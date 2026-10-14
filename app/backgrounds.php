<?php
declare(strict_types=1);

const BACKGROUND_FILE = APP_ROOT . '/storage/background.php';
const BACKGROUND_GUARD = "<?php http_response_code(404); exit; ?>\n";

function background_urls(): array
{
    if (is_file(BACKGROUND_FILE)) {
        return [app_url('/image.php?background=custom&v=' . (string) filemtime(BACKGROUND_FILE))];
    }
    return array_map(
        static fn (int $number): string => app_url('/assets/backgrounds/LPbj' . $number . '.webp'),
        range(1, 5)
    );
}

function save_background(array $image): void
{
    $content = BACKGROUND_GUARD . json_encode([
        'mime' => $image['mime'],
        'data' => base64_encode($image['bytes']),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = APP_ROOT . '/storage/.background-' . bin2hex(random_bytes(16)) . '.php';
    $handle = @fopen($temporary, 'x+b');
    if ($handle === false) {
        throw new RuntimeException('Cannot create background file.');
    }
    try {
        if (!chmod($temporary, 0600) || fwrite($handle, $content) !== strlen($content)
            || !fflush($handle)) {
            throw new RuntimeException('Cannot write background file.');
        }
        fclose($handle);
        $handle = null;
        if (!rename($temporary, BACKGROUND_FILE)) {
            throw new RuntimeException('Cannot replace background file.');
        }
        clearstatcache(true, BACKGROUND_FILE);
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

function read_background(): ?array
{
    $handle = @fopen(BACKGROUND_FILE, 'rb');
    if ($handle === false) {
        return null;
    }
    try {
        $size = fstat($handle)['size'] ?? 0;
        if ($size < strlen(BACKGROUND_GUARD) || $size > 7 * 1024 * 1024) {
            return null;
        }
        $content = stream_get_contents($handle, 7 * 1024 * 1024);
    } finally {
        fclose($handle);
    }
    if (!is_string($content) || !str_starts_with($content, BACKGROUND_GUARD)) {
        return null;
    }
    $data = json_decode(substr($content, strlen(BACKGROUND_GUARD)), true);
    if (!is_array($data) || !in_array($data['mime'] ?? null, ['image/jpeg', 'image/png'], true)
        || !is_string($data['data'] ?? null)) {
        return null;
    }
    $bytes = base64_decode($data['data'], true);
    if ($bytes === false || $bytes === '' || strlen($bytes) > MAX_IMAGE_BYTES) {
        return null;
    }
    return ['mime' => $data['mime'], 'bytes' => $bytes];
}

function reset_background(): void
{
    if (is_file(BACKGROUND_FILE) && !unlink(BACKGROUND_FILE)) {
        throw new RuntimeException('Cannot reset background.');
    }
    clearstatcache(true, BACKGROUND_FILE);
}
