<?php
declare(strict_types=1);

date_default_timezone_set('America/Fortaleza');

define('GPMS_ROOT', dirname(__DIR__));
define('GPMS_STORAGE', GPMS_ROOT . '/storage');
define('GPMS_UPLOADS', GPMS_ROOT . '/blog/uploads');

function gpms_host(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'gpms.com.br'));
    return preg_replace('/:\d+$/', '', $host) ?: 'gpms.com.br';
}

function gpms_is_local(): bool
{
    return in_array(gpms_host(), ['localhost', '127.0.0.1'], true);
}

function gpms_force_https(): void
{
    if (gpms_is_local() || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return;
    }

    $host = gpms_host();
    if (!preg_match('/^(?:www\.|blog\.|admin\.)?gpms\.com\.br$/', $host)) {
        $host = 'gpms.com.br';
    }

    header('Location: https://' . $host . (string) ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

function gpms_security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: DENY');
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    $frames = $admin ? "'none'" : 'https://www.youtube-nocookie.com https://player.vimeo.com';
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "img-src 'self' https://gpms.com.br https://blog.gpms.com.br data:; " .
        "style-src 'self'; script-src 'self'; frame-src {$frames}; " .
        "font-src 'self' https://gpms.com.br; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    );
}

function gpms_blog_base(): string
{
    return strpos(gpms_host(), 'blog.') === 0 ? '' : '/blog';
}

function gpms_admin_base(): string
{
    return strpos(gpms_host(), 'admin.') === 0 ? '' : '/admin';
}

function gpms_blog_url(string $path = ''): string
{
    return gpms_blog_base() . '/' . ltrim($path, '/');
}

function gpms_admin_url(string $path = ''): string
{
    return gpms_admin_base() . '/' . ltrim($path, '/');
}

function gpms_canonical_blog_url(string $path = ''): string
{
    return 'https://blog.gpms.com.br/' . ltrim($path, '/');
}

function gpms_asset_url(string $path): string
{
    return 'https://gpms.com.br/assets/' . ltrim($path, '/');
}

function gpms_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gpms_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('gpms_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !gpms_is_local(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function gpms_ensure_directories(): void
{
    foreach ([GPMS_STORAGE, GPMS_UPLOADS] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar a pasta de dados.');
        }
    }
}

function gpms_store_path(string $name): string
{
    if (!preg_match('/^[a-z0-9-]+\.php$/', $name)) {
        throw new InvalidArgumentException('Nome de arquivo inválido.');
    }
    return GPMS_STORAGE . '/' . $name;
}

function gpms_read_store(string $name, array $default = []): array
{
    $path = gpms_store_path($name);
    if (!is_file($path)) {
        return $default;
    }

    $data = include $path;
    return is_array($data) ? $data : $default;
}

function gpms_write_store(string $name, array $data): void
{
    gpms_ensure_directories();
    $path = gpms_store_path($name);
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
    $contents = "<?php\nreturn " . var_export($data, true) . ";\n";

    if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Não foi possível salvar os dados. Verifique a permissão da pasta storage.');
    }
    @chmod($path, 0640);
}

function gpms_posts(): array
{
    $posts = gpms_read_store('posts.php');
    usort($posts, static function (array $a, array $b): int {
        return strcmp((string) ($b['published_at'] ?? $b['updated_at'] ?? ''), (string) ($a['published_at'] ?? $a['updated_at'] ?? ''));
    });
    return $posts;
}

function gpms_save_posts(array $posts): void
{
    gpms_write_store('posts.php', array_values($posts));
}

function gpms_post_by_id(string $id): ?array
{
    foreach (gpms_posts() as $post) {
        if (($post['id'] ?? '') === $id) {
            return $post;
        }
    }
    return null;
}

function gpms_post_by_slug(string $slug): ?array
{
    foreach (gpms_posts() as $post) {
        if (($post['slug'] ?? '') === $slug && ($post['status'] ?? '') === 'published') {
            return $post;
        }
    }
    return null;
}

function gpms_published_posts(string $query = '', string $category = ''): array
{
    $query = gpms_lower(trim($query));
    return array_values(array_filter(gpms_posts(), static function (array $post) use ($query, $category): bool {
        if (($post['status'] ?? '') !== 'published') {
            return false;
        }
        if ($category !== '' && ($post['category'] ?? '') !== $category) {
            return false;
        }
        if ($query === '') {
            return true;
        }
        $haystack = gpms_lower(implode(' ', [
            (string) ($post['title'] ?? ''),
            (string) ($post['excerpt'] ?? ''),
            (string) ($post['category'] ?? ''),
            (string) ($post['body'] ?? ''),
        ]));
        return strpos($haystack, $query) !== false;
    }));
}

function gpms_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function gpms_substr(string $value, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
}

function gpms_slugify(string $value): string
{
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = strtolower($converted !== false ? $converted : $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'artigo';
}

function gpms_unique_slug(string $requested, string $id = ''): string
{
    $base = gpms_slugify($requested);
    $slug = $base;
    $suffix = 2;
    $posts = gpms_posts();
    while (array_filter($posts, static function (array $post) use ($slug, $id): bool {
        return ($post['slug'] ?? '') === $slug && ($post['id'] ?? '') !== $id;
    })) {
        $slug = $base . '-' . $suffix++;
    }
    return $slug;
}

function gpms_excerpt(string $body, int $length = 180): string
{
    $plain = trim(preg_replace('/\s+/', ' ', preg_replace('/^(?:##\s+|-\s+)/m', '', $body) ?? $body) ?? $body);
    return gpms_substr($plain, 0, $length) . (strlen($plain) > $length ? '…' : '');
}

function gpms_format_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $months = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $time = strtotime($date);
    return date('j', $time) . ' de ' . $months[(int) date('n', $time)] . ' de ' . date('Y', $time);
}

function gpms_render_body(string $body): string
{
    $lines = preg_split('/\R/', trim($body)) ?: [];
    $html = '';
    $paragraph = [];
    $listOpen = false;

    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if ($paragraph) {
            $html .= '<p>' . gpms_e(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $flushParagraph();
            if ($listOpen) {
                $html .= '</ul>';
                $listOpen = false;
            }
            continue;
        }
        if (strpos($trimmed, '## ') === 0) {
            $flushParagraph();
            if ($listOpen) {
                $html .= '</ul>';
                $listOpen = false;
            }
            $html .= '<h2>' . gpms_e(substr($trimmed, 3)) . '</h2>';
            continue;
        }
        if (strpos($trimmed, '- ') === 0) {
            $flushParagraph();
            if (!$listOpen) {
                $html .= '<ul>';
                $listOpen = true;
            }
            $html .= '<li>' . gpms_e(substr($trimmed, 2)) . '</li>';
            continue;
        }
        if ($listOpen) {
            $html .= '</ul>';
            $listOpen = false;
        }
        $paragraph[] = $trimmed;
    }
    $flushParagraph();
    if ($listOpen) {
        $html .= '</ul>';
    }
    return $html;
}

function gpms_image_url(?string $filename): string
{
    if (!$filename) {
        return '';
    }
    $path = 'uploads/' . rawurlencode(basename($filename));
    if (strpos(gpms_host(), 'admin.') === 0) {
        return gpms_canonical_blog_url($path);
    }
    return gpms_blog_url($path);
}

function gpms_save_uploaded_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('A imagem não pôde ser enviada.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Cada imagem deve ter no máximo 5 MB.');
    }

    $temporary = (string) ($file['tmp_name'] ?? '');
    $imageInfo = @getimagesize($temporary);
    $mime = $imageInfo['mime'] ?? '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Use imagens JPG, PNG ou WebP.');
    }

    gpms_ensure_directories();
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporary, GPMS_UPLOADS . '/' . $filename)) {
        throw new RuntimeException('Não foi possível gravar a imagem enviada.');
    }
    return $filename;
}

function gpms_uploaded_images(string $field, int $limit = 6): array
{
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return [];
    }

    $saved = [];
    $count = min(count($_FILES[$field]['name']), $limit);
    for ($index = 0; $index < $count; $index++) {
        if ((int) $_FILES[$field]['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $saved[] = gpms_save_uploaded_image([
            'name' => $_FILES[$field]['name'][$index],
            'type' => $_FILES[$field]['type'][$index],
            'tmp_name' => $_FILES[$field]['tmp_name'][$index],
            'error' => $_FILES[$field]['error'][$index],
            'size' => $_FILES[$field]['size'][$index],
        ]);
    }
    return $saved;
}

function gpms_delete_image(?string $filename): void
{
    if (!$filename) {
        return;
    }
    $path = GPMS_UPLOADS . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function gpms_video_embed(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = trim((string) ($parts['path'] ?? ''), '/');
    $id = '';
    $src = '';

    if ($host === 'youtu.be') {
        $id = explode('/', $path)[0] ?? '';
    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = (string) ($query['v'] ?? '');
        if (!$id && strpos($path, 'shorts/') === 0) {
            $id = substr($path, 7);
        }
    }
    if ($id && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
        $src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id);
    } elseif (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('/^\d+$/', $path)) {
        $src = 'https://player.vimeo.com/video/' . $path;
    }
    return $src;
}

function gpms_csrf_token(): string
{
    gpms_session_start();
    if (empty($_SESSION['gpms_csrf'])) {
        $_SESSION['gpms_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['gpms_csrf'];
}

function gpms_verify_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(gpms_csrf_token(), $token)) {
        throw new RuntimeException('A sessão expirou. Atualize a página e tente novamente.');
    }
}

function gpms_auth_config(): array
{
    return gpms_read_store('auth.php');
}

function gpms_is_configured(): bool
{
    $auth = gpms_auth_config();
    return !empty($auth['username']) && !empty($auth['password_hash']);
}

function gpms_is_admin(): bool
{
    gpms_session_start();
    return !empty($_SESSION['gpms_admin']) && $_SESSION['gpms_admin'] === true;
}

function gpms_require_admin(): void
{
    if (!gpms_is_admin()) {
        header('Location: ' . gpms_admin_url(), true, 303);
        exit;
    }
}

function gpms_flash(string $type, string $message): void
{
    gpms_session_start();
    $_SESSION['gpms_flash'] = ['type' => $type, 'message' => $message];
}

function gpms_take_flash(): ?array
{
    gpms_session_start();
    $flash = $_SESSION['gpms_flash'] ?? null;
    unset($_SESSION['gpms_flash']);
    return is_array($flash) ? $flash : null;
}

function gpms_login_blocked(string $ip): bool
{
    $attempts = gpms_read_store('login-attempts.php');
    $key = hash('sha256', $ip);
    $record = $attempts[$key] ?? [];
    return (int) ($record['count'] ?? 0) >= 5 && (int) ($record['last'] ?? 0) > time() - 900;
}

function gpms_record_login_attempt(string $ip, bool $success): void
{
    $attempts = gpms_read_store('login-attempts.php');
    $key = hash('sha256', $ip);
    foreach ($attempts as $attemptKey => $record) {
        if ((int) ($record['last'] ?? 0) < time() - 86400) {
            unset($attempts[$attemptKey]);
        }
    }
    if ($success) {
        unset($attempts[$key]);
    } else {
        $attempts[$key] = [
            'count' => (int) ($attempts[$key]['count'] ?? 0) + 1,
            'last' => time(),
        ];
    }
    gpms_write_store('login-attempts.php', $attempts);
}
