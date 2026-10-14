<?php
declare(strict_types=1);

function installation_checks(): array
{
    $checks = [
        ['label' => 'PHP 8.2 或更高版本', 'ok' => PHP_VERSION_ID >= 80200, 'detail' => PHP_VERSION],
    ];
    foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'session'] as $extension) {
        $checks[] = ['label' => 'PHP 扩展 ' . $extension, 'ok' => extension_loaded($extension),
            'detail' => extension_loaded($extension) ? '已启用' : '请在宝塔 PHP 扩展中安装或启用'];
    }
    $gdReady = extension_loaded('gd') && function_exists('imagecreatefromstring')
        && (imagetypes() & IMG_JPG) && (imagetypes() & IMG_PNG) && (imagetypes() & IMG_WEBP);
    $checks[] = ['label' => '图片处理 JPEG / PNG / WebP', 'ok' => (bool) $gdReady,
        'detail' => $gdReady ? '可用，上传图片会重新编码' : '请启用 GD 的 JPEG、PNG、WebP 支持'];
    $writable = is_dir(APP_ROOT . '/storage') && is_writable(APP_ROOT . '/storage');
    $checks[] = ['label' => 'storage 目录可写', 'ok' => $writable,
        'detail' => $writable ? '可写' : '将 storage 目录所有者设置为站点 PHP 运行用户，并授予写权限'];
    foreach (['upload_max_filesize' => 5, 'post_max_size' => 6, 'memory_limit' => 256] as $setting => $minimum) {
        $value = (string) ini_get($setting);
        $bytes = ini_parse_quantity($value);
        $ok = ($setting === 'memory_limit' && $bytes === -1) || $bytes >= $minimum * 1024 * 1024;
        $checks[] = ['label' => $setting, 'ok' => $ok,
            'detail' => $value . '，需至少 ' . $minimum . 'M'];
    }
    return $checks;
}

function checks_passed(array $checks): bool
{
    foreach ($checks as $check) {
        if (!$check['ok']) {
            return false;
        }
    }
    return true;
}

function ensure_install_key(): string
{
    $path = APP_ROOT . '/storage/install-key.php';
    if (!is_file($path)) {
        $key = bin2hex(random_bytes(24));
        $handle = @fopen($path, 'x');
        if ($handle !== false) {
            $content = "<?php\nif (!defined('APP_ROOT')) { http_response_code(404); exit; }\nreturn " . var_export($key, true) . ";\n";
            try {
                if (fwrite($handle, $content) !== strlen($content)) {
                    throw new RuntimeException('Cannot write installation key.');
                }
            } finally {
                fclose($handle);
            }
            @chmod($path, 0600);
        }
    }
    $key = require $path;
    if (!is_string($key) || strlen($key) !== 48) {
        throw new RuntimeException('Invalid installation key.');
    }
    return $key;
}

function installation_values(): array
{
    $defaults = [
        'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => '',
        'db_user' => '', 'table_prefix' => 'mox_', 'site_name' => 'MOX 留言板', 'admin_user' => '',
    ];
    foreach ($defaults as $key => &$value) {
        if (isset($_POST[$key])) {
            $value = mb_substr(trim(post_string($key)), 0, 128, 'UTF-8');
        }
    }
    return $defaults;
}

function validate_installation(array $values): array
{
    $errors = [];
    if (!preg_match('/\A[a-zA-Z0-9.:\[\]_-]{1,253}\z/D', $values['db_host'])) {
        $errors[] = '数据库地址无效，请填写 IP 或主机名，不含端口。';
    }
    if (!ctype_digit($values['db_port']) || (int) $values['db_port'] < 1 || (int) $values['db_port'] > 65535) {
        $errors[] = '数据库端口需要在 1 至 65535 之间。';
    }
    if (!preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/D', $values['db_name'])) {
        $errors[] = '数据库名称仅支持字母、数字、下划线和短横线。';
    }
    if (!text_valid($values['db_user'], 32)) {
        $errors[] = '请填写有效的数据库账号。';
    }
    if (!preg_match('/\A[a-z][a-z0-9_]{0,23}\z/D', $values['table_prefix'])) {
        $errors[] = '表前缀以小写字母开头，只包含小写字母、数字和下划线，最长 24 个字符。';
    }
    if (!text_valid($values['site_name'], 60)) {
        $errors[] = '站点名称需要 1 至 60 个字符。';
    }
    if (!preg_match('/\A[a-zA-Z0-9_-]{3,32}\z/D', $values['admin_user'])) {
        $errors[] = '管理员账号需要 3 至 32 个字母、数字、下划线或短横线。';
    }
    $password = post_string('admin_password');
    if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 12
        || strlen($password) > 72 || str_contains($password, "\0")) {
        $errors[] = '管理员密码至少 12 个字符，最多 72 字节。';
    }
    if ($password !== post_string('admin_password_confirm')) {
        $errors[] = '两次输入的管理员密码不一致。';
    }
    return $errors;
}

function install_application(array $values): void
{
    $lock = fopen(APP_ROOT . '/storage/install.guard', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('安装正在进行，请稍后重试。');
    }
    $pdo = null;
    $created = [];
    $configWritten = false;
    $temporary = null;
    try {
        if (installed()) {
            throw new RuntimeException('已经安装，不能重复安装。');
        }
        $settings = [
            'host' => $values['db_host'], 'port' => (int) $values['db_port'],
            'name' => $values['db_name'], 'user' => $values['db_user'], 'pass' => post_string('db_password'),
        ];
        try {
            $pdo = connect_database($settings);
        } catch (Throwable $error) {
            report_exception($error, 'install-connect');
            throw new RuntimeException('无法连接数据库，请核对地址、端口、数据库名、账号和密码。');
        }
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        if (!str_contains(strtolower($version), 'mariadb') && version_compare($version, '5.7.0', '<')) {
            throw new RuntimeException('需要 MySQL 5.7 或更高版本。');
        }
        $packetSize = (int) $pdo->query('SELECT @@max_allowed_packet')->fetchColumn();
        if ($packetSize < 8 * 1024 * 1024) {
            throw new RuntimeException('数据库 max_allowed_packet 需至少 8M，请在宝塔数据库配置中调整并重启数据库后重试。');
        }
        $prefix = $values['table_prefix'];
        foreach (['admins', 'messages', 'rate_limits'] as $name) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $statement->execute([$prefix . $name]);
            if ((int) $statement->fetchColumn() > 0) {
                throw new RuntimeException('该表前缀已被使用，请更换前缀。安装不会覆盖已有数据。');
            }
        }
        $definitions = [
            'admins' => 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(32) NOT NULL, password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL, UNIQUE KEY username_unique (username)',
            'messages' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(40) NOT NULL, message TEXT NOT NULL,
                status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
                source ENUM('web','desktop') NOT NULL,
                reply TEXT NULL, created_at DATETIME NOT NULL, replied_at DATETIME NULL,
                image_data MEDIUMBLOB NULL, image_mime VARCHAR(24) NULL, image_size INT UNSIGNED NOT NULL DEFAULT 0,
                dedupe_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                UNIQUE KEY dedupe_unique (dedupe_key), KEY status_id (status, id)",
            'rate_limits' => 'bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
                hits INT UNSIGNED NOT NULL, expires_at BIGINT UNSIGNED NOT NULL, KEY expiry (expires_at)',
        ];
        foreach ($definitions as $name => $definition) {
            $table = '`' . $prefix . $name . '`';
            $pdo->exec("CREATE TABLE $table ($definition) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $created[] = $table;
        }
        $statement = $pdo->prepare("INSERT INTO `{$prefix}admins` (username, password_hash, created_at) VALUES (?, ?, UTC_TIMESTAMP())");
        $statement->execute([$values['admin_user'], password_hash(post_string('admin_password'), PASSWORD_BCRYPT, ['cost' => 12])]);
        $config = [
            'db' => $settings, 'table_prefix' => $prefix, 'site_name' => $values['site_name'],
            'secret' => bin2hex(random_bytes(32)),
        ];
        $content = "<?php\ndeclare(strict_types=1);\nif (!defined('APP_ROOT')) { http_response_code(404); exit; }\nreturn "
            . var_export($config, true) . ";\n";
        $temporary = APP_ROOT . '/storage/.config-' . bin2hex(random_bytes(8)) . '.php';
        if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('配置文件写入失败，请检查 storage 目录权限。');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, APP_ROOT . '/storage/config.php')) {
            @unlink($temporary);
            throw new RuntimeException('配置文件保存失败，请检查 storage 目录权限。');
        }
        $configWritten = true;
        @unlink(APP_ROOT . '/storage/install-key.php');
        session_regenerate_id(true);
        $_SESSION = [
            'install_complete' => true, 'installed_admin' => $values['admin_user'],
            'csrf' => bin2hex(random_bytes(32)),
        ];
    } catch (Throwable $error) {
        if (!$configWritten && $temporary !== null && is_file($temporary)) {
            @unlink($temporary);
        }
        if (!$configWritten && $pdo !== null) {
            foreach (array_reverse($created) as $table) {
                try {
                    $pdo->exec("DROP TABLE $table");
                } catch (Throwable $cleanupError) {
                    report_exception($cleanupError, 'install-cleanup');
                }
            }
        }
        if ($error instanceof PDOException) {
            report_exception($error, 'install-schema');
            throw new RuntimeException('创建数据表失败，请确认数据库账号拥有建表和读写权限。');
        }
        throw $error;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
