<?php

    // Get all query params except 'url'
    $params = array_diff_key($_GET ?? [], ['url' => '']);

    /**
     * Safely fetch a query param with a default
     */
    function getParam(array $params, string $key, mixed $default = null): mixed {
        return $params[$key] ?? $default;
    }

    // Example usage:
    // $value = getParam($params, 'foo', 'default');

?>