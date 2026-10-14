<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!installed()) {
    redirect_to('/install/index.php');
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    render_error('请求方式不支持', '请通过留言表单提交内容。', 405);
    exit;
}

try {
    $table = table_name('messages');
    $total = (int) db()->query("SELECT COUNT(*) FROM $table WHERE status = 'approved'")->fetchColumn();
    $pages = max(1, (int) ceil($total / 10));
    $page = min($pages, max(1, (int) get_string('page')));
    $offset = ($page - 1) * 10;
    $rows = db()->query("SELECT id, name, message, reply, created_at, replied_at, image_size
        FROM $table WHERE status = 'approved' ORDER BY id DESC LIMIT 10 OFFSET $offset")->fetchAll();
    start_session();
    $values = $_SESSION['form_values'] ?? [];
    unset($_SESSION['form_values']);
    render_home([
        'site_name' => site_settings()['site_name'],
        'messages' => message_rows($rows),
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'notice' => take_flash(),
        'errors' => [],
        'values' => $values,
    ]);
} catch (Throwable $error) {
    report_exception($error, 'home');
    http_response_code(503);
    render_error('暂时无法访问', '服务暂时不可用，请稍后再试。', 503);
}
