<?php
declare(strict_types=1);

$isHttps = (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

if (!$isHttps) {
    $requestedHost = (string) ($_SERVER['HTTP_HOST'] ?? 'gpms.com.br');
    $safeHost = preg_match('/^(?:www\.)?gpms\.com\.br$/i', $requestedHost)
        ? $requestedHost
        : 'gpms.com.br';
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    header('Location: https://' . $safeHost . $requestUri, true, 301);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Strict-Transport-Security: max-age=31536000');
readfile(__DIR__ . '/index.html');
