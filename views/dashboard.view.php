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
        if (!hash_equals((string)$csrfToken, (string)$postedCsrf)) {
            $errors[] = 'Ongeldige aanvraag (CSRF).';
        } else {
            $action = $_POST['action'] ?? '';

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

            if ($action === 'blog_create') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om blogs te beheren.';
                } else {
                    $created = $blogModel->create([
                        'title' => trim($_POST['title'] ?? ''),
                        'slug' => trim($_POST['slug'] ?? ''),
                        'featured_image' => trim($_POST['featured_image'] ?? ''),
                        'intro' => trim($_POST['intro'] ?? ''),
                        'category' => trim($_POST['category'] ?? ''),
                        'tags' => trim($_POST['tags'] ?? ''),
                        'meta_title' => trim($_POST['meta_title'] ?? ''),
                        'meta_description' => trim($_POST['meta_description'] ?? ''),
                        'og_image' => trim($_POST['og_image'] ?? ''),
                        'excerpt' => trim($_POST['excerpt'] ?? ''),
                        'content' => trim($_POST['content'] ?? ''),
                        'status' => trim($_POST['status'] ?? 'draft'),
                        'scheduled_at' => trim($_POST['scheduled_at'] ?? ''),
                    ], $currentUserId);

                    if ($created) {
                        header('Location: /dashboard/blogs?ok=blog_created');
                        exit;
                    }

                    $errors[] = $blogModel->getLastError() ?: 'Blogpost kon niet worden aangemaakt.';
                }
            }

            if ($action === 'blog_update') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om blogs te beheren.';
                } else {
                    $blogId = (int)($_POST['id'] ?? 0);
                    if ($blogId <= 0) {
                        $errors[] = 'Ongeldige blogpost.';
                    } elseif ($blogModel->update($blogId, [
                        'title' => trim($_POST['title'] ?? ''),
                        'slug' => trim($_POST['slug'] ?? ''),
                        'featured_image' => trim($_POST['featured_image'] ?? ''),
                        'intro' => trim($_POST['intro'] ?? ''),
                        'category' => trim($_POST['category'] ?? ''),
                        'tags' => trim($_POST['tags'] ?? ''),
                        'meta_title' => trim($_POST['meta_title'] ?? ''),
                        'meta_description' => trim($_POST['meta_description'] ?? ''),
                        'og_image' => trim($_POST['og_image'] ?? ''),
                        'excerpt' => trim($_POST['excerpt'] ?? ''),
                        'content' => trim($_POST['content'] ?? ''),
                        'status' => trim($_POST['status'] ?? 'draft'),
                        'scheduled_at' => trim($_POST['scheduled_at'] ?? ''),
                    ], $currentUserId)) {
                        header('Location: /dashboard/blogs?ok=blog_updated');
                        exit;
                    } else {
                        $errors[] = $blogModel->getLastError() ?: 'Blogpost kon niet worden bijgewerkt.';
                    }
                }
            }

            if ($action === 'blog_duplicate') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om blogs te beheren.';
                } else {
                    $blogId = (int)($_POST['id'] ?? 0);
                    if ($blogId <= 0) {
                        $errors[] = 'Ongeldige blogpost.';
                    } elseif ($blogModel->duplicate($blogId, $currentUserId)) {
                        header('Location: /dashboard/blogs?ok=blog_duplicated');
                        exit;
                    } else {
                        $errors[] = 'Concept kon niet worden gedupliceerd.';
                    }
                }
            }

            if ($action === 'blog_bulk') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om blogs te beheren.';
                } else {
                    $bulkAction = trim($_POST['bulk_action'] ?? '');
                    $selected = $_POST['selected_ids'] ?? [];
                    $selectedIds = is_array($selected) ? $selected : [];

                    if ($bulkAction === '' || empty($selectedIds)) {
                        $errors[] = 'Selecteer items en een bulk-actie.';
                    } else {
                        $affected = 0;
                        if ($bulkAction === 'delete') {
                            $affected = $blogModel->bulkDelete($selectedIds);
                        } elseif (in_array($bulkAction, ['draft', 'published', 'scheduled', 'archived'], true)) {
                            $affected = $blogModel->bulkUpdateStatus($selectedIds, $bulkAction, $currentUserId);
                        }

                        if ($affected > 0) {
                            header('Location: /dashboard/blogs?ok=blog_bulk_updated');
                            exit;
                        }

                        $errors[] = 'Bulk-actie kon niet worden uitgevoerd.';
                    }
                }
            }

            if ($action === 'blog_inline_status') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om blogs te beheren.';
                } else {
                    $blogId = (int)($_POST['id'] ?? 0);
                    $status = trim($_POST['status'] ?? 'draft');
                    if ($blogId <= 0) {
                        $errors[] = 'Ongeldige blogpost.';
                    } elseif ($blogModel->bulkUpdateStatus([$blogId], $status, $currentUserId) > 0) {
                        header('Location: /dashboard/blogs?ok=blog_updated');
                        exit;
                    } else {
                        $errors[] = 'Status kon niet inline worden bijgewerkt.';
                    }
                }
            }

            if ($action === 'blog_restore_revision') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om blogs te beheren.';
                } else {
                    $revisionId = (int)($_POST['revision_id'] ?? 0);
                    $blogId = (int)($_POST['blog_id'] ?? 0);
                    if ($revisionId <= 0 || $blogId <= 0) {
                        $errors[] = 'Ongeldige revisie.';
                    } elseif ($blogModel->restoreRevision($revisionId, $currentUserId)) {
                        header('Location: /dashboard/blogs/edit/' . $blogId . '?ok=blog_revision_restored');
                        exit;
                    } else {
                        $errors[] = $blogModel->getLastError() ?: 'Revisie kon niet worden teruggezet.';
                    }
                }
            }

            if ($action === 'blog_autosave') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om autosave uit te voeren.';
                } else {
                    $blogId = (int)($_POST['id'] ?? 0);
                    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
                    if ($blogId <= 0) {
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            http_response_code(400);
                            echo json_encode(['ok' => false, 'error' => 'Autosave werkt alleen voor bestaande posts.']);
                            exit;
                        }
                        $errors[] = 'Autosave werkt alleen voor bestaande posts.';
                    } else {
                        $ok = $blogModel->saveAutosave($blogId, $currentUserId, [
                            'title' => trim($_POST['title'] ?? ''),
                            'slug' => trim($_POST['slug'] ?? ''),
                            'featured_image' => trim($_POST['featured_image'] ?? ''),
                            'intro' => trim($_POST['intro'] ?? ''),
                            'category' => trim($_POST['category'] ?? ''),
                            'tags' => trim($_POST['tags'] ?? ''),
                            'meta_title' => trim($_POST['meta_title'] ?? ''),
                            'meta_description' => trim($_POST['meta_description'] ?? ''),
                            'og_image' => trim($_POST['og_image'] ?? ''),
                            'excerpt' => trim($_POST['excerpt'] ?? ''),
                            'content' => trim($_POST['content'] ?? ''),
                            'status' => trim($_POST['status'] ?? 'draft'),
                            'scheduled_at' => trim($_POST['scheduled_at'] ?? ''),
                        ]);
                        if ($ok) {
                            if ($isAjax) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode(['ok' => true, 'saved_at' => date('Y-m-d H:i:s')]);
                                exit;
                            }
                            header('Location: /dashboard/blogs/edit/' . $blogId . '?ok=blog_autosaved');
                            exit;
                        }

                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            http_response_code(500);
                            echo json_encode(['ok' => false, 'error' => ($blogModel->getLastError() ?: 'Autosave kon niet worden opgeslagen.')]);
                            exit;
                        }

                        $errors[] = $blogModel->getLastError() ?: 'Autosave kon niet worden opgeslagen.';
                    }
                }
            }

            if ($action === 'blog_preview_token') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om preview links te maken.';
                } else {
                    $blogId = (int)($_POST['blog_id'] ?? 0);
                    $role = trim($_POST['required_role'] ?? 'all');
                    $ttlHours = max(1, min((int)($_POST['ttl_hours'] ?? 24), 168));
                    $preview = $blogModel->createPreviewToken($blogId, $role, $currentUserId, $ttlHours);
                    if ($preview) {
                        header('Location: /dashboard/blogs/edit/' . $blogId . '?ok=blog_preview_created&preview_token=' . urlencode($preview['token']));
                        exit;
                    }
                    $errors[] = 'Preview-link kon niet worden aangemaakt.';
                }
            }

            if ($action === 'blog_delete') {
                if (!$canBlogWrite) {
                    $errors[] = 'Geen rechten om blogs te beheren.';
                } else {
                    $blogId = (int)($_POST['id'] ?? 0);
                    if ($blogId <= 0) {
                        $errors[] = 'Ongeldige blogpost.';
                    } elseif ($blogModel->delete($blogId)) {
                        header('Location: /dashboard/blogs?ok=blog_deleted');
                        exit;
                    } else {
                        $errors[] = 'Blogpost kon niet worden verwijderd.';
                    }
                }
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
                        header('Location: /dashboard/media?ok=media_folder_created');
                        exit;
                    }
                    $errors[] = $contentModel->getLastError() ?: 'Map kon niet worden aangemaakt.';
                }
            }

            if ($action === 'media_upload') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om media te uploaden.';
                } else {
                    $file = $_FILES['media_file'] ?? null;
                    if (!$file) {
                        $errors[] = 'Geen bestand geüpload.';
                    } else {
                        $uploaded = $contentModel->uploadMedia(
                            $file,
                            (int)($_POST['folder_id'] ?? 0),
                            trim($_POST['alt_text'] ?? ''),
                            $currentUserId
                        );

                        if ($uploaded) {
                            header('Location: /dashboard/media?ok=media_uploaded');
                            exit;
                        }
                        $errors[] = $contentModel->getLastError() ?: 'Upload mislukt.';
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
                        header('Location: /dashboard/pages?ok=menu_deleted');
                        exit;
                    } else {
                        $errors[] = 'Menu-item kon niet worden verwijderd.';
                    }
                }
            }

            if ($action === 'page_create') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om pagina\'s te beheren.';
                } else {
                    $created = $pageModel->create([
                        'title' => trim($_POST['title'] ?? ''),
                        'slug' => trim($_POST['slug'] ?? ''),
                        'template' => trim($_POST['template'] ?? 'default'),
                        'page_type' => trim($_POST['page_type'] ?? 'basic_page'),
                        'template_payload_json' => json_encode([
                            'hero' => [
                                'title' => trim($_POST['hero_title'] ?? ''),
                                'subtitle' => trim($_POST['hero_subtitle'] ?? ''),
                                'cta_label' => trim($_POST['hero_cta_label'] ?? ''),
                                'cta_url' => trim($_POST['hero_cta_url'] ?? ''),
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'excerpt' => trim($_POST['excerpt'] ?? ''),
                        'content' => trim($_POST['content'] ?? ''),
                        'meta_title' => trim($_POST['meta_title'] ?? ''),
                        'meta_description' => trim($_POST['meta_description'] ?? ''),
                        'status' => trim($_POST['status'] ?? 'draft'),
                        'published_at' => trim($_POST['published_at'] ?? ''),
                    ], $currentUserId);

                    if ($created) {
                        header('Location: /dashboard/pages?ok=page_created');
                        exit;
                    }

                    $errors[] = $pageModel->getLastError() ?: 'Pagina kon niet worden aangemaakt.';
                }
            }

            if ($action === 'page_update') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om pagina\'s te beheren.';
                } else {
                    $pageId = (int)($_POST['id'] ?? 0);
                    if ($pageId <= 0) {
                        $errors[] = 'Ongeldige pagina.';
                    } elseif ($pageModel->update($pageId, [
                        'title' => trim($_POST['title'] ?? ''),
                        'slug' => trim($_POST['slug'] ?? ''),
                        'template' => trim($_POST['template'] ?? 'default'),
                        'page_type' => trim($_POST['page_type'] ?? 'basic_page'),
                        'template_payload_json' => json_encode([
                            'hero' => [
                                'title' => trim($_POST['hero_title'] ?? ''),
                                'subtitle' => trim($_POST['hero_subtitle'] ?? ''),
                                'cta_label' => trim($_POST['hero_cta_label'] ?? ''),
                                'cta_url' => trim($_POST['hero_cta_url'] ?? ''),
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'excerpt' => trim($_POST['excerpt'] ?? ''),
                        'content' => trim($_POST['content'] ?? ''),
                        'meta_title' => trim($_POST['meta_title'] ?? ''),
                        'meta_description' => trim($_POST['meta_description'] ?? ''),
                        'status' => trim($_POST['status'] ?? 'draft'),
                        'published_at' => trim($_POST['published_at'] ?? ''),
                    ], $currentUserId)) {
                        header('Location: /dashboard/pages?ok=page_updated');
                        exit;
                    } else {
                        $errors[] = $pageModel->getLastError() ?: 'Pagina kon niet worden bijgewerkt.';
                    }
                }
            }

            if ($action === 'page_delete') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om pagina\'s te beheren.';
                } else {
                    $pageId = (int)($_POST['id'] ?? 0);
                    if ($pageId <= 0) {
                        $errors[] = 'Ongeldige pagina.';
                    } elseif ($pageModel->delete($pageId)) {
                        header('Location: /dashboard/pages?ok=page_deleted');
                        exit;
                    } else {
                        $errors[] = $pageModel->getLastError() ?: 'Pagina kon niet worden verwijderd.';
                    }
                }
            }

            if ($action === 'content_create') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om content te beheren.';
                } else {
                    $contentType = trim((string)($_POST['content_type'] ?? ''));
                    $typeDefinition = $contentTypeRegistry->getByKey($contentType);

                    if (!$typeDefinition) {
                        $errors[] = 'Onbekend content type.';
                    } else {
                        $payload = [];
                        $fields = isset($typeDefinition['fields']) && is_array($typeDefinition['fields'])
                            ? $typeDefinition['fields']
                            : [];
                        foreach ($fields as $field) {
                            $fieldName = trim((string)($field['name'] ?? ''));
                            if ($fieldName === '') {
                                continue;
                            }
                            $payload[$fieldName] = trim((string)($_POST['payload_' . $fieldName] ?? ''));
                        }

                        $created = $contentItemModel->create([
                            'type' => $contentType,
                            'title' => trim((string)($_POST['title'] ?? '')),
                            'slug' => trim((string)($_POST['slug'] ?? '')),
                            'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
                            'content' => trim((string)($_POST['content'] ?? '')),
                            'featured_image' => trim((string)($_POST['featured_image'] ?? '')),
                            'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
                            'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
                            'status' => trim((string)($_POST['status'] ?? 'draft')),
                            'published_at' => trim((string)($_POST['published_at'] ?? '')),
                            'starts_at' => trim((string)($_POST['starts_at'] ?? '')),
                            'ends_at' => trim((string)($_POST['ends_at'] ?? '')),
                            'payload_json' => $payload,
                        ], $currentUserId);

                        if ($created) {
                            $redirectSlug = (string)($typeDefinition['slug'] ?? $contentType);
                            header('Location: /dashboard/content/' . rawurlencode($redirectSlug) . '?ok=content_created');
                            exit;
                        }

                        $errors[] = $contentItemModel->getLastError() ?: 'Content item kon niet worden aangemaakt.';
                    }
                }
            }

            if ($action === 'content_update') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om content te beheren.';
                } else {
                    $itemId = (int)($_POST['id'] ?? 0);
                    $contentType = trim((string)($_POST['content_type'] ?? ''));
                    $typeDefinition = $contentTypeRegistry->getByKey($contentType);

                    if ($itemId <= 0) {
                        $errors[] = 'Ongeldig content item.';
                    } elseif (!$typeDefinition) {
                        $errors[] = 'Onbekend content type.';
                    } else {
                        $payload = [];
                        $fields = isset($typeDefinition['fields']) && is_array($typeDefinition['fields'])
                            ? $typeDefinition['fields']
                            : [];
                        foreach ($fields as $field) {
                            $fieldName = trim((string)($field['name'] ?? ''));
                            if ($fieldName === '') {
                                continue;
                            }
                            $payload[$fieldName] = trim((string)($_POST['payload_' . $fieldName] ?? ''));
                        }

                        $updated = $contentItemModel->update($itemId, [
                            'type' => $contentType,
                            'title' => trim((string)($_POST['title'] ?? '')),
                            'slug' => trim((string)($_POST['slug'] ?? '')),
                            'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
                            'content' => trim((string)($_POST['content'] ?? '')),
                            'featured_image' => trim((string)($_POST['featured_image'] ?? '')),
                            'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
                            'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
                            'status' => trim((string)($_POST['status'] ?? 'draft')),
                            'published_at' => trim((string)($_POST['published_at'] ?? '')),
                            'starts_at' => trim((string)($_POST['starts_at'] ?? '')),
                            'ends_at' => trim((string)($_POST['ends_at'] ?? '')),
                            'payload_json' => $payload,
                        ], $currentUserId);

                        if ($updated) {
                            $redirectSlug = (string)($typeDefinition['slug'] ?? $contentType);
                            header('Location: /dashboard/content/' . rawurlencode($redirectSlug) . '?ok=content_updated');
                            exit;
                        }

                        $errors[] = $contentItemModel->getLastError() ?: 'Content item kon niet worden bijgewerkt.';
                    }
                }
            }

            if ($action === 'content_delete') {
                if (!$canPagesWrite) {
                    $errors[] = 'Geen rechten om content te beheren.';
                } else {
                    $itemId = (int)($_POST['id'] ?? 0);
                    $contentType = trim((string)($_POST['content_type'] ?? ''));
                    $typeDefinition = $contentTypeRegistry->getByKey($contentType);

                    if ($itemId <= 0) {
                        $errors[] = 'Ongeldig content item.';
                    } elseif ($contentItemModel->delete($itemId)) {
                        $redirectSlug = (string)($typeDefinition['slug'] ?? 'services');
                        header('Location: /dashboard/content/' . rawurlencode($redirectSlug) . '?ok=content_deleted');
                        exit;
                    } else {
                        $errors[] = $contentItemModel->getLastError() ?: 'Content item kon niet worden verwijderd.';
                    }
                }
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

    $users = ($usersSearch !== '') ? $account->get_all($usersSearch) : $account->get_all();
    $blogList = $blogModel->listAllAdvanced([
        'search' => $blogSearch,
        'status' => $blogStatusFilter,
        'category' => $blogCategoryFilter,
        'sort' => $blogSort,
        'page' => $blogPage,
        'per_page' => $blogPerPage,
    ]);
    $blogPosts = $blogList['items'];
    $blogPagesTotal = $blogList['pages'];
    $blogTotalItems = $blogList['total'];
    $blogCategories = $blogModel->getDistinctCategories(200);

    $mediaFolders = $contentModel->listMediaFolders();
    $mediaList = $contentModel->listMediaItems($mediaSearch, $mediaFolderFilter, $mediaPage, $mediaPerPage, $mediaSort);
    $mediaItems = $mediaList['items'];
    $mediaPagesTotal = $mediaList['pages'];
    $mediaTotalItems = $mediaList['total'];
    $menuItems = $contentModel->listMenuItems('main');
    $menuTreeItems = $contentModel->getMenuTree('main');

    $pageSearch = trim($_GET['page_q'] ?? '');
    $pageStatus = trim($_GET['page_status'] ?? '');
    $pagePageNumber = max(1, (int)($_GET['page_page'] ?? 1));
    $pagePerPage = max(5, min((int)($_GET['page_per_page'] ?? 10), 50));
    $pagesList = $pageModel->listAll($pageSearch, $pageStatus, $pagePageNumber, $pagePerPage);
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

    $contentManagedList = $contentItemModel->listAll(
        $contentSelectedTypeKey,
        $contentSearch,
        $contentStatus,
        $contentManagedPage,
        $contentManagedPerPage
    );

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
                <a class="<?php echo $page === 'content' ? 'active' : ''; ?>" href="/dashboard/content/services">Content</a>
                <?php foreach (($contentTypesList ?? []) as $sidebarTypeKey => $sidebarTypeDefinition) : ?>
                    <?php
                        $sidebarTypeSlug = (string)($sidebarTypeDefinition['slug'] ?? $sidebarTypeKey);
                        $sidebarTypeActive = $page === 'content' && (string)($contentSelectedTypeKey ?? '') === (string)$sidebarTypeKey;
                    ?>
                    <a class="sub-item <?php echo $sidebarTypeActive ? 'active' : ''; ?>" href="/dashboard/content/<?php echo htmlspecialchars($sidebarTypeSlug); ?>">
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
</body>
</html>