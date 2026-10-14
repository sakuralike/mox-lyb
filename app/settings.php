<?php
declare(strict_types=1);

const SITE_SETTINGS_FILE = APP_ROOT . '/storage/site-settings.php';
const SITE_SETTINGS_GUARD = "<?php http_response_code(404); exit; ?>\n";

function site_settings_defaults(string $siteName = '在线留言'): array
{
    return [
        'site_name' => $siteName,
        'site_description' => "一个想法，一声问候，或是一点小小的建议。\n把想说的话写下来，我们会认真读到。",
        'footer_text' => '小小的留言，也有闪闪发光的心意。',
        'moderation_required' => false,
        'logo' => null,
    ];
}

function site_settings_errors(array $settings): array
{
    $errors = [];
    foreach ([
        'site_name' => [60, false, '网站标题需要 1 至 60 个字符。'],
        'site_description' => [200, true, '网站简介不能超过 200 个字符。'],
        'footer_text' => [120, true, '页脚文字不能超过 120 个字符。'],
    ] as $key => [$limit, $allowEmpty, $message]) {
        if (!is_string($settings[$key] ?? null) || !text_valid($settings[$key], $limit, $allowEmpty)) {
            $errors[] = $message;
        }
    }
    if (!is_bool($settings['moderation_required'] ?? null)) {
        $errors[] = '留言审核开关无效。';
    }
    return $errors;
}

function site_logo_image(array $settings): ?array
{
    $logo = $settings['logo'] ?? null;
    if ($logo === null) {
        return null;
    }
    if (!is_array($logo) || !in_array($logo['mime'] ?? null, ['image/jpeg', 'image/png'], true)
        || !is_string($logo['data'] ?? null)) {
        throw new RuntimeException('Invalid site logo.');
    }
    $bytes = base64_decode($logo['data'], true);
    if ($bytes === false || $bytes === '' || strlen($bytes) > MAX_IMAGE_BYTES) {
        throw new RuntimeException('Invalid site logo data.');
    }
    return ['mime' => $logo['mime'], 'bytes' => $bytes];
}

function site_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    if (!file_exists(SITE_SETTINGS_FILE)) {
        return $settings = site_settings_defaults(installed() ? (string) config()['site_name'] : '在线留言');
    }
    $handle = @fopen(SITE_SETTINGS_FILE, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Cannot read site settings.');
    }
    try {
        $size = fstat($handle)['size'] ?? 0;
        if ($size < strlen(SITE_SETTINGS_GUARD) || $size > 7 * 1024 * 1024) {
            throw new RuntimeException('Invalid site settings size.');
        }
        $content = stream_get_contents($handle, 7 * 1024 * 1024);
    } finally {
        fclose($handle);
    }
    if (!is_string($content) || !str_starts_with($content, SITE_SETTINGS_GUARD)) {
        throw new RuntimeException('Invalid site settings file.');
    }
    $values = json_decode(substr($content, strlen(SITE_SETTINGS_GUARD)), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($values) || site_settings_errors($values) !== [] || !array_key_exists('logo', $values)) {
        throw new RuntimeException('Invalid site settings values.');
    }
    site_logo_image($values);
    return $settings = $values;
}

function site_logo_url(?array $settings = null): ?string
{
    $settings ??= site_settings();
    if ($settings['logo'] === null) {
        return null;
    }
    return app_url('/image.php?logo=site&v=' . substr(hash('sha256', $settings['logo']['data']), 0, 12));
}

function save_site_settings(array $settings): void
{
    if (site_settings_errors($settings) !== [] || !array_key_exists('logo', $settings)) {
        throw new RuntimeException('Invalid site settings.');
    }
    site_logo_image($settings);
    $content = SITE_SETTINGS_GUARD . json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($content) > 7 * 1024 * 1024) {
        throw new RuntimeException('Site settings are too large.');
    }
    $temporary = APP_ROOT . '/storage/.site-settings-' . bin2hex(random_bytes(16)) . '.php';
    $handle = @fopen($temporary, 'x+b');
    if ($handle === false) {
        throw new RuntimeException('Cannot create site settings file.');
    }
    try {
        if (!chmod($temporary, 0600) || fwrite($handle, $content) !== strlen($content) || !fflush($handle)) {
            throw new RuntimeException('Cannot write site settings file.');
        }
        fclose($handle);
        $handle = null;
        if (!rename($temporary, SITE_SETTINGS_FILE)) {
            throw new RuntimeException('Cannot replace site settings file.');
        }
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}
