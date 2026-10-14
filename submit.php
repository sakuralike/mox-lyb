<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/messages.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(405, ['ok' => false, 'message' => '请使用 POST 提交留言。']);
}
if (!installed()) {
    json_response(503, ['ok' => false, 'message' => '留言板尚未安装。']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 6 * 1024 * 1024) {
    json_response(413, ['ok' => false, 'message' => '请求内容过大，图片不能超过 5 MB。']);
}

$web = post_string('source') === 'web';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($fetchSite === 'cross-site') {
    json_response(403, ['ok' => false, 'message' => '不接受跨站页面提交。']);
}
if ($origin !== '') {
    $expectedOrigin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
        . ($_SERVER['HTTP_HOST'] ?? '');
    if (!hash_equals($expectedOrigin, $origin)) {
        json_response(403, ['ok' => false, 'message' => '不接受跨站页面提交。']);
    }
}
if (($web || $origin !== '' || $fetchSite !== '') && !valid_csrf()) {
    json_response(403, ['ok' => false, 'message' => '页面已失效，请刷新后再提交。']);
}
try {
    $result = submit_message($web ? 'web' : 'desktop');
    $message = $result['status'] === 'pending' ? '留言已提交，等待管理员审核后公开。' : '留言已发布，感谢你的分享。';
    if ($web) {
        unset($_SESSION['form_values']);
        flash('success', $message);
        redirect_to('/index.php');
    }
    json_response(200, ['ok' => true, 'id' => $result['id'], 'status' => $result['status'], 'message' => $message]);
} catch (SubmissionError $error) {
    if ($error->status === 429) {
        header('Retry-After: 600');
    }
    if ($web) {
        $_SESSION['form_values'] = [
            'name' => mb_substr(post_string('name'), 0, 40, 'UTF-8'),
            'message' => mb_substr(post_string('message'), 0, MAX_MESSAGE_LENGTH, 'UTF-8'),
        ];
        flash('error', $error->getMessage());
        redirect_to('/index.php#write');
    }
    json_response($error->status, ['ok' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    report_exception($error, 'submission');
    json_response(503, ['ok' => false, 'message' => '留言未能保存，请稍后重试。']);
}
