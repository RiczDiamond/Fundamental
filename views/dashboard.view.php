<?php
    $currentUserId = $session->get('user_id');
    $currentUser = $account->get($currentUserId);

    if (!$currentUser) {
        header('Location: /logout');
        exit;
    }

    $dashboardPages = ['overview', 'users', 'blogs', 'pages', 'content', 'media', 'profile'];
    $page = $dashboardPage ?? 'overview';
    if (!in_array($page, $dashboardPages, true)) {
        $page = 'overview';
    }

    $canDashboard = $auth->perm_check(null, 'dashboard');
    $canManageUsers = $auth->perm_check(null, 'users.manage');
    $canBlogWrite = $auth->perm_check(null, 'blog.write');
    $canPagesWrite = $auth->perm_check(null, 'pages.write');
    $blogModel = new Blog($link);
    $pageModel = new Page($link);
    $contentTypeRegistry = new ContentTypeRegistry();
    $contentItemModel = new ContentItem($link);
    $contentModel = new Content($link);
    $sanitizerService = class_exists('App\\Services\\SanitizerService') ? new App\Services\SanitizerService() : null;
    $validationService = class_exists('App\\Services\\ValidationService') ? new App\Services\ValidationService() : null;
    $actionLogger = class_exists('App\\Services\\ActionLoggerService') ? new App\Services\ActionLoggerService(dirname(__DIR__)) : null;

    $sanitizeText = static function ($value) use ($sanitizerService) {
        if ($sanitizerService) {
            return $sanitizerService->sanitizeText($value);
        }
        return trim((string)$value);
    };

    $sanitizeHtml = static function ($value) use ($sanitizerService, $sanitizeText) {
        if ($sanitizerService) {
            return $sanitizerService->sanitizeHtml($value);
        }
        return $sanitizeText($value);
    };

    $sanitizeUrl = static function ($value) use ($sanitizerService, $sanitizeText) {
        if ($sanitizerService) {
            return $sanitizerService->sanitizeUrl($value);
        }
        return $sanitizeText($value);
    };

    $deleteCacheKeys = static function (array $keys) use (&$appCache): void {
        if (!isset($appCache) || !$appCache instanceof \Symfony\Component\Cache\Adapter\FilesystemAdapter) {
            return;
        }

        foreach ($keys as $cacheKey) {
            $cacheKey = trim((string)$cacheKey);
            if ($cacheKey === '') {
                continue;
            }
            $appCache->deleteItem($cacheKey);
        }
    };

    $dashboardLockFactory = null;
    if (class_exists('Symfony\\Component\\Lock\\LockFactory') && class_exists('Symfony\\Component\\Lock\\Store\\FlockStore')) {
        $lockDirectory = dirname(__DIR__) . '/storage/locks';
        if (!is_dir($lockDirectory)) {
            @mkdir($lockDirectory, 0775, true);
        }
        $dashboardLockFactory = new \Symfony\Component\Lock\LockFactory(
            new \Symfony\Component\Lock\Store\FlockStore($lockDirectory)
        );
    }

    $actionRateLimiters = [];
    if (
        isset($appCache)
        && $appCache instanceof \Symfony\Component\Cache\Adapter\FilesystemAdapter
        && class_exists('Symfony\\Component\\RateLimiter\\RateLimiterFactory')
        && class_exists('Symfony\\Component\\RateLimiter\\Storage\\CacheStorage')
    ) {
        $limiterStorage = new \Symfony\Component\RateLimiter\Storage\CacheStorage($appCache);
        $actionRateLimiters['media_upload'] = new \Symfony\Component\RateLimiter\RateLimiterFactory([
            'id' => 'dashboard_media_upload',
            'policy' => 'sliding_window',
            'limit' => 30,
            'interval' => '10 minutes',
        ], $limiterStorage);

        $actionRateLimiters['blog_preview_token'] = new \Symfony\Component\RateLimiter\RateLimiterFactory([
            'id' => 'dashboard_preview_token',
            'policy' => 'sliding_window',
            'limit' => 25,
            'interval' => '1 hour',
        ], $limiterStorage);
    }

    $consumeActionRateLimit = static function (string $action, int $userId) use ($actionRateLimiters): array {
        if (!isset($actionRateLimiters[$action])) {
            return [true, 0];
        }

        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $limiter = $actionRateLimiters[$action]->create($userId . '|' . $clientIp);
        $limit = $limiter->consume(1);
        if ($limit->isAccepted()) {
            return [true, 0];
        }

        $retryAfter = $limit->getRetryAfter();
        $retrySeconds = $retryAfter ? max(1, (int)($retryAfter->getTimestamp() - time())) : 60;
        return [false, $retrySeconds];
    };

    $dashboardStopwatch = class_exists('Symfony\\Component\\Stopwatch\\Stopwatch')
        ? new \Symfony\Component\Stopwatch\Stopwatch(true)
        : null;

    $blogActionHandler = class_exists('App\\Http\\Controllers\\Dashboard\\BlogActionHandler')
        ? new \App\Http\Controllers\Dashboard\BlogActionHandler(
            $blogModel,
            $canBlogWrite,
            (int)$currentUserId,
            $validationService,
            $actionLogger,
            $sanitizeText,
            $sanitizeHtml,
            $sanitizeUrl,
            $deleteCacheKeys
        )
        : null;

    $pageActionHandler = class_exists('App\\Http\\Controllers\\Dashboard\\PageActionHandler')
        ? new \App\Http\Controllers\Dashboard\PageActionHandler(
            $pageModel,
            $canPagesWrite,
            (int)$currentUserId,
            $validationService,
            $actionLogger,
            $sanitizeText,
            $sanitizeHtml,
            $sanitizeUrl,
            $deleteCacheKeys
        )
        : null;

    $contentActionHandler = class_exists('App\\Http\\Controllers\\Dashboard\\ContentActionHandler')
        ? new \App\Http\Controllers\Dashboard\ContentActionHandler(
            $contentItemModel,
            $contentTypeRegistry,
            $canPagesWrite,
            (int)$currentUserId,
            $validationService,
            $actionLogger,
            $sanitizeText,
            $sanitizeHtml,
            $sanitizeUrl,
            $deleteCacheKeys
        )
        : null;

    $measureDashboardBlock = static function (string $name, callable $callback) use ($dashboardStopwatch, $actionLogger, $currentUserId) {
        if (!$dashboardStopwatch) {
            return $callback();
        }

        $dashboardStopwatch->start($name);
        try {
            return $callback();
        } finally {
            $event = $dashboardStopwatch->stop($name);
            if ($actionLogger && $event->getDuration() >= 200) {
                $actionLogger->info('Dashboard slow query block', [
                    'user_id' => $currentUserId,
                    'block' => $name,
                    'duration_ms' => $event->getDuration(),
                    'memory_bytes' => $event->getMemory(),
                ]);
            }
        }
    };

    if (!$canDashboard) {
        http_response_code(403);
        echo 'Geen toegang tot dashboard.';
        exit;
    }

    $errors = [];
    $success = '';

    $okMessages = [
        'profile_updated' => 'Profiel bijgewerkt.',
        'password_updated' => 'Wachtwoord bijgewerkt.',
        'blog_created' => 'Blogpost aangemaakt.',
        'blog_updated' => 'Blogpost bijgewerkt.',
        'blog_deleted' => 'Blogpost verwijderd.',
        'blog_duplicated' => 'Concept gedupliceerd.',
        'blog_bulk_updated' => 'Bulk-actie uitgevoerd.',
        'blog_revision_restored' => 'Revisie teruggezet.',
        'blog_autosaved' => 'Autosave opgeslagen.',
        'blog_preview_created' => 'Preview-link aangemaakt.',
        'media_uploaded' => 'Media geüpload.',
        'media_updated' => 'Media metadata bijgewerkt.',
        'media_folder_created' => 'Mediamap aangemaakt.',
        'menu_saved' => 'Menu-item opgeslagen.',
        'menu_deleted' => 'Menu-item verwijderd.',
        'menu_reordered' => 'Menu-volgorde bijgewerkt.',
        'page_created' => 'Pagina aangemaakt.',
        'page_updated' => 'Pagina bijgewerkt.',
        'page_deleted' => 'Pagina verwijderd.',
        'content_created' => 'Content item aangemaakt.',
        'content_updated' => 'Content item bijgewerkt.',
        'content_deleted' => 'Content item verwijderd.',
        'user_updated' => 'Gebruiker bijgewerkt.',
        'user_banned' => 'Gebruiker geblokkeerd.',
        'user_unbanned' => 'Gebruiker gedeblokkeerd.',
        'user_trashed' => 'Gebruiker verplaatst naar verwijderlijst.',
        'user_restored' => 'Gebruiker hersteld.',
    ];

    if (!empty($_GET['ok']) && isset($okMessages[$_GET['ok']])) {
        $success = $okMessages[$_GET['ok']];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedCsrf = $_POST['csrf'] ?? '';
        $csrfValid = false;
        if (isset($csrfTokenManager) && $csrfTokenManager instanceof \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface) {
            $csrfValid = $csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('fundamental_form', (string)$postedCsrf));
        } else {
            $csrfValid = hash_equals((string)$csrfToken, (string)$postedCsrf);
        }

        if (!$csrfValid) {
            $errors[] = 'Ongeldige aanvraag (CSRF).';
        } else {
            $action = $_POST['action'] ?? '';
            $activeActionLock = null;

            $resolveActionLockKey = static function (string $actionName): ?string {
                $id = (int)($_POST['id'] ?? 0);
                return match ($actionName) {
                    'blog_create' => 'blog:create',
                    'blog_update' => 'blog:update:' . max(0, $id),
                    'blog_delete' => 'blog:delete:' . max(0, $id),
                    'blog_duplicate' => 'blog:duplicate:' . max(0, $id),
                    'blog_bulk' => 'blog:bulk',
                    'blog_restore_revision' => 'blog:restore:' . (int)($_POST['revision_id'] ?? 0),
                    'page_create' => 'page:create',
                    'page_update' => 'page:update:' . max(0, $id),
                    'page_delete' => 'page:delete:' . max(0, $id),
                    'content_create' => 'content:create:' . trim((string)($_POST['content_type'] ?? 'unknown')),
                    'content_update' => 'content:update:' . max(0, $id),
                    'content_delete' => 'content:delete:' . max(0, $id),
                    'menu_save' => 'menu:save:' . max(0, $id),
                    'menu_reorder_tree' => 'menu:reorder:main',
                    'menu_delete' => 'menu:delete:' . max(0, $id),
                    'media_folder_create' => 'media:folder:create',
                    'media_update' => 'media:update:' . max(0, $id),
                    default => null,
                };
            };

            $lockKey = $resolveActionLockKey($action);
            if ($lockKey !== null && $dashboardLockFactory instanceof \Symfony\Component\Lock\LockFactory) {
                $activeActionLock = $dashboardLockFactory->createLock('dashboard:' . $lockKey, 20.0, false);
                if (!$activeActionLock->acquire()) {
                    $errors[] = 'Deze actie wordt al uitgevoerd. Probeer het over een paar seconden opnieuw.';
                    $action = '';
                }
            }

            if ($action === 'media_upload' || $action === 'blog_preview_token') {
                [$accepted, $retrySeconds] = $consumeActionRateLimit($action, (int)$currentUserId);
                if (!$accepted) {
                    $errors[] = 'Te veel verzoeken voor deze actie. Probeer opnieuw over ' . $retrySeconds . ' seconden.';
                    $action = '';
                }
            }

            if ($action === 'profile_update') {
                $gender = trim($_POST['gender'] ?? '');
                $birthDate = trim($_POST['birth_date'] ?? '');
                $data = [
                    'id' => $currentUserId,
                    'display_name' => trim($_POST['display_name'] ?? ''),
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'username' => trim($_POST['username'] ?? ''),
                    'gender' => $gender !== '' ? $gender : null,
                    'birth_date' => $birthDate !== '' ? $birthDate : null,
                ];

                if (empty($data['email']) || empty($data['username'])) {
                    $errors[] = 'Email en gebruikersnaam zijn verplicht.';
                } elseif ($account->put($data)) {
                    header('Location: /dashboard/profile?ok=profile_updated');
                    exit;
                } else {
                    $errors[] = 'Profiel kon niet worden bijgewerkt.';
                }
            }

            if ($action === 'profile_password') {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

                if (empty($currentPassword) || empty($newPassword)) {
                    $errors[] = 'Vul alle wachtwoordvelden in.';
                } elseif ($newPassword !== $newPasswordConfirm) {
                    $errors[] = 'Nieuw wachtwoord komt niet overeen.';
                } elseif (!password_verify($currentPassword, $currentUser['password'])) {
                    $errors[] = 'Huidig wachtwoord is onjuist.';
                } elseif ($account->put(['id' => $currentUserId, 'password' => $newPassword])) {
                    header('Location: /dashboard/profile?ok=password_updated');
                    exit;
                } else {
                    $errors[] = 'Wachtwoord kon niet worden bijgewerkt.';
                }
            }

            if ($blogActionHandler instanceof \App\Http\Controllers\Dashboard\BlogActionHandler && $blogActionHandler->supports($action)) {
                $errors = $blogActionHandler->handle($action, $errors);
                $action = '';
            }

            if ($pageActionHandler instanceof \App\Http\Controllers\Dashboard\PageActionHandler && $pageActionHandler->supports($action)) {
                $errors = $pageActionHandler->handle($action, $errors);
                $action = '';
            }

            if ($contentActionHandler instanceof \App\Http\Controllers\Dashboard\ContentActionHandler && $contentActionHandler->supports($action)) {
                $errors = $contentActionHandler->handle($action, $errors);
                $action = '';
            }

            if ($action === 'user_update') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);
                    $payload = [
                        'id' => $targetId,
                        'display_name' => trim($_POST['display_name'] ?? ''),
                        'email' => trim($_POST['email'] ?? ''),
                        'username' => trim($_POST['username'] ?? ''),
                        'role' => trim($_POST['role'] ?? 'user'),
                        'status' => trim($_POST['status'] ?? 'active'),
                    ];

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($account->put($payload)) {
                        header('Location: /dashboard/users?ok=user_updated');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden bijgewerkt.';
                    }
                }
            }

            if ($action === 'user_ban') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);
                    $reason = trim($_POST['reason'] ?? 'Geblokkeerd via dashboard');
                    $minutes = max(0, (int)($_POST['minutes'] ?? 0));
                    $until = $minutes > 0 ? time() + ($minutes * 60) : null;

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($targetId === (int)$currentUserId) {
                        $errors[] = 'Je kunt jezelf niet blokkeren.';
                    } elseif ($account->ban($targetId, $reason, $until)) {
                        header('Location: /dashboard/users?ok=user_banned');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden geblokkeerd.';
                    }
                }
            }

            if ($action === 'user_unban') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($account->unban($targetId)) {
                        header('Location: /dashboard/users?ok=user_unbanned');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden gedeblokkeerd.';
                    }
                }
            }

            if ($action === 'user_trash') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($targetId === (int)$currentUserId) {
                        $errors[] = 'Je kunt jezelf niet verwijderen.';
                    } elseif ($account->trash($targetId)) {
                        header('Location: /dashboard/users?ok=user_trashed');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden verplaatst naar verwijderlijst.';
                    }
                }
            }

            if ($action === 'user_restore') {
                if (!$canManageUsers) {
                    $errors[] = 'Geen rechten om gebruikers te beheren.';
                } else {
                    $targetId = (int)($_POST['id'] ?? 0);

                    if ($targetId <= 0) {
                        $errors[] = 'Ongeldige gebruiker.';
                    } elseif ($account->restore($targetId)) {
                        header('Location: /dashboard/users?ok=user_restored');
                        exit;
                    } else {
                        $errors[] = 'Gebruiker kon niet worden hersteld.';
                    }
                }
            }

            if ($action === 'media_folder_create') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om media mappen te beheren.';
                } else {
                    $created = $contentModel->createMediaFolder(
                        trim($_POST['name'] ?? ''),
                        (int)($_POST['parent_id'] ?? 0),
                        $currentUserId
                    );

                    if ($created) {
                        $deleteCacheKeys(['sitemap.xml.public']);
                        header('Location: /dashboard/media?ok=media_folder_created');
                        exit;
                    }
                    $errors[] = $contentModel->getLastError() ?: 'Map kon niet worden aangemaakt.';
                }
            }

            if ($action === 'media_upload') {
                $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

                if (!$canPagesWrite) {
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        http_response_code(403);
                        echo json_encode(['ok' => false, 'error' => 'Geen rechten om media te uploaden.']);
                        exit;
                    }
                    $errors[] = 'Geen rechten om media te uploaden.';
                } else {
                    $file = $_FILES['media_file'] ?? null;
                    if (!$file) {
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            http_response_code(400);
                            echo json_encode(['ok' => false, 'error' => 'Geen bestand geüpload.']);
                            exit;
                        }
                        $errors[] = 'Geen bestand geüpload.';
                    } else {
                        $uploaded = $contentModel->uploadMedia(
                            $file,
                            (int)($_POST['folder_id'] ?? 0),
                            trim($_POST['alt_text'] ?? ''),
                            $currentUserId
                        );

                        if ($uploaded) {
                            if ($isAjax) {
                                $uploadedItem = $contentModel->getMediaItemById((int)$uploaded);
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode([
                                    'ok' => true,
                                    'item' => [
                                        'id' => (int)($uploadedItem['id'] ?? $uploaded),
                                        'filename' => (string)($uploadedItem['filename'] ?? ($file['name'] ?? 'Bestand')),
                                        'path' => (string)($uploadedItem['path'] ?? ''),
                                        'alt_text' => (string)($uploadedItem['alt_text'] ?? ''),
                                        'mime_type' => (string)($uploadedItem['mime_type'] ?? ($file['type'] ?? '')),
                                    ],
                                ]);
                                exit;
                            }
                            header('Location: /dashboard/media?ok=media_uploaded');
                            exit;
                        }
                        $errorMessage = $contentModel->getLastError() ?: 'Upload mislukt.';
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            http_response_code(500);
                            echo json_encode(['ok' => false, 'error' => $errorMessage]);
                            exit;
                        }
                        $errors[] = $errorMessage;
                    }
                }
            }

            if ($action === 'media_update') {
                $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

                if (!$canPagesWrite) {
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        http_response_code(403);
                        echo json_encode(['ok' => false, 'error' => 'Geen rechten om media metadata te wijzigen.']);
                        exit;
                    }
                    $errors[] = 'Geen rechten om media metadata te wijzigen.';
                } else {
                    $mediaId = (int)($_POST['id'] ?? 0);
                    $updated = $contentModel->updateMediaMeta($mediaId, [
                        'alt_text' => trim($_POST['alt_text'] ?? ''),
                        'folder_id' => (int)($_POST['folder_id'] ?? 0),
                        'crop_x' => $_POST['crop_x'] ?? null,
                        'crop_y' => $_POST['crop_y'] ?? null,
                        'crop_w' => $_POST['crop_w'] ?? null,
                        'crop_h' => $_POST['crop_h'] ?? null,
                        'resize_w' => $_POST['resize_w'] ?? null,
                        'resize_h' => $_POST['resize_h'] ?? null,
                    ]);

                    if ($updated) {
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['ok' => true, 'saved_at' => date('Y-m-d H:i:s')]);
                            exit;
                        }
                        header('Location: /dashboard/media?ok=media_updated');
                        exit;
                    }

                    $errorMessage = $contentModel->getLastError() ?: 'Media metadata kon niet worden bijgewerkt.';
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        http_response_code(500);
                        echo json_encode(['ok' => false, 'error' => $errorMessage]);
                        exit;
                    }

                    $errors[] = $errorMessage;
                }
            }

            if ($action === 'menu_save') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om menu te beheren.';
                } else {
                    $saved = $contentModel->saveMenuItem([
                        'id' => (int)($_POST['id'] ?? 0),
                        'parent_id' => (int)($_POST['parent_id'] ?? 0),
                        'location' => trim($_POST['location'] ?? 'main'),
                        'label' => trim($_POST['label'] ?? ''),
                        'url' => trim($_POST['url'] ?? ''),
                        'sort_order' => (int)($_POST['sort_order'] ?? 0),
                        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    ], $currentUserId);

                    if ($saved) {
                        $deleteCacheKeys(['navigation.main.tree', 'sitemap.xml.public']);
                        header('Location: /dashboard/pages?ok=menu_saved');
                        exit;
                    }
                    $errors[] = 'Menu-item kon niet worden opgeslagen.';
                }
            }

            if ($action === 'menu_reorder_tree') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om menu te beheren.';
                } else {
                    $treeRaw = trim((string)($_POST['menu_tree_json'] ?? ''));
                    $tree = json_decode($treeRaw, true);
                    if (!is_array($tree)) {
                        $errors[] = 'Ongeldige menu-volgorde payload.';
                    } elseif ($contentModel->reorderMenuTree('main', $tree, $currentUserId)) {
                        $deleteCacheKeys(['navigation.main.tree', 'sitemap.xml.public']);
                        header('Location: /dashboard/pages?ok=menu_reordered');
                        exit;
                    } else {
                        $errors[] = 'Menu-volgorde kon niet worden opgeslagen.';
                    }
                }
            }

            if ($action === 'menu_delete') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om menu te beheren.';
                } else {
                    $menuId = (int)($_POST['id'] ?? 0);
                    if ($menuId <= 0) {
                        $errors[] = 'Ongeldig menu-item.';
                    } elseif ($contentModel->deleteMenuItem($menuId)) {
                        $deleteCacheKeys(['navigation.main.tree', 'sitemap.xml.public']);
                        header('Location: /dashboard/pages?ok=menu_deleted');
                        exit;
                    } else {
                        $errors[] = 'Menu-item kon niet worden verwijderd.';
                    }
                }
            }

            if (is_object($activeActionLock) && method_exists($activeActionLock, 'release')) {
                $activeActionLock->release();
            }
        }
    }

    $stats = $account->get_dashboard_stats();
    $usersSearch = trim($_GET['q'] ?? '');
    $blogSearch = trim($_GET['blog_q'] ?? '');
    $blogStatusFilter = trim($_GET['blog_status'] ?? '');
    $blogCategoryFilter = trim($_GET['blog_category'] ?? '');
    $blogSort = trim($_GET['blog_sort'] ?? 'newest');
    $blogPage = max(1, (int)($_GET['blog_page'] ?? 1));
    $blogPerPage = max(5, min((int)($_GET['blog_per_page'] ?? 10), 50));

    $mediaSearch = trim($_GET['media_q'] ?? '');
    $mediaFolderFilter = (int)($_GET['media_folder'] ?? 0);
    $mediaSort = trim($_GET['media_sort'] ?? 'newest');
    $mediaPage = max(1, (int)($_GET['media_page'] ?? 1));
    $mediaPerPage = max(6, min((int)($_GET['media_per_page'] ?? 12), 60));

    $users = $measureDashboardBlock('dashboard.users.list', static function () use ($account, $usersSearch) {
        return ($usersSearch !== '') ? $account->get_all($usersSearch) : $account->get_all();
    });
    $blogList = $measureDashboardBlock('dashboard.blogs.list', static function () use ($blogModel, $blogSearch, $blogStatusFilter, $blogCategoryFilter, $blogSort, $blogPage, $blogPerPage) {
        return $blogModel->listAllAdvanced([
            'search' => $blogSearch,
            'status' => $blogStatusFilter,
            'category' => $blogCategoryFilter,
            'sort' => $blogSort,
            'page' => $blogPage,
            'per_page' => $blogPerPage,
        ]);
    });
    $blogPosts = $blogList['items'];
    $blogPagesTotal = $blogList['pages'];
    $blogTotalItems = $blogList['total'];
    $blogCategories = $blogModel->getDistinctCategories(200);

    $mediaFolders = $measureDashboardBlock('dashboard.media.folders', static function () use ($contentModel) {
        return $contentModel->listMediaFolders();
    });
    $mediaList = $measureDashboardBlock('dashboard.media.list', static function () use ($contentModel, $mediaSearch, $mediaFolderFilter, $mediaPage, $mediaPerPage, $mediaSort) {
        return $contentModel->listMediaItems($mediaSearch, $mediaFolderFilter, $mediaPage, $mediaPerPage, $mediaSort);
    });
    $mediaItems = $mediaList['items'];
    $mediaPagesTotal = $mediaList['pages'];
    $mediaTotalItems = $mediaList['total'];
    $menuItems = $contentModel->listMenuItems('main');
    $menuTreeItems = $contentModel->getMenuTree('main');

    $pageSearch = trim($_GET['page_q'] ?? '');
    $pageStatus = trim($_GET['page_status'] ?? '');
    $pagePageNumber = max(1, (int)($_GET['page_page'] ?? 1));
    $pagePerPage = max(5, min((int)($_GET['page_per_page'] ?? 10), 50));
    $pagesList = $measureDashboardBlock('dashboard.pages.list', static function () use ($pageModel, $pageSearch, $pageStatus, $pagePageNumber, $pagePerPage) {
        return $pageModel->listAll($pageSearch, $pageStatus, $pagePageNumber, $pagePerPage);
    });
    $managedPages = $pagesList['items'];
    $managedPagesTotal = $pagesList['total'];
    $managedPagesPagesTotal = $pagesList['pages'];
    $pageMode = 'list';
    $editPageId = null;
    $editPageData = null;
    if ($page === 'pages') {
        $subPage = strtolower((string)($dashboardSubPage ?? ''));
        if ($subPage === 'create' && $canPagesWrite) {
            $pageMode = 'create';
        } elseif ($subPage === 'edit' && $canPagesWrite) {
            $pageMode = 'edit';
            $editPageId = (int)($dashboardSubId ?? 0);
            $editPageData = $pageModel->get($editPageId);
            if (!$editPageData) {
                $errors[] = 'Pagina niet gevonden.';
                $pageMode = 'list';
                $editPageId = null;
            }
        }
    }

    $contentTypesList = $contentTypeRegistry->getAll();
    $dashboardContentTypeSlug = trim((string)($dashboardContentTypeSlug ?? ''));
    if ($dashboardContentTypeSlug === '') {
        $dashboardContentTypeSlug = 'services';
    }

    $contentTypeHit = $contentTypeRegistry->getBySlug($dashboardContentTypeSlug);
    if (!$contentTypeHit) {
        $contentTypeHit = $contentTypeRegistry->getBySlug('services');
    }

    $contentSelectedTypeKey = (string)($contentTypeHit['key'] ?? 'services');
    $contentSelectedTypeDefinition = (array)($contentTypeHit['definition'] ?? []);

    $contentSearch = trim((string)($_GET['content_q'] ?? ''));
    $contentStatus = trim((string)($_GET['content_status'] ?? ''));
    $contentManagedPage = max(1, (int)($_GET['content_page'] ?? 1));
    $contentManagedPerPage = max(5, min((int)($_GET['content_per_page'] ?? 10), 50));

    $contentManagedList = $measureDashboardBlock('dashboard.content.list', static function () use ($contentItemModel, $contentSelectedTypeKey, $contentSearch, $contentStatus, $contentManagedPage, $contentManagedPerPage) {
        return $contentItemModel->listAll(
            $contentSelectedTypeKey,
            $contentSearch,
            $contentStatus,
            $contentManagedPage,
            $contentManagedPerPage
        );
    });

    $contentManagedItems = $contentManagedList['items'];
    $contentManagedTotal = $contentManagedList['total'];
    $contentManagedPagesTotal = $contentManagedList['pages'];

    $contentMode = 'list';
    $contentEditItemId = null;
    $contentEditItem = null;
    if ($page === 'content') {
        $contentAction = strtolower(trim((string)($dashboardContentAction ?? '')));
        if ($contentAction === 'create' && $canPagesWrite) {
            $contentMode = 'create';
        } elseif ($contentAction === 'edit' && $canPagesWrite) {
            $contentMode = 'edit';
            $contentEditItemId = (int)($dashboardContentEditId ?? 0);
            $contentEditItem = $contentItemModel->get($contentEditItemId);

            if (!$contentEditItem || (string)($contentEditItem['type'] ?? '') !== $contentSelectedTypeKey) {
                $errors[] = 'Content item niet gevonden.';
                $contentMode = 'list';
                $contentEditItem = null;
            }
        }
    }

    $trashedUsers = $account->get_trash(100);
    $profileGroups = $account->get_groups($currentUserId);
    $profilePermissions = $account->get_permissions($currentUserId);

    $blogMode = 'list';
    $blogFormData = [];
    $blogEditId = null;
    if ($page === 'blogs') {
        $subPage = strtolower((string)($dashboardSubPage ?? ''));
        if ($subPage === 'create' && $canBlogWrite) {
            $blogMode = 'create';
            $blogFormData = [
                'title' => '',
                'slug' => '',
                'featured_image' => '',
                'intro' => '',
                'category' => '',
                'tags' => '',
                'meta_title' => '',
                'meta_description' => '',
                'og_image' => '',
                'excerpt' => '',
                'content' => '',
                'status' => 'draft',
                'scheduled_at' => '',
            ];
        } elseif ($subPage === 'edit' && $canBlogWrite) {
            $blogMode = 'edit';
            $blogEditId = (int)($dashboardSubId ?? 0);
            $blogFormData = $blogModel->get($blogEditId);
            if (!$blogFormData) {
                $errors[] = 'Blogpost niet gevonden.';
                $blogMode = 'list';
            }
        }
    }

    $blogRevisions = [];
    $blogAutosave = null;
    if ($page === 'blogs' && $blogMode === 'edit' && !empty($blogEditId)) {
        $blogRevisions = $blogModel->listRevisions($blogEditId, 15);
        $blogAutosave = $blogModel->getLatestAutosave($blogEditId, $currentUserId);
    }

    $createdPreviewToken = trim((string)($_GET['preview_token'] ?? ''));

    $activeUsers = [];
    $bannedUsers = [];
    foreach ($users as $row) {
        if (($row['status'] ?? '') === 'banned') {
            $bannedUsers[] = $row;
        } else {
            $activeUsers[] = $row;
        }
    }

    $pageTitles = [
        'overview' => 'Dashboard',
        'users' => 'Gebruikers',
        'blogs' => ($blogMode === 'create' ? 'Nieuwe blogpost' : ($blogMode === 'edit' ? 'Blogpost bewerken' : 'Blogposts')),
        'pages' => ($pageMode === 'create' ? 'Nieuwe pagina' : ($pageMode === 'edit' ? 'Pagina bewerken' : 'Pagina\'s')),
        'content' => ($contentMode === 'create'
            ? 'Nieuw content item'
            : ($contentMode === 'edit'
                ? 'Content item bewerken'
                : (trim((string)($contentSelectedTypeDefinition['label'] ?? '')) !== ''
                    ? (string)$contentSelectedTypeDefinition['label']
                    : 'Content'))),
        'media' => 'Media',
        'profile' => 'Profiel',
    ];

    $activePageTitle = $pageTitles[$page] ?? 'Dashboard';

    $dashboardEditorMediaItems = [];
    foreach (($mediaItems ?? []) as $mediaRow) {
        $dashboardEditorMediaItems[] = [
            'id' => (int)($mediaRow['id'] ?? 0),
            'filename' => (string)($mediaRow['filename'] ?? ''),
            'path' => (string)($mediaRow['path'] ?? ''),
            'alt_text' => (string)($mediaRow['alt_text'] ?? ''),
            'mime_type' => (string)($mediaRow['mime_type'] ?? ''),
        ];
    }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fundamental CMS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; color: #1f2937; }
        .adminbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: #1d2327;
            color: #f0f6fc;
            z-index: 60;
            border-bottom: 1px solid #2c3338;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 12px;
            box-sizing: border-box;
            font-size: 13px;
        }
        .adminbar a { color: #9fd3ff; text-decoration: none; }
        .adminbar-left, .adminbar-right { display:flex; align-items:center; gap:14px; }
        .admin-brand { font-weight: 700; color: #fff; letter-spacing: .2px; }

        .wp-shell {
            display: grid;
            grid-template-columns: 230px minmax(0, 1fr);
            min-height: 100vh;
            padding-top: 40px;
        }

        .wp-sidebar {
            background: #1d2327;
            color: #b6c0c8;
            border-right: 1px solid #2c3338;
            padding: 12px 0;
        }
        .wp-sidebar-title {
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 10px 14px 8px;
        }
        .wp-nav { display: flex; flex-direction: column; }
        .wp-nav a {
            color: #b6c0c8;
            text-decoration: none;
            padding: 10px 14px;
            border-left: 3px solid transparent;
            font-size: 14px;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }
        .wp-nav a:hover { background: #2c3338; color: #fff; }
        .wp-nav a.active {
            background: #2271b1;
            color: #fff;
            border-left-color: #72aee6;
        }
        .wp-nav a.sub-item {
            padding-left: 28px;
            font-size: 13px;
            background: #23282d;
        }
        .wp-nav a.sub-item:hover {
            background: #2c3338;
        }

        .wp-main {
            padding: 16px;
        }
        .wp-pagehead {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .title { margin: 0; font-size: 26px; font-weight: 600; line-height: 1.1; }
        .muted { color: #646970; font-size: 13px; }

        .card {
            background: #fff;
            border-radius: 6px;
            border: 1px solid #dcdcde;
            padding: 16px;
            box-shadow: none;
            margin-bottom: 14px;
        }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .stat { background: #f6f7f7; border-radius: 6px; border: 1px solid #dcdcde; padding: 12px; }
        .stat .label { font-size: 12px; color: #646970; }
        .stat .value { font-size: 24px; font-weight: bold; margin-top: 4px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        input, select, button, textarea { padding: 8px 10px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; }
        input, select, textarea { background: #fff; }
        button { background: #2271b1; color: #fff; border: 1px solid #2271b1; cursor: pointer; }
        button.secondary { background: #50575e; border-color: #50575e; }
        button.warn { background: #b32d2e; border-color: #b32d2e; }
        a { color: #2271b1; }
        table { width: 100%; border-collapse: collapse; display: block; overflow-x: auto; }
        table thead, table tbody { width: 100%; }
        th, td { border-bottom: 1px solid #dcdcde; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f6f7f7; font-size: 13px; }
        .alert-ok { padding: 10px 12px; border-radius: 4px; background: #edfaef; color: #215b2a; margin-bottom: 12px; border: 1px solid #9ad8a5; }
        .alert-error { padding: 10px 12px; border-radius: 4px; background: #fcf0f1; color: #8a2424; margin-bottom: 12px; border: 1px solid #e6a7aa; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 12px; }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.banned { background: #fee2e2; color: #991b1b; }
        .two-col { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .small { font-size: 12px; color: #646970; }
        .editor-media-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 14px;
            box-sizing: border-box;
        }
        .editor-media-modal.is-open { display: flex; }
        .editor-media-panel {
            width: min(980px, 100%);
            max-height: 84vh;
            overflow: auto;
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            padding: 14px;
        }
        .editor-media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .editor-media-upload {
            margin-top: 10px;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 10px;
            background: #f6f7f7;
            display: grid;
            gap: 8px;
        }
        .editor-media-upload-status {
            font-size: 12px;
            min-height: 18px;
            color: #646970;
        }
        .editor-media-upload-status.is-error { color: #b32d2e; }
        .editor-media-upload-status.is-ok { color: #166534; }
        .editor-media-card {
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 10px;
            background: #f6f7f7;
            display: grid;
            gap: 6px;
        }
        .editor-media-card img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dcdcde;
            background: #fff;
        }
        .editor-toolbar {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            padding: 8px;
            border: 1px solid #dcdcde;
            border-bottom: 0;
            border-radius: 6px 6px 0 0;
            background: #f6f7f7;
        }
        .editor-toolbar button {
            min-width: auto;
            padding: 5px 8px;
            font-size: 12px;
        }
        .editor-wysiwyg {
            border: 1px solid #8c8f94;
            border-radius: 0 0 6px 6px;
            background: #fff;
            min-height: 180px;
            padding: 10px;
            overflow: auto;
        }
        .editor-wysiwyg:focus {
            outline: 2px solid #72aee6;
            outline-offset: 1px;
        }
        .dashboard-image-preview {
            margin-top: 8px;
            border: 1px solid #dcdcde;
            border-radius: 6px;
            background: #fff;
            padding: 6px;
            max-width: 320px;
        }
        .dashboard-image-preview img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 4px;
        }
        .dashboard-image-preview.is-empty {
            display: none;
        }
        @media (max-width: 960px) {
            .wp-shell { grid-template-columns: 1fr; }
            .wp-sidebar {
                position: sticky;
                top: 40px;
                z-index: 40;
                padding: 8px;
                border-right: none;
                border-bottom: 1px solid #2c3338;
            }
            .wp-sidebar-title { display: none; }
            .wp-nav {
                flex-direction: row;
                overflow-x: auto;
                gap: 6px;
                padding-bottom: 2px;
            }
            .wp-nav a {
                border-left: none;
                border-radius: 4px;
                white-space: nowrap;
                padding: 8px 10px;
            }
            .wp-nav a.active { border-left: none; }
        }
        @media (max-width: 760px) {
            .wp-main { padding: 12px; }
            .wp-pagehead { align-items: flex-start; flex-direction: column; }
            .title { font-size: 22px; }
            input, select, textarea, button { width: 100%; max-width: 100%; }
            .row > a { width: 100%; }
            .adminbar-left, .adminbar-right { gap: 8px; }
        }
    </style>
</head>
<body>
    <header class="adminbar">
        <div class="adminbar-left">
            <span class="admin-brand">Fundamental CMS</span>
            <a href="/">Site bekijken</a>
        </div>
        <div class="adminbar-right">
            <span><?php echo htmlspecialchars($currentUser['username'] ?? ''); ?></span>
            <a href="/dashboard/profile">Profiel</a>
            <a href="/logout">Uitloggen</a>
        </div>
    </header>

    <div class="wp-shell">
        <aside class="wp-sidebar">
            <div class="wp-sidebar-title">Navigatie</div>
            <nav class="wp-nav">
                <a class="<?php echo $page === 'overview' ? 'active' : ''; ?>" href="/dashboard/overview">Dashboard</a>
                <a class="<?php echo $page === 'users' ? 'active' : ''; ?>" href="/dashboard/users">Gebruikers</a>
                <a class="<?php echo $page === 'blogs' ? 'active' : ''; ?>" href="/dashboard/blogs">Blogposts</a>
                <a class="<?php echo $page === 'pages' ? 'active' : ''; ?>" href="/dashboard/pages">Pagina's</a>
                <?php foreach (($contentTypesList ?? []) as $sidebarTypeKey => $sidebarTypeDefinition) : ?>
                    <?php
                        $sidebarTypeSlug = (string)($sidebarTypeDefinition['slug'] ?? $sidebarTypeKey);
                        $sidebarTypeActive = $page === 'content' && (string)($contentSelectedTypeKey ?? '') === (string)$sidebarTypeKey;
                    ?>
                    <a class="<?php echo $sidebarTypeActive ? 'active' : ''; ?>" href="/dashboard/<?php echo htmlspecialchars($sidebarTypeSlug); ?>">
                        <?php echo htmlspecialchars((string)($sidebarTypeDefinition['label'] ?? $sidebarTypeKey)); ?>
                    </a>
                <?php endforeach; ?>
                <a class="<?php echo $page === 'media' ? 'active' : ''; ?>" href="/dashboard/media">Media</a>
                <a class="<?php echo $page === 'profile' ? 'active' : ''; ?>" href="/dashboard/profile">Profiel</a>
            </nav>
        </aside>

        <main class="wp-main">
            <div class="wp-pagehead">
                <div>
                    <h1 class="title"><?php echo htmlspecialchars($activePageTitle); ?></h1>
                    <div class="muted">Ingelogd als <?php echo htmlspecialchars($currentUser['username'] ?? ''); ?> (<?php echo htmlspecialchars($currentUser['role'] ?? 'user'); ?>)</div>
                </div>
                <div class="row">
                    <a href="/" target="_blank">Open site</a>
                </div>
            </div>

            <?php if (!empty($success)) : ?>
                <div class="alert-ok"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error) : ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>

            <?php
                if ($page === 'blogs' && in_array($blogMode, ['create', 'edit'], true)) {
                    require __DIR__ . '/dashboard/blogs/form.view.php';
                } elseif ($page === 'pages' && in_array($pageMode, ['create', 'edit'], true)) {
                    require __DIR__ . '/dashboard/pages/form.view.php';
                } elseif ($page === 'content' && in_array($contentMode, ['create', 'edit'], true)) {
                    $contentFormPath = __DIR__ . '/dashboard/content/forms/' . $contentSelectedTypeKey . '.view.php';
                    if (is_file($contentFormPath)) {
                        require $contentFormPath;
                    } else {
                        require __DIR__ . '/dashboard/content/form.view.php';
                    }
                } elseif ($page === 'content') {
                    $contentIndexPath = __DIR__ . '/dashboard/content/indexes/' . $contentSelectedTypeKey . '.view.php';
                    if (is_file($contentIndexPath)) {
                        require $contentIndexPath;
                    } else {
                        require __DIR__ . '/dashboard/content/index.view.php';
                    }
                } else {
                    require __DIR__ . '/dashboard/' . $page . '/index.view.php';
                }
            ?>
        </main>
    </div>

    <div class="editor-media-modal" id="editor-media-modal" aria-hidden="true">
        <div class="editor-media-panel" role="dialog" aria-modal="true" aria-labelledby="editorMediaTitle">
            <div class="row" style="justify-content:space-between; align-items:center;">
                <h3 id="editorMediaTitle" style="margin:0;">Selecteer media</h3>
                <button type="button" class="secondary" id="editor-media-close">Sluiten</button>
            </div>
            <div class="row" style="margin-top:10px;">
                <input type="search" id="editor-media-search" placeholder="Zoek op bestandsnaam...">
                <a href="/dashboard/media" target="_blank">Open volledige mediabibliotheek</a>
            </div>
            <div class="editor-media-upload">
                <div class="row">
                    <input type="file" id="editor-media-upload-file" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                    <select id="editor-media-upload-folder" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                        <option value="0">Geen map</option>
                        <?php foreach (($mediaFolders ?? []) as $folder) : ?>
                            <option value="<?php echo (int)$folder['id']; ?>"><?php echo htmlspecialchars((string)$folder['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <input type="text" id="editor-media-upload-alt" placeholder="Alt-tekst (optioneel)" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>
                    <button type="button" id="editor-media-upload-button" <?php echo !$canPagesWrite ? 'disabled' : ''; ?>>Upload hier</button>
                </div>
                <div class="editor-media-upload-status" id="editor-media-upload-status"></div>
            </div>
            <div class="editor-media-grid" id="editor-media-grid"></div>
        </div>
    </div>

    <script>
        window.FundamentalEditor = (function () {
            var mediaItems = <?php echo json_encode($dashboardEditorMediaItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
            var csrfToken = <?php echo json_encode((string)$csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var modal = document.getElementById('editor-media-modal');
            var closeBtn = document.getElementById('editor-media-close');
            var searchInput = document.getElementById('editor-media-search');
            var grid = document.getElementById('editor-media-grid');
            var uploadInput = document.getElementById('editor-media-upload-file');
            var uploadAltInput = document.getElementById('editor-media-upload-alt');
            var uploadFolderSelect = document.getElementById('editor-media-upload-folder');
            var uploadBtn = document.getElementById('editor-media-upload-button');
            var uploadStatus = document.getElementById('editor-media-upload-status');
            var onPick = null;

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function renderMediaList(filterText) {
                if (!grid) {
                    return;
                }
                var q = String(filterText || '').toLowerCase().trim();
                var html = '';

                mediaItems.forEach(function (item) {
                    var filename = String(item.filename || '');
                    var path = String(item.path || '');
                    var mime = String(item.mime_type || '');
                    var hay = (filename + ' ' + path + ' ' + mime).toLowerCase();
                    if (q && hay.indexOf(q) === -1) {
                        return;
                    }

                    var preview = '';
                    if (mime.indexOf('image/') === 0) {
                        preview = '<img src="' + escapeHtml(path) + '" alt="' + escapeHtml(filename) + '">';
                    }

                    html += '<div class="editor-media-card">'
                        + preview
                        + '<strong>' + escapeHtml(filename || 'Bestand') + '</strong>'
                        + '<div class="small">' + escapeHtml(mime || 'bestand') + '</div>'
                        + '<button type="button" class="secondary js-media-use" data-path="' + escapeHtml(path) + '">Gebruik</button>'
                        + '</div>';
                });

                if (!html) {
                    html = '<div class="small">Geen media gevonden in de huidige set. Upload of open de mediabibliotheek.</div>';
                }
                grid.innerHTML = html;

                grid.querySelectorAll('.js-media-use').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (typeof onPick === 'function') {
                            onPick(String(btn.getAttribute('data-path') || ''));
                        }
                        closeMediaPicker();
                    });
                });
            }

            function openMediaPicker(callback) {
                onPick = callback;
                if (!modal) {
                    return;
                }
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                renderMediaList(searchInput ? searchInput.value : '');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            function closeMediaPicker() {
                if (!modal) {
                    return;
                }
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                onPick = null;
            }

            function setUploadStatus(text, statusClass) {
                if (!uploadStatus) {
                    return;
                }
                uploadStatus.classList.remove('is-error');
                uploadStatus.classList.remove('is-ok');
                if (statusClass) {
                    uploadStatus.classList.add(statusClass);
                }
                uploadStatus.textContent = String(text || '');
            }

            function uploadFromPicker() {
                if (!uploadInput || !uploadInput.files || !uploadInput.files[0]) {
                    setUploadStatus('Selecteer eerst een bestand.', 'is-error');
                    return;
                }

                var data = new FormData();
                data.append('csrf', csrfToken);
                data.append('action', 'media_upload');
                data.append('media_file', uploadInput.files[0]);
                data.append('folder_id', uploadFolderSelect ? String(uploadFolderSelect.value || '0') : '0');
                data.append('alt_text', uploadAltInput ? String(uploadAltInput.value || '') : '');

                setUploadStatus('Uploaden...', '');
                if (uploadBtn) {
                    uploadBtn.disabled = true;
                }

                fetch(window.location.pathname, {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, payload: payload || {} };
                    });
                })
                .then(function (result) {
                    var payload = result.payload || {};
                    if (!result.ok || !payload.ok) {
                        throw new Error(String(payload.error || 'Upload mislukt.'));
                    }

                    var item = payload.item || null;
                    if (!item || !item.path) {
                        throw new Error('Upload gelukt, maar media-item kon niet worden geladen.');
                    }

                    mediaItems.unshift(item);
                    renderMediaList(searchInput ? searchInput.value : '');
                    setUploadStatus('Upload gelukt.', 'is-ok');

                    if (uploadInput) {
                        uploadInput.value = '';
                    }
                    if (uploadAltInput) {
                        uploadAltInput.value = '';
                    }

                    if (typeof onPick === 'function') {
                        onPick(String(item.path || ''));
                        closeMediaPicker();
                    }
                })
                .catch(function (error) {
                    setUploadStatus(String((error && error.message) || 'Upload mislukt.'), 'is-error');
                })
                .finally(function () {
                    if (uploadBtn) {
                        uploadBtn.disabled = false;
                    }
                });
            }

            function insertHtmlAtCursor(editable, html) {
                editable.focus();
                if (document.execCommand) {
                    document.execCommand('insertHTML', false, html);
                    return;
                }
                editable.innerHTML += html;
            }

            function initWysiwyg(textarea) {
                if (!textarea || textarea.dataset.editorInit === '1') {
                    return null;
                }
                textarea.dataset.editorInit = '1';
                textarea.classList.add('fnd-wysiwyg-source');

                var wrapper = document.createElement('div');
                var toolbar = document.createElement('div');
                var editable = document.createElement('div');
                wrapper.className = 'editor-wysiwyg-wrap';
                toolbar.className = 'editor-toolbar';
                editable.className = 'editor-wysiwyg';
                editable.contentEditable = 'true';

                var value = String(textarea.value || '');
                if (/<[a-z][\s\S]*>/i.test(value)) {
                    editable.innerHTML = value;
                } else {
                    editable.textContent = value;
                }

                toolbar.innerHTML = ''
                    + '<button type="button" data-cmd="bold"><strong>B</strong></button>'
                    + '<button type="button" data-cmd="italic"><em>I</em></button>'
                    + '<button type="button" data-wrap="h2">H2</button>'
                    + '<button type="button" data-wrap="p">P</button>'
                    + '<button type="button" data-cmd="insertUnorderedList">Lijst</button>'
                    + '<button type="button" data-link="1">Link</button>'
                    + '<button type="button" data-image="1">Afbeelding</button>';

                toolbar.querySelectorAll('[data-cmd]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        editable.focus();
                        document.execCommand(String(btn.getAttribute('data-cmd')), false, null);
                    });
                });

                toolbar.querySelectorAll('[data-wrap]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var tag = String(btn.getAttribute('data-wrap') || 'p');
                        var selection = window.getSelection();
                        var selected = selection ? selection.toString() : '';
                        if (!selected) {
                            selected = 'Tekst';
                        }
                        insertHtmlAtCursor(editable, '<' + tag + '>' + escapeHtml(selected) + '</' + tag + '>');
                    });
                });

                toolbar.querySelectorAll('[data-link]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var url = window.prompt('URL invoeren', '/');
                        if (!url) {
                            return;
                        }
                        editable.focus();
                        document.execCommand('createLink', false, url);
                    });
                });

                toolbar.querySelectorAll('[data-image]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        openMediaPicker(function (path) {
                            if (!path) {
                                return;
                            }
                            insertHtmlAtCursor(editable, '<img src="' + escapeHtml(path) + '" alt="">');
                        });
                    });
                });

                textarea.style.display = 'none';
                textarea.parentNode.insertBefore(wrapper, textarea);
                wrapper.appendChild(toolbar);
                wrapper.appendChild(editable);
                wrapper.appendChild(textarea);

                textarea.__fndSync = function () {
                    textarea.value = editable.innerHTML;
                };

                return { textarea: textarea, editable: editable };
            }

            function syncForm(form) {
                if (!form) {
                    return;
                }
                form.querySelectorAll('.fnd-wysiwyg-source').forEach(function (textarea) {
                    if (typeof textarea.__fndSync === 'function') {
                        textarea.__fndSync();
                    }
                });
            }

            function bindMediaButton(button) {
                if (!button || button.dataset.mediaBound === '1') {
                    return;
                }
                button.dataset.mediaBound = '1';
                button.addEventListener('click', function () {
                    var targetId = String(button.getAttribute('data-target') || '');
                    var target = targetId ? document.getElementById(targetId) : null;
                    if (!target) {
                        return;
                    }
                    openMediaPicker(function (path) {
                        target.value = path;
                        target.dispatchEvent(new Event('change'));
                    });
                });
            }

            function bindImagePreview(previewWrap) {
                if (!previewWrap || previewWrap.dataset.previewBound === '1') {
                    return;
                }

                var inputId = String(previewWrap.getAttribute('data-input-id') || '');
                var imgId = String(previewWrap.getAttribute('data-img-id') || '');
                var input = inputId ? document.getElementById(inputId) : null;
                var img = imgId ? document.getElementById(imgId) : null;

                if (!input || !img) {
                    return;
                }

                previewWrap.dataset.previewBound = '1';

                function syncPreview() {
                    var value = String(input.value || '').trim();
                    if (!value) {
                        img.src = '';
                        previewWrap.classList.add('is-empty');
                        return;
                    }

                    img.src = value;
                    previewWrap.classList.remove('is-empty');
                }

                input.addEventListener('input', syncPreview);
                input.addEventListener('change', syncPreview);
                syncPreview();
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeMediaPicker);
            }
            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeMediaPicker();
                    }
                });
            }
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    renderMediaList(searchInput.value || '');
                });
            }
            if (uploadBtn) {
                uploadBtn.addEventListener('click', uploadFromPicker);
            }
            if (uploadInput) {
                uploadInput.addEventListener('change', function () {
                    setUploadStatus('', '');
                });
            }

            return {
                openMediaPicker: openMediaPicker,
                closeMediaPicker: closeMediaPicker,
                initWysiwyg: initWysiwyg,
                syncForm: syncForm,
                bindMediaButton: bindMediaButton,
                bindImagePreview: bindImagePreview,
                uploadFromPicker: uploadFromPicker
            };
        })();
    </script>
</body>
</html>