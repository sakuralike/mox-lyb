<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const MAX_MESSAGE_LENGTH = 3000;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Shanghai');

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
if (defined('APP_SUBDIRECTORY')) {
    $scriptDirectory = str_replace('\\', '/', dirname($scriptDirectory));
}
define('APP_BASE', $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : $scriptDirectory);

function h(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = '/'): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
}

security_headers();

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('MOXBOARD' . substr(hash('sha256', APP_BASE), 0, 10));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_url('/'),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function get_string(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? $value : '';
}

function valid_csrf(): bool
{
    $token = post_string('csrf');
    return $token !== '' && hash_equals(csrf_token(), $token);
}

function flash(string $type, string $text): void
{
    start_session();
    $_SESSION['notice'] = ['type' => $type, 'text' => $text];
}

function take_flash(): ?array
{
    start_session();
    $notice = $_SESSION['notice'] ?? null;
    unset($_SESSION['notice']);
    return is_array($notice) ? $notice : null;
}

function redirect_to(string $path): never
{
    header('Location: ' . app_url($path), true, 303);
    exit;
}

function json_response(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function installed(): bool
{
    return is_file(APP_ROOT . '/storage/config.php');
}

function config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/storage/config.php';
        if (!is_array($config) || !isset($config['db'], $config['secret'], $config['table_prefix'])) {
            throw new RuntimeException('Invalid application configuration.');
        }
    }
    return $config;
}

function connect_database(array $settings): PDO
{
    $dsn = 'mysql:host=' . $settings['host'] . ';port=' . $settings['port']
        . ';dbname=' . $settings['name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $settings['user'], $settings['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    return $pdo ??= connect_database(config()['db']);
}

function table_name(string $name): string
{
    $prefix = config()['table_prefix'];
    if (!preg_match('/\A[a-z][a-z0-9_]{0,23}\z/D', $prefix)
        || !in_array($name, ['messages', 'admins', 'rate_limits'], true)) {
        throw new RuntimeException('Invalid table identifier.');
    }
    return '`' . $prefix . $name . '`';
}

function report_exception(Throwable $error, string $context): void
{
    error_log('MOX feedback: ' . $context . ' [' . get_class($error) . ':' . $error->getCode() . ']');
}

function text_valid(string $text, int $max, bool $allowEmpty = false): bool
{
    if (!mb_check_encoding($text, 'UTF-8')) {
        return false;
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
        return false;
    }
    $length = mb_strlen($text, 'UTF-8');
    return ($allowEmpty || $length > 0) && $length <= $max;
}

function positive_id(mixed $value): int
{
    if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $value)) {
        return 0;
    }
    return (int) $value;
}

function current_admin(): ?array
{
    start_session();
    if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
        return null;
    }
    $now = time();
    if ($now - (int) ($_SESSION['admin_since'] ?? 0) > 28800
        || $now - (int) ($_SESSION['admin_seen'] ?? 0) > 1800) {
        unset($_SESSION['admin'], $_SESSION['admin_since'], $_SESSION['admin_seen']);
        return null;
    }
    $_SESSION['admin_seen'] = $now;
    return $_SESSION['admin'];
}

function consume_rate(string $purpose, int $limit, int $window): bool
{
    $now = time();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bucket = hash_hmac('sha256', $purpose . '|' . $ip . '|' . intdiv($now, $window), config()['secret']);
    $table = table_name('rate_limits');
    $statement = db()->prepare("INSERT INTO $table (bucket, hits, expires_at) VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE hits = hits + 1");
    $statement->execute([$bucket, (intdiv($now, $window) + 2) * $window]);
    $statement = db()->prepare("SELECT hits FROM $table WHERE bucket = ?");
    $statement->execute([$bucket]);
    $hits = (int) $statement->fetchColumn();
    $statement = db()->prepare("DELETE FROM $table WHERE expires_at < ? LIMIT 100");
    $statement->execute([$now]);
    return $hits <= $limit;
}

function display_time(?string $utc): ?string
{
    if ($utc === null) {
        return null;
    }
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i');
}

function message_rows(array $rows): array
{
    foreach ($rows as &$row) {
        $row['has_image'] = (int) $row['image_size'] > 0;
        $row['created_at'] = display_time($row['created_at']);
        $row['replied_at'] = display_time($row['replied_at']);
        $row['reply'] ??= '';
    }
    return $rows;
}

require_once __DIR__ . '/backgrounds.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/views.php';
