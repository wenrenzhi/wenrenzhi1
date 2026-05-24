<?php
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

if ($step == 3) {
    $host = $_POST['host'] ?? 'localhost';
    $port = $_POST['port'] ?? '3306';
    $dbname = $_POST['dbname'] ?? '';
    $dbuser = $_POST['dbuser'] ?? '';
    $dbpass = $_POST['dbpass'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($dbname) || empty($dbuser) || empty($username) || empty($password)) {
        $error = '请填写所有必填字段';
        $step = 2;
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致';
        $step = 2;
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于6位';
        $step = 2;
    } else {
        try {
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `is_super` TINYINT(1) NOT NULL DEFAULT 0,
                `permissions` JSON NULL,
                `must_change_pwd` TINYINT(1) NOT NULL DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `group_id` INT NULL DEFAULT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `groups` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `periods` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `start_time` DATETIME NOT NULL,
                `end_time` DATETIME NULL DEFAULT NULL,
                `base_score` DECIMAL(10,1) NOT NULL DEFAULT 0.0,
                `remark` TEXT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `records` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `period_id` INT NOT NULL,
                `type` ENUM('add','deduct') NOT NULL,
                `score` DECIMAL(10,1) NOT NULL,
                `reason` TEXT NULL,
                `extra_note` TEXT NULL,
                `operator_id` INT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `status` TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `operation_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `action` VARCHAR(100) NOT NULL,
                `detail` TEXT NULL,
                `ip` VARCHAR(45) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmt = $pdo->prepare("INSERT INTO users (username, password, is_super, status, created_at) VALUES (?, ?, 1, 1, NOW())");
            $stmt->execute([$username, md5($password)]);

            $configContent = "<?php
define('DB_HOST', '" . addslashes($host) . "');
define('DB_PORT', '" . addslashes($port) . "');
define('DB_NAME', '" . addslashes($dbname) . "');
define('DB_USER', '" . addslashes($dbuser) . "');
define('DB_PASS', '" . addslashes($dbpass) . "');
define('SITE_TITLE', '班级操行分管理系统');
define('SITE_URL', 'https://class.hoha.top');
";

            file_put_contents(__DIR__ . '/config.php', $configContent);

            $success = '安装成功！请删除 install.php 文件，然后 <a href="admin_login.php">登录后台</a>';
        } catch (Exception $e) {
            $error = '安装失败：' . $e->getMessage();
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 班级操行分管理系统</title>
    <link rel="stylesheet" href="https://cdn.hoha.top/mdui-v1.0.2/css/mdui.min.css">
    <script src="https://cdn.hoha.top/mdui-v1.0.2/js/mdui.min.js"></script>
    <style>
        body { background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .install-card { width: 100%; max-width: 500px; }
    </style>
</head>
<body class="mdui-theme-primary-indigo">
<div class="mdui-card install-card mdui-shadow-8">
    <?php if ($step == 1): ?>
    <div class="mdui-card-primary">
        <div class="mdui-card-primary-title">班级操行分管理系统</div>
        <div class="mdui-card-primary-subtitle">安装向导</div>
    </div>
    <div class="mdui-card-content mdui-typo">
        <p>欢迎使用班级操行分管理系统！安装向导将引导你完成以下步骤：</p>
        <ol>
            <li>配置数据库连接</li>
            <li>创建管理员账号</li>
            <li>完成安装</li>
        </ol>
        <p style="color: #757575; font-size: 14px;">请确保已创建 MySQL 数据库，并准备好数据库连接信息。</p>
    </div>
    <div class="mdui-card-actions">
        <a href="?step=2" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple">开始安装</a>
    </div>
    <?php elseif ($step == 2): ?>
    <div class="mdui-card-primary">
        <div class="mdui-card-primary-title">数据库与管理员配置</div>
    </div>
    <div class="mdui-card-content">
        <?php if ($error): ?>
        <div class="mdui-chip" style="background: #ffebee; color: #c62828; margin-bottom: 16px;">
            <span class="mdui-chip-title"><?php echo h($error); ?></span>
        </div>
        <?php endif; ?>
        <form method="post" action="?step=3">
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">数据库主机</label>
                <input class="mdui-textfield-input" type="text" name="host" value="localhost" required/>
            </div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">数据库端口</label>
                <input class="mdui-textfield-input" type="text" name="port" value="3306" required/>
            </div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">数据库名</label>
                <input class="mdui-textfield-input" type="text" name="dbname" value="class.hoha.top" required/>
            </div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">数据库用户名</label>
                <input class="mdui-textfield-input" type="text" name="dbuser" value="class.hoha.top" required/>
            </div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">数据库密码</label>
                <input class="mdui-textfield-input" type="password" name="dbpass" value="szcct4qh"/>
            </div>
            <div class="mdui-divider" style="margin: 16px 0;"></div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">超级管理员用户名</label>
                <input class="mdui-textfield-input" type="text" name="username" required/>
            </div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">密码（至少6位）</label>
                <input class="mdui-textfield-input" type="password" name="password" required minlength="6"/>
            </div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label">确认密码</label>
                <input class="mdui-textfield-input" type="password" name="password2" required minlength="6"/>
            </div>
            <div class="mdui-card-actions" style="padding: 0; margin-top: 16px;">
                <button type="submit" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple">完成安装</button>
            </div>
        </form>
    </div>
    <?php elseif ($step == 3): ?>
    <div class="mdui-card-primary">
        <div class="mdui-card-primary-title">安装完成</div>
    </div>
    <div class="mdui-card-content mdui-typo">
        <div class="mdui-chip" style="background: #e8f5e9; color: #2e7d32; margin-bottom: 16px;">
            <span class="mdui-chip-title"><?php echo $success; ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>