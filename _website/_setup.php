<?php

    declare(strict_types=1);

    if (!defined('DIR')) {
        die('Direct access not allowed');
    }

    $route = detect_template_from_url($url ?? ['home']);


    // If detector flagged not_found and a full-page 404 exists under pages/, include it
    // Full-page 404s are expected to contain their own header/footer, so return immediately after including.
    if (!empty($route['context']['not_found'])) {
        $pages404 = __DIR__ . '/pages/404.php';
        $tpl404 = __DIR__ . '/templates/404.php';
        if (file_exists($pages404)) {
            require_once $pages404;
            return;
        }
        // fall through to template-based rendering
        if (file_exists($tpl404)) {
            require_once __DIR__ . '/partials/header.php';
            load_template('404', []);
            require_once __DIR__ . '/partials/footer.php';
            return;
        }

        // no page/template: render lightweight 404 inside header/footer
        require_once __DIR__ . '/partials/header.php';
        if (function_exists('render_404_section')) {
            render_404_section();
        } else {
            if (!headers_sent()) http_response_code(404);
            echo '<h1>404 - Pagina niet gevonden</h1>';
        }
        require_once __DIR__ . '/partials/footer.php';
        return;
    }

    // Normal page rendering uses header + template + footer
    require_once __DIR__ . '/partials/header.php';
    load_template($route['template'], $route['context']);
    require_once __DIR__ . '/partials/footer.php';

