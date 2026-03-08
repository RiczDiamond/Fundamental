<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class WebController
{
    private const CSRF_TOKEN_ID = 'fundamental_form';

    public function __construct(
        private readonly \PDO $link,
        private readonly \Account $account,
        private readonly \Auth $auth,
        private readonly \Session $session,
        private readonly \Logging $logger,
        private readonly FilesystemAdapter $appCache,
        private readonly object $loginLimiterFactory,
        private readonly string $projectRoot,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function dispatch(array $url): void
    {
        $csrfToken = $this->getCsrfTokenValue();

        if (($url[0] ?? '') === 'uploads') {
            $this->serveUpload($url);
            return;
        }

        $this->logger->handleRequest($_SESSION['user_id'] ?? null);

        $segment = $url[0] ?? 'home';
        if ($segment === '' || $segment === 'home') {
            $this->showHome();
            return;
        }

        if ($segment === 'login') {
            $this->handleLogin();
            return;
        }

        if ($segment === 'logout') {
            $this->render('logout.view.php', ['csrfToken' => $csrfToken]);
            return;
        }

        if ($segment === 'register') {
            $this->render('register.view.php', ['csrfToken' => $csrfToken]);
            return;
        }

        if ($segment === 'blog') {
            $this->handleBlog($url);
            return;
        }

        if ($segment === 'sitemap.xml') {
            $this->handleSitemap();
            return;
        }

        if ($segment === 'dashboard') {
            $this->handleDashboard($url);
            return;
        }

        $this->handleContentOrPage($url);
    }

    private function showHome(): void
    {
        $pageModel = new \Page($this->link);
        $pageData = $pageModel->findPublishedBySlug('home');

        if ($pageData) {
            $this->render('pages/show.view.php', [
                'pageData' => $pageData,
                'csrfToken' => $this->getCsrfTokenValue(),
            ]);
            return;
        }

        $blog = new \Blog($this->link);
        $blog->publishScheduled();
        $categoryFilter = trim($_GET['category'] ?? '');
        $posts = $blog->listPublished(50, $categoryFilter !== '' ? $categoryFilter : null);
        $categories = $this->cachedValue('blog.categories.overview.20', static function () use ($blog) {
            return $blog->getCategoryOverview(20);
        }, 300);

        $this->render('blog/list.view.php', [
            'posts' => $posts,
            'categories' => $categories,
        ]);
    }

    private function handleLogin(): void
    {
        if ($this->session->has('user_id')) {
            header('Location: /dashboard');
            exit;
        }

        $error = '';
        $email = '';
        $rememberChecked = false;
        $csrfToken = $this->getCsrfTokenValue();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $postedCsrf = (string)($_POST['csrf'] ?? '');
            if (!$this->isValidCsrfSubmission($postedCsrf)) {
                $this->abort(400, 'Ongeldige aanvraag (CSRF).');
            }

            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $rememberChecked = isset($_POST['remember']);

            $limitKey = ($email !== '' ? $email : 'unknown') . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $limiter = $this->loginLimiterFactory->create($limitKey);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $retrySeconds = max(1, (int)ceil(($limit->getRetryAfter()?->getTimestamp() ?? time()) - time()));
                $error = 'Te veel loginpogingen. Probeer opnieuw over ' . $retrySeconds . ' seconden.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                $error = 'Vul alle velden correct in';
            } elseif ($this->auth->login($email, $password, $rememberChecked)) {
                header('Location: /dashboard');
                exit;
            } else {
                $error = 'Login mislukt, controleer je gegevens';
            }
        }

        $this->render('login.view.php', [
            'csrfToken' => $csrfToken,
            'error' => $error,
            'email' => $email,
            'rememberChecked' => $rememberChecked,
        ]);
    }

    private function handleBlog(array $url): void
    {
        $blog = new \Blog($this->link);
        $blog->publishScheduled();
        $csrfToken = $this->getCsrfTokenValue();

        if (($url[1] ?? '') === 'preview') {
            $token = urldecode((string)($url[2] ?? ''));
            $preview = $blog->findPreviewByToken($token);

            if (!$preview) {
                $this->abort(404, '404 - Preview niet gevonden of verlopen');
            }

            $requiredRole = trim((string)($preview['required_role'] ?? ''));
            if ($requiredRole !== '') {
                if (!$this->session->has('user_id')) {
                    header('Location: /login');
                    exit;
                }

                $viewer = $this->account->get($this->session->get('user_id'));
                $viewerRole = $viewer['role'] ?? 'user';
                if ($viewerRole !== 'admin' && $viewerRole !== $requiredRole) {
                    $this->abort(403, '403 - Geen toegang tot deze preview');
                }
            }

            $post = $preview;
            $isPreviewMode = true;
            $previewToken = $token;
            $readMinutes = $blog->estimateReadMinutes($post['content'] ?? '');
            $previousPost = null;
            $nextPost = null;
            $relatedPosts = [];
            $comments = [];
            $likedByCurrent = false;
            $categories = $this->cachedValue('blog.categories.overview.20', static function () use ($blog) {
                return $blog->getCategoryOverview(20);
            }, 300);

            $this->render('blog/show.view.php', compact(
                'post',
                'isPreviewMode',
                'previewToken',
                'readMinutes',
                'previousPost',
                'nextPost',
                'relatedPosts',
                'comments',
                'likedByCurrent',
                'categories',
                'csrfToken'
            ));
            return;
        }

        if (!empty($url[1])) {
            $slug = urldecode($url[1]);
            $post = $blog->findPublishedBySlug($slug);

            if (!$post && $this->session->has('user_id') && $this->auth->perm_check(null, 'blog.write')) {
                $post = $blog->findBySlug($slug);
            }

            if (!$post) {
                $this->abort(404, '404 - Blogpost niet gevonden');
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $postedCsrf = $_POST['csrf'] ?? '';
                if (!$this->isValidCsrfSubmission((string)$postedCsrf)) {
                    $this->abort(400, 'Ongeldige aanvraag (CSRF).');
                }

                $action = $_POST['action'] ?? '';
                if ($action === 'blog_like') {
                    $sessionKey = session_id();
                    $userId = $this->session->has('user_id') ? $this->session->get('user_id') : null;
                    $blog->addLike($post['id'], $sessionKey, $userId);
                    header('Location: ' . $post['permalink'] . '#engagement');
                    exit;
                }

                if ($action === 'blog_comment') {
                    $authorName = trim($_POST['author_name'] ?? '');
                    $authorEmail = trim($_POST['author_email'] ?? '');
                    $comment = trim($_POST['comment'] ?? '');
                    $userId = $this->session->has('user_id') ? $this->session->get('user_id') : null;

                    if ($userId) {
                        $profile = $this->account->get($userId);
                        if (!empty($profile['display_name'])) {
                            $authorName = $profile['display_name'];
                        } elseif (!empty($profile['username'])) {
                            $authorName = $profile['username'];
                        }
                        if (!empty($profile['email'])) {
                            $authorEmail = $profile['email'];
                        }
                    }

                    $blog->addComment($post['id'], $authorName, $authorEmail, $comment, $userId);
                    header('Location: ' . $post['permalink'] . '#comments');
                    exit;
                }
            }

            $blog->incrementViewCount($post['id']);
            $readMinutes = $blog->estimateReadMinutes($post['content'] ?? '');
            $previousPost = $blog->getPreviousPost($post);
            $nextPost = $blog->getNextPost($post);
            $relatedPosts = $blog->getRelatedPosts($post, 3);
            $comments = $blog->getComments($post['id'], 200);
            $likedByCurrent = $blog->hasLiked($post['id'], session_id());
            $categories = $this->cachedValue('blog.categories.overview.20', static function () use ($blog) {
                return $blog->getCategoryOverview(20);
            }, 300);

            $this->render('blog/show.view.php', compact(
                'post',
                'readMinutes',
                'previousPost',
                'nextPost',
                'relatedPosts',
                'comments',
                'likedByCurrent',
                'categories',
                'csrfToken'
            ));
            return;
        }

        $categoryFilter = trim($_GET['category'] ?? '');
        $posts = $blog->listPublished(50, $categoryFilter !== '' ? $categoryFilter : null);
        $categories = $this->cachedValue('blog.categories.overview.20', static function () use ($blog) {
            return $blog->getCategoryOverview(20);
        }, 300);

        $this->render('blog/list.view.php', [
            'posts' => $posts,
            'categories' => $categories,
            'csrfToken' => $csrfToken,
        ]);
    }

    private function handleDashboard(array $url): void
    {
        if (!$this->session->has('user_id')) {
            header('Location: /login');
            exit;
        }

        $csrfToken = $this->getCsrfTokenValue();

        $allowedDashboardPages = ['overview', 'users', 'blogs', 'pages', 'content', 'media', 'profile'];
        $dashboardPage = $url[1] ?? 'overview';
        $dashboardSubPage = $url[2] ?? null;
        $dashboardSubId = $url[3] ?? null;
        $dashboardSubExtra = $url[4] ?? null;
        $dashboardContentTypeSlug = null;
        $dashboardContentAction = null;
        $dashboardContentEditId = null;
        $isContentAliasRoute = false;

        if (!in_array($dashboardPage, $allowedDashboardPages, true)) {
            $typeRegistry = new \ContentTypeRegistry();
            if ($typeRegistry->isValidSlug((string)$dashboardPage)) {
                // Support WP-like routes: /dashboard/{content-type}/{create|edit}/{id?}
                $dashboardContentTypeSlug = (string)$dashboardPage;
                $dashboardPage = 'content';
                $dashboardSubPage = $url[2] ?? null;
                $dashboardSubId = $url[3] ?? null;
                $dashboardSubExtra = $url[4] ?? null;
                $isContentAliasRoute = true;
            } else {
                $this->abort(404, '404 - Dashboard pagina niet gevonden');
            }
        }

        if ($dashboardPage === 'blogs' && !empty($dashboardSubPage)) {
            $allowedBlogSubPages = ['create', 'edit'];
            if (!in_array($dashboardSubPage, $allowedBlogSubPages, true)) {
                $this->abort(404, '404 - Blogs pagina niet gevonden');
            }
            if ($dashboardSubPage === 'edit' && (!is_numeric($dashboardSubId) || (int)$dashboardSubId <= 0)) {
                $this->abort(404, '404 - Blogpost niet gevonden');
            }
        }

        if ($dashboardPage === 'pages' && !empty($dashboardSubPage)) {
            $allowedPageSubPages = ['create', 'edit'];
            if (!in_array($dashboardSubPage, $allowedPageSubPages, true)) {
                $this->abort(404, '404 - Pagina beheer niet gevonden');
            }
            if ($dashboardSubPage === 'edit' && (!is_numeric($dashboardSubId) || (int)$dashboardSubId <= 0)) {
                $this->abort(404, '404 - Pagina niet gevonden');
            }
        }

        if ($dashboardPage === 'content') {
            $typeRegistry = new \ContentTypeRegistry();

            $candidateType = trim((string)($dashboardContentTypeSlug ?? ''));
            if ($candidateType === '') {
                $candidateType = trim((string)$dashboardSubPage);
            }
            if ($candidateType === '') {
                $candidateType = 'services';
            }

            if (!$typeRegistry->isValidSlug($candidateType)) {
                $this->abort(404, '404 - Content type niet gevonden');
            }

            $dashboardContentTypeSlug = $candidateType;
            $dashboardContentAction = strtolower(trim((string)($isContentAliasRoute ? $dashboardSubPage : $dashboardSubId)));
            if ($dashboardContentAction !== '' && !in_array($dashboardContentAction, ['create', 'edit'], true)) {
                $this->abort(404, '404 - Content beheer niet gevonden');
            }

            if ($dashboardContentAction === 'edit') {
                $editIdCandidate = $isContentAliasRoute ? $dashboardSubId : $dashboardSubExtra;
                if (!is_numeric($editIdCandidate) || (int)$editIdCandidate <= 0) {
                    $this->abort(404, '404 - Content item niet gevonden');
                }
                $dashboardContentEditId = (int)$editIdCandidate;
            }
        }

        $this->render('dashboard.view.php', compact(
            'dashboardPage',
            'dashboardSubPage',
            'dashboardSubId',
            'dashboardSubExtra',
            'dashboardContentTypeSlug',
            'dashboardContentAction',
            'dashboardContentEditId',
            'csrfToken'
        ));
    }

    private function handleContentOrPage(array $url): void
    {
        $csrfToken = $this->getCsrfTokenValue();
        $contentTypeRegistry = new \ContentTypeRegistry();
        $contentTypeHit = $contentTypeRegistry->getBySlug((string)($url[0] ?? ''));
        if ($contentTypeHit) {
            $contentTypeKey = (string)$contentTypeHit['key'];
            $contentTypeDefinition = (array)$contentTypeHit['definition'];
            $contentModel = new \ContentItem($this->link);

            if (!empty($url[1])) {
                $contentItem = $contentModel->findPublishedByTypeAndSlug($contentTypeKey, (string)$url[1]);
                if (!$contentItem) {
                    $this->abort(404, '404 - Content item niet gevonden');
                }

                $contentShowPath = $this->projectRoot . '/views/content/types/' . $contentTypeKey . '.show.view.php';
                if (is_file($contentShowPath)) {
                    $this->renderAbsolute($contentShowPath, compact('contentTypeKey', 'contentTypeDefinition', 'contentItem'));
                } else {
                    $this->render('content/show.view.php', compact('contentTypeKey', 'contentTypeDefinition', 'contentItem'));
                }
                exit;
            }

            $contentItems = $contentModel->listPublishedByType($contentTypeKey, 100);
            $contentListPath = $this->projectRoot . '/views/content/types/' . $contentTypeKey . '.list.view.php';
            if (is_file($contentListPath)) {
                $this->renderAbsolute($contentListPath, compact('contentTypeKey', 'contentTypeDefinition', 'contentItems'));
            } else {
                $this->render('content/list.view.php', compact('contentTypeKey', 'contentTypeDefinition', 'contentItems'));
            }
            exit;
        }

        $pageModel = new \Page($this->link);
        $slug = trim(urldecode((string)($url[0] ?? '')), '/');
        $pageData = $pageModel->findPublishedBySlug($slug);

        if ($pageData) {
            $this->render('pages/show.view.php', [
                'pageData' => $pageData,
                'csrfToken' => $csrfToken,
            ]);
            exit;
        }

        $this->abort(404, '404 - Pagina niet gevonden');
    }

    private function serveUpload(array $url): void
    {
        $relativePath = ltrim(implode('/', array_slice($url, 1)), '/');
        if ($relativePath === '' || preg_match('#(^|/)\\.\\.(?:/|$)#', $relativePath)) {
            $this->abort(404, '404 - Bestand niet gevonden');
        }

        $candidates = [
            $this->projectRoot . '/public/uploads/' . $relativePath,
            $this->projectRoot . '/uploads/' . $relativePath,
        ];

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? (finfo_file($finfo, $candidate) ?: 'application/octet-stream') : 'application/octet-stream';
            if ($finfo) {
                finfo_close($finfo);
            }

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($candidate));
            header('Cache-Control: public, max-age=86400');
            readfile($candidate);
            exit;
        }

        $this->abort(404, '404 - Bestand niet gevonden');
    }

    private function cachedValue(string $key, callable $producer, int $ttl = 300): mixed
    {
        $item = $this->appCache->getItem($key);
        if (!$item->isHit()) {
            $item->set($producer());
            $item->expiresAfter($ttl);
            $this->appCache->save($item);
        }
        return $item->get();
    }

    private function render(string $viewPath, array $vars = []): void
    {
        $absolutePath = $this->projectRoot . '/views/' . ltrim($viewPath, '/');
        $this->renderAbsolute($absolutePath, $vars);
    }

    private function renderAbsolute(string $absolutePath, array $vars = []): void
    {
        $link = $this->link;
        $account = $this->account;
        $auth = $this->auth;
        $session = $this->session;
        $logger = $this->logger;
        $csrfTokenManager = $this->csrfTokenManager;
        $appCache = $this->appCache;

        extract($vars, EXTR_SKIP);
        require $absolutePath;
    }

    private function handleSitemap(): void
    {
        if (!class_exists('App\\Services\\SitemapService')) {
            $this->abort(500, 'Sitemap service ontbreekt');
        }

        $baseUrl = rtrim((string)(getenv('SITE_URL') ?: ''), '/');
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            $baseUrl = $scheme . '://' . $host;
        }

        $service = new \App\Services\SitemapService($this->link, $baseUrl);
        $xml = $service->buildXml();

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $xml;
        exit;
    }

    private function getCsrfTokenValue(): string
    {
        return (string)$this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue();
    }

    private function isValidCsrfSubmission(string $submittedToken): bool
    {
        if ($submittedToken === '') {
            return false;
        }

        return $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $submittedToken));
    }

    private function abort(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        echo $message;
        exit;
    }
}
