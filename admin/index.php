<?php
declare(strict_types=1);
define('APP_SUBDIRECTORY', true);
require dirname(__DIR__) . '/app/bootstrap.php';

if (!installed()) {
    redirect_to('/install/index.php');
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'HEAD'], true)) {
    header('Allow: GET, POST, HEAD');
    http_response_code(405);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 6 * 1024 * 1024) {
    render_error('请求内容过大', '图片不能超过 5 MB，请缩小后重试。', 413);
    exit;
}

start_session();
$errors = [];
$settingsValues = null;
$admin = current_admin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!valid_csrf()) {
            http_response_code(403);
            render_error('请求已过期', '请返回管理后台刷新页面后重试。', 403);
            exit;
        }
        $action = post_string('action');
        if ($action === 'login' && $admin === null) {
            if (!consume_rate('login', 8, 900)) {
                http_response_code(429);
                header('Retry-After: 900');
                $errors[] = '登录尝试过多，请 15 分钟后重试。';
            } else {
                $username = trim(post_string('username'));
                $password = post_string('password');
                $statement = db()->prepare('SELECT id, username, password_hash FROM ' . table_name('admins') . ' WHERE username = ?');
                $statement->execute([mb_substr($username, 0, 32, 'UTF-8')]);
                $row = $statement->fetch();
                $hash = $row['password_hash'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
                $verified = strlen($password) <= 72 && password_verify($password, $hash);
                if (!$row || !$verified || $username !== $row['username']) {
                    http_response_code(401);
                    $errors[] = '账号或密码错误。';
                } else {
                    session_regenerate_id(true);
                    $_SESSION = [
                        'admin' => ['id' => (int) $row['id'], 'username' => $row['username']],
                        'admin_since' => time(),
                        'admin_seen' => time(),
                        'csrf' => bin2hex(random_bytes(32)),
                    ];
                    redirect_to('/admin/index.php');
                }
            }
        } elseif ($admin === null) {
            http_response_code(401);
            $errors[] = '请先登录。';
        } elseif ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('success', '已退出管理后台。');
            redirect_to('/admin/index.php');
        } elseif ($action === 'background_upload') {
            require_once dirname(__DIR__) . '/app/messages.php';
            try {
                $image = read_uploaded_image();
                if ($image === null) {
                    throw new SubmissionError(422, '请先选择一张背景图片。');
                }
                save_background($image);
                flash('success', '背景图片已更换，所有页面刷新后生效。');
                redirect_to('/admin/index.php?background=1#background-settings');
            } catch (SubmissionError $error) {
                http_response_code($error->status);
                $errors[] = $error->getMessage();
            }
        } elseif ($action === 'background_reset') {
            reset_background();
            flash('success', '已恢复 5 张默认背景图片随机轮换。');
            redirect_to('/admin/index.php?background=1#background-settings');
        } elseif ($action === 'settings_save') {
            $settingsValues = [
                'site_name' => trim(post_string('site_name')),
                'site_description' => trim(str_replace(["\r\n", "\r"], "\n", post_string('site_description'))),
                'footer_text' => trim(post_string('footer_text')),
                'moderation_required' => post_string('moderation_required') === '1',
            ];
            $errors = site_settings_errors($settingsValues);
            foreach (['site_name', 'site_description', 'footer_text'] as $key) {
                if (!is_string($_POST[$key] ?? null)) {
                    $errors[] = '网站信息格式无效，请重新填写。';
                    break;
                }
            }
            foreach (['moderation_required', 'remove_logo'] as $key) {
                if (isset($_POST[$key]) && !in_array($_POST[$key], ['0', '1'], true)) {
                    $errors[] = '设置选项无效。';
                    break;
                }
            }
            if ($errors === []) {
                require_once dirname(__DIR__) . '/app/messages.php';
                try {
                    $settings = $settingsValues + ['logo' => site_settings()['logo']];
                    $image = read_uploaded_image();
                    if ($image !== null && post_string('remove_logo') === '1') {
                        throw new SubmissionError(422, '上传新 LOGO 与恢复默认 LOGO 不能同时选择。');
                    }
                    if ($image !== null) {
                        $settings['logo'] = ['mime' => $image['mime'], 'data' => base64_encode($image['bytes'])];
                    } elseif (post_string('remove_logo') === '1') {
                        $settings['logo'] = null;
                    }
                    save_site_settings($settings);
                    flash('success', '网站设置已保存。' . ($settings['moderation_required'] ? '新留言审核后公开。' : '新留言直接公开，无需审核。'));
                    redirect_to('/admin/index.php?settings=1#site-settings');
                } catch (SubmissionError $error) {
                    http_response_code($error->status);
                    $errors[] = $error->getMessage();
                }
            } else {
                http_response_code(422);
            }
        } elseif ($action === 'bulk_delete') {
            $values = $_POST['ids'] ?? null;
            $ids = [];
            if (!is_array($values) || $values === [] || count($values) > 100) {
                $errors[] = '请选择 1 至 100 条留言后再删除。';
            } else {
                foreach ($values as $value) {
                    $id = positive_id($value);
                    if ($id === 0) {
                        $errors[] = '留言选择无效，未删除任何留言。';
                        break;
                    }
                    $ids[] = $id;
                }
            }
            if ($errors !== []) {
                http_response_code(422);
            } else {
                $ids = array_values(array_unique($ids));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $statement = db()->prepare('DELETE FROM ' . table_name('messages') . " WHERE id IN ($placeholders)");
                $statement->execute($ids);
                flash('success', '已删除 ' . $statement->rowCount() . ' 条留言及其附件。');
                redirect_to('/admin/index.php');
            }
        } else {
            $id = positive_id(post_string('id'));
            $table = table_name('messages');
            $statement = db()->prepare("SELECT id FROM $table WHERE id = ?");
            $statement->execute([$id]);
            if ($id === 0 || !$statement->fetchColumn()) {
                http_response_code(404);
                $errors[] = '留言不存在或已经删除。';
            } elseif ($action === 'status' && in_array(post_string('status'), ['approved', 'hidden'], true)) {
                $statement = db()->prepare("UPDATE $table SET status = ? WHERE id = ?");
                $statement->execute([post_string('status'), $id]);
                flash('success', post_string('status') === 'approved' ? '留言已公开。' : '留言已隐藏。');
                redirect_to('/admin/index.php');
            } elseif ($action === 'reply') {
                $reply = trim(str_replace(["\r\n", "\r"], "\n", post_string('reply')));
                if (!text_valid($reply, MAX_MESSAGE_LENGTH, true)) {
                    http_response_code(422);
                    $errors[] = '回复不能超过 3000 个字符，请使用有效的文字内容。';
                } else {
                    $statement = db()->prepare("UPDATE $table SET reply = ?, replied_at = "
                        . ($reply === '' ? 'NULL' : 'UTC_TIMESTAMP()') . ' WHERE id = ?');
                    $statement->execute([$reply === '' ? null : $reply, $id]);
                    flash('success', '回复已保存；只有已公开留言的回复会对外展示。');
                    redirect_to('/admin/index.php');
                }
            } elseif ($action === 'delete') {
                $statement = db()->prepare("DELETE FROM $table WHERE id = ?");
                $statement->execute([$id]);
                flash('success', '留言及附件已删除。');
                redirect_to('/admin/index.php');
            } else {
                http_response_code(422);
                $errors[] = '操作无效。';
            }
        }
    }
    $common = [
        'site_name' => site_settings()['site_name'],
        'notice' => take_flash(),
        'errors' => $errors,
        'values' => ['username' => mb_substr(post_string('username'), 0, 32, 'UTF-8')],
        'settings_values' => $settingsValues,
    ];
    if ($admin === null) {
        render_login($common);
        exit;
    }
    $status = get_string('status');
    if (!in_array($status, ['pending', 'approved', 'hidden'], true)) {
        $status = 'all';
    }
    $q = mb_substr(trim(get_string('q')), 0, 100, 'UTF-8');
    $params = [];
    $conditions = [];
    if ($status !== 'all') {
        $conditions[] = 'status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $conditions[] = '(name LIKE ? OR message LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $table = table_name('messages');
    $statement = db()->prepare("SELECT COUNT(*) FROM $table$where");
    $statement->execute($params);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = min($pages, max(1, (int) get_string('page')));
    $offset = ($page - 1) * 20;
    $statement = db()->prepare("SELECT id, name, message, status, source, reply, created_at, replied_at, image_size
        FROM $table$where ORDER BY id DESC LIMIT 20 OFFSET $offset");
    $statement->execute($params);
    $stats = ['pending' => 0, 'approved' => 0, 'hidden' => 0];
    foreach (db()->query("SELECT status, COUNT(*) AS count FROM $table GROUP BY status") as $row) {
        $stats[$row['status']] = (int) $row['count'];
    }
    render_admin($common + [
        'messages' => message_rows($statement->fetchAll()),
        'status' => $status,
        'q' => $q,
        'stats' => $stats,
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'admin_name' => $admin['username'],
    ]);
} catch (Throwable $error) {
    report_exception($error, 'admin');
    http_response_code(503);
    render_error('暂时无法访问', '后台服务暂时不可用，请稍后再试。', 503);
}
