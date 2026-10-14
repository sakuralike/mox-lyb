<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

try {
    if (get_string('logo') === 'site') {
        $logo = site_logo_image(site_settings());
        if ($logo === null) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $logo['mime']);
        header('Content-Length: ' . strlen($logo['bytes']));
        header('Content-Disposition: inline; filename="logo.' . ($logo['mime'] === 'image/png' ? 'png"' : 'jpg"'));
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            echo $logo['bytes'];
        }
        exit;
    }
    if (get_string('background') === 'custom') {
        $background = read_background();
        if ($background === null) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $background['mime']);
        header('Content-Length: ' . strlen($background['bytes']));
        header('Content-Disposition: inline; filename="background.' . ($background['mime'] === 'image/png' ? 'png"' : 'jpg"'));
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            echo $background['bytes'];
        }
        exit;
    }
    $id = positive_id(get_string('id'));
    if (!installed() || $id === 0) {
        http_response_code(404);
        exit;
    }
    $admin = current_admin();
    session_write_close();
    $table = table_name('messages');
    $statement = db()->prepare("SELECT image_data, image_mime, image_size FROM $table
        WHERE id = ? AND image_size > 0" . ($admin === null ? " AND status = 'approved'" : ''));
    $statement->execute([$id]);
    $image = $statement->fetch();
    if (!$image || !in_array($image['image_mime'], ['image/jpeg', 'image/png'], true)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $image['image_mime']);
    header('Content-Length: ' . $image['image_size']);
    header('Content-Disposition: inline; filename="feedback-' . $id . ($image['image_mime'] === 'image/png' ? '.png"' : '.jpg"'));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        echo $image['image_data'];
    }
} catch (Throwable $error) {
    report_exception($error, 'image');
    http_response_code(503);
}
