<?php

if (isset($url) && is_array($url) && $url[0] === 'api') {

    if (count($url) === 1) {

        // Default API endpoint
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'API is working', 'version' => '1.0']);

    } else {

        $urlPath = implode('/', array_slice($url, 1));
        $apiPath = DIR_API . $urlPath . '.php';

        if (!file_exists($apiPath)) {

            $apiPath = DIR_API . $urlPath . '/index.php';

        }

        if (file_exists($apiPath)) {

            require_once $apiPath;

        } else {

            // Show API 404 error
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'API endpoint not found', 'requested' => $urlPath]);

        }

    }

}
