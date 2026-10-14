<?php
declare(strict_types=1);

final class SubmissionError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function read_uploaded_image(): ?array
{
    if (!isset($_FILES['image'])) {
        return null;
    }
    $file = $_FILES['image'];
    if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
        throw new SubmissionError(422, '图片上传格式无效。');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new SubmissionError(413, '图片不能超过 5 MB。');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name'])) {
        throw new SubmissionError(422, '图片上传失败，请重新选择。');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size === 0 || $size > MAX_IMAGE_BYTES) {
        throw new SubmissionError(413, '请选择不超过 5 MB 的有效图片。');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/gif' => IMAGETYPE_GIF,
        'image/webp' => IMAGETYPE_WEBP,
        'image/bmp' => IMAGETYPE_BMP,
        'image/x-ms-bmp' => IMAGETYPE_BMP,
    ];
    $dimensions = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || $dimensions === false || $dimensions[2] !== $types[$mime]) {
        throw new SubmissionError(422, '只支持真实的 JPG、PNG、GIF、WebP 或 BMP 图片。');
    }
    if ($dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 6000
        || $dimensions[1] > 6000 || $dimensions[0] * $dimensions[1] > 12000000) {
        throw new SubmissionError(422, '图片尺寸过大，请缩小到 1200 万像素以内，单边不超过 6000 像素。');
    }
    $raw = file_get_contents($file['tmp_name']);
    if ($raw === false) {
        throw new SubmissionError(422, '无法读取上传的图片。');
    }
    $image = @imagecreatefromstring($raw);
    unset($raw);
    if ($image === false) {
        throw new SubmissionError(422, '图片已损坏，无法读取。');
    }
    ob_start();
    try {
        if ($mime === 'image/jpeg') {
            $ok = imagejpeg($image, null, 88);
            $outputMime = 'image/jpeg';
        } else {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = imagepng($image, null, 6);
            $outputMime = 'image/png';
        }
        $bytes = ob_get_contents();
    } finally {
        ob_end_clean();
        unset($image);
    }
    if (!$ok || !is_string($bytes) || $bytes === '' || strlen($bytes) > MAX_IMAGE_BYTES) {
        throw new SubmissionError(422, '图片处理后过大，请缩小图片后重试。');
    }
    return ['bytes' => $bytes, 'mime' => $outputMime];
}

function submit_message(string $source): array
{
    if (!consume_rate('submission', 10, 600)) {
        throw new SubmissionError(429, '提交过于频繁，请稍后再试。');
    }
    $name = trim(post_string('name'));
    $message = trim(str_replace(["\r\n", "\r"], "\n", post_string('message')));
    if (!text_valid($name, 40) || str_contains($name, "\n") || str_contains($name, "\r")) {
        throw new SubmissionError(422, '昵称需要 1 至 40 个字符，且不能换行。');
    }
    if (!text_valid($message, MAX_MESSAGE_LENGTH)) {
        throw new SubmissionError(422, '留言需要 1 至 3000 个字符，请使用 UTF-8 编码。');
    }
    if (post_string('website') !== '') {
        throw new SubmissionError(422, '提交无效，请重新填写。');
    }
    $image = read_uploaded_image();
    $dedupe = hash_hmac('sha256', json_encode([
        $name, $message, $image === null ? '' : hash('sha256', $image['bytes']), intdiv(time(), 600),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), config()['secret']);
    $status = site_settings()['moderation_required'] ? 'pending' : 'approved';
    $table = table_name('messages');
    $statement = db()->prepare("INSERT INTO $table
        (name, message, status, source, image_data, image_mime, image_size, dedupe_key, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())");
    try {
        $statement->execute([
            $name, $message, $status, $source, $image['bytes'] ?? null, $image['mime'] ?? null,
            $image === null ? 0 : strlen($image['bytes']), $dedupe,
        ]);
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? null) === 1062) {
            throw new SubmissionError(409, '这条留言已提交，请勿重复提交。');
        }
        throw $error;
    }
    return ['id' => (int) db()->lastInsertId(), 'status' => $status];
}
