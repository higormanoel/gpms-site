<?php
declare(strict_types=1);

$forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$requestedHost = (string) ($_SERVER['HTTP_HOST'] ?? 'gpms.com.br');
$hostWithoutPort = strtolower((string) preg_replace('/:\d+$/', '', $requestedHost));
$isLocal = in_array($hostWithoutPort, ['localhost', '127.0.0.1'], true);
$isHttps = (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    || strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
    || $forwardedProto === 'https';

if (!$isHttps && !$isLocal) {
    $safeHost = preg_match('/^(?:www\.)?gpms\.com\.br$/i', $requestedHost)
        ? $requestedHost
        : 'gpms.com.br';
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    header('Location: https://' . $safeHost . $requestUri, true, 301);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Frame-Options: DENY');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

$html = (string) file_get_contents(__DIR__ . '/index.html');
if (!$isLocal) {
    $releaseBase = '/gpms-release-20260803/';
    $html = str_replace(
        ['href="assets/', 'src="assets/', 'action="contact.php"'],
        [
            'href="' . $releaseBase . 'assets/',
            'src="' . $releaseBase . 'assets/',
            'action="' . $releaseBase . 'contact.php"',
        ],
        $html
    );
}

echo $html;
