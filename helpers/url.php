<?php

$httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
$isHttps = $httpsValue !== '' && $httpsValue !== 'off';
$scheme = $isHttps ? 'https' : 'http';

$rawHost = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$safeHost = preg_replace('/[^a-z0-9\.\-:\[\]]/i', '', $rawHost) ?? 'localhost';

if ($safeHost === '') {
    $safeHost = 'localhost';
}

define('BASE_URL', $scheme . '://' . $safeHost);

$hostOnly = preg_replace('/:\d+$/', '', $safeHost) ?? $safeHost;
$hostOnly = trim($hostOnly, '[]');
$hostParts = explode('.', strtolower($hostOnly));

if (count($hostParts) > 3 && $hostParts[0] === 'www') {
    define('SUBDOMAIN', $hostParts[1]);
} elseif (count($hostParts) > 2) {
    define('SUBDOMAIN', $hostParts[0]);
} else {
    define('SUBDOMAIN', '');
}

$rawUrl = trim((string) ($_GET['url'] ?? ''));
$rawUrl = trim($rawUrl, "/ \t\r\n");

if ($rawUrl === '') {
    $url = ['home'];
} else {
    $parts = preg_split('#/+#', $rawUrl) ?: [];
    $url = [];

    foreach ($parts as $part) {
        $part = trim((string) $part);

        if ($part === '') {
            continue;
        }

        $safePart = preg_replace('/[^a-z0-9\-]/i', '', strtolower($part)) ?? '';

        if ($safePart !== '') {
            $url[] = $safePart;
        }
    }

    if ($url === []) {
        $url = ['home'];
    }
}

?>