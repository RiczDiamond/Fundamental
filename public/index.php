<?php

    use App\Http\Controllers\WebController;
    use Doctrine\DBAL\DriverManager;
    use FastRoute\Dispatcher;
    use FastRoute\RouteCollector;
    use Symfony\Component\Cache\Adapter\FilesystemAdapter;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\RateLimiter\RateLimiterFactory;
    use Symfony\Component\RateLimiter\Storage\CacheStorage;
    use Symfony\Component\Security\Csrf\CsrfTokenManager;

    require_once '../core/bootstrap.php';

    $account    = new \Account($link);
    $auth       = new \Auth($link);
    $cookie     = new \Cookie();
    $session    = new \Session($link);
    $logger     = new \Logging($link);

    $cacheDir = dirname(__DIR__) . '/storage/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    $appCache = new FilesystemAdapter('fundamental', 0, $cacheDir);

    $loginLimiterFactory = new RateLimiterFactory([
        'id' => 'login',
        'policy' => 'sliding_window',
        'limit' => 5,
        'interval' => '15 minutes',
    ], new CacheStorage($appCache));

    $dbalConnection = null;
    try {
        $dbalConnection = DriverManager::getConnection([
            'dbname' => DB['NAME'],
            'user' => DB['USER'],
            'password' => DB['PASS'],
            'host' => DB['HOST'],
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
        ]);
    } catch (Throwable $e) {
        error_log('DBAL connection failed: ' . $e->getMessage());
    }

    $httpRequest = Request::createFromGlobals();
    $requestedPath = trim((string)($_GET['url'] ?? $httpRequest->getPathInfo()), '/');
    if (str_starts_with($requestedPath, 'index.php/')) {
        $requestedPath = substr($requestedPath, strlen('index.php/'));
    }
    if ($requestedPath === '') {
        $requestedPath = 'home';
    }

    $dispatcher = FastRoute\simpleDispatcher(static function (RouteCollector $routeCollector): void {
        $routeCollector->addRoute(['GET', 'POST'], '/home', 'home');
        $routeCollector->addRoute(['GET', 'POST'], '/login', 'login');
        $routeCollector->addRoute(['GET', 'POST'], '/logout', 'logout');
        $routeCollector->addRoute(['GET', 'POST'], '/register', 'register');
        $routeCollector->addRoute(['GET', 'POST'], '/blog', 'blog_list');
        $routeCollector->addRoute(['GET', 'POST'], '/blog/{slug:.+}', 'blog_show');
        $routeCollector->addRoute(['GET'], '/sitemap.xml', 'sitemap');
        $routeCollector->addRoute(['GET', 'POST'], '/dashboard', 'dashboard');
        $routeCollector->addRoute(['GET', 'POST'], '/dashboard/{section}[/{action}[/{id}[/{extra}]]]', 'dashboard');
        $routeCollector->addRoute(['GET'], '/uploads/{path:.+}', 'uploads');
    });

    $routeInfo = $dispatcher->dispatch($httpRequest->getMethod(), '/' . $requestedPath);
    $url = explode('/', $requestedPath);

    if ($routeInfo[0] === Dispatcher::FOUND) {
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        if ($handler === 'home') {
            $url = ['home'];
        } elseif ($handler === 'login') {
            $url = ['login'];
        } elseif ($handler === 'logout') {
            $url = ['logout'];
        } elseif ($handler === 'register') {
            $url = ['register'];
        } elseif ($handler === 'blog_list') {
            $url = ['blog'];
        } elseif ($handler === 'blog_show') {
            $url = ['blog', trim((string)($vars['slug'] ?? ''), '/')];
        } elseif ($handler === 'sitemap') {
            $url = ['sitemap.xml'];
        } elseif ($handler === 'dashboard') {
            $url = ['dashboard'];
            foreach (['section', 'action', 'id', 'extra'] as $key) {
                if (!empty($vars[$key])) {
                    $url[] = (string)$vars[$key];
                }
            }
        } elseif ($handler === 'uploads') {
            $url = array_merge(['uploads'], explode('/', trim((string)($vars['path'] ?? ''), '/')));
        }
    }

    $csrfTokenManager = new CsrfTokenManager();
    $csrfToken = $csrfTokenManager->getToken('fundamental_form')->getValue();
    $session->set('csrf', $csrfToken);

    $webController = new WebController(
        $link,
        $account,
        $auth,
        $session,
        $logger,
        $appCache,
        $loginLimiterFactory,
        dirname(__DIR__),
        $csrfTokenManager
    );

    $webController->dispatch($url);