<?php
declare(strict_types=1);
define('APP_SUBDIRECTORY', true);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/installer.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'HEAD'], true)) {
    header('Allow: GET, POST, HEAD');
    http_response_code(405);
    exit;
}

start_session();
$data = ['site_name' => 'MOX 留言板', 'notice' => null, 'errors' => [], 'values' => [], 'checks' => [], 'step' => 'check'];

try {
    if (installed()) {
        $data['site_name'] = config()['site_name'];
        $data['step'] = ($_SESSION['install_complete'] ?? false) ? 'done' : 'locked';
        $data['admin_user'] = $_SESSION['installed_admin'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(409);
            $data['step'] = 'locked';
        }
        render_install($data);
        exit;
    }
    $data['checks'] = installation_checks();
    $ready = checks_passed($data['checks']);
    $authorized = (int) ($_SESSION['installer_until'] ?? 0) >= time();
    if ($ready) {
        $key = ensure_install_key();
        $data['values'] = installation_values();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!valid_csrf()) {
            http_response_code(403);
            $data['errors'][] = '页面已失效，请刷新后重新提交。';
        } elseif (!$ready) {
            http_response_code(422);
            $data['errors'][] = '请先完成所有环境检查。';
        } elseif (post_string('action') === 'configure') {
            if (!hash_equals($key, trim(post_string('install_key')))) {
                http_response_code(403);
                $data['errors'][] = '安装口令不正确，请通过宝塔文件管理查看 storage/install-key.php。';
            } else {
                session_regenerate_id(true);
                $_SESSION['installer_until'] = time() + 1800;
                $authorized = true;
            }
        } elseif (post_string('action') === 'install' && $authorized) {
            $data['errors'] = validate_installation($data['values']);
            if ($data['errors'] === []) {
                install_application($data['values']);
                redirect_to('/install/index.php');
            }
            http_response_code(422);
        } else {
            http_response_code(403);
            $data['errors'][] = '请先验证安装口令。';
        }
    }
    $data['step'] = $authorized && $ready ? 'setup' : 'check';
    render_install($data);
} catch (Throwable $error) {
    report_exception($error, 'installer');
    http_response_code(422);
    $data['step'] = !empty($_SESSION['installer_until']) && $_SESSION['installer_until'] >= time() ? 'setup' : 'check';
    $data['errors'][] = $error instanceof RuntimeException && !$error instanceof PDOException
        ? $error->getMessage() : '安装未完成，请检查环境和目录权限后重试。';
    render_install($data);
}
