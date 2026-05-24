<?php
require_once __DIR__ . '/../init.php';

if (!file_exists(__DIR__ . '/../config.php') || !defined('DB_HOST')) {
    header('Location: install.php');
    exit;
}

try {
    $db = getDB();
    $db->query("SELECT 1 FROM users LIMIT 1");
} catch (Exception $e) {
    header('Location: /../install.php');
    exit;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $db->query("SELECT remember_token FROM users LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL DEFAULT NULL");
}

if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_token'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE remember_token = ? AND status = 1 LIMIT 1");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_super'] = (bool)$user['is_super'];
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['must_change_pwd'] = (bool)$user['must_change_pwd'];
        header('Location: index.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || $user['password'] !== md5($password)) {
            $error = '用户名或密码错误';
        } elseif ($user['status'] == 0) {
            $error = '账号已被禁用，请联系管理员';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_super'] = (bool)$user['is_super'];
            $_SESSION['permissions'] = $user['permissions'];
            $_SESSION['must_change_pwd'] = (bool)$user['must_change_pwd'];

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + 604800, '/', '', true, true);
            }

            logOperation($user['id'], 'login', ['username' => $username]);

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>管理员登录</title>
    <link rel="stylesheet" href="https://cdn.hoha.top/mdui-v1.0.2/css/mdui.min.css">
    <script src="https://cdn.hoha.top/js/jquery-4.0.0.min.js"></script>
    <script src="https://cdn.hoha.top/mdui-v1.0.2/js/mdui.min.js"></script>
    <style>
        body {
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
        .login-card .mdui-card-header {
            text-align: center;
            padding-top: 24px;
        }
        .login-card .mdui-card-header .mdui-card-header-title {
            font-size: 22px;
            font-weight: bold;
        }
        .login-card .mdui-card-header .mdui-card-header-subtitle {
            font-size: 14px;
            color: #757575;
        }
        .login-card .mdui-card-content {
            padding: 8px 24px 0;
        }
        .login-card .mdui-card-actions {
            padding: 16px 24px 24px;
        }
    </style>
</head>
<body class="mdui-theme-primary-indigo mdui-theme-accent-blue">
<div class="mdui-card login-card mdui-shadow-8">
    <div class="mdui-card-header">
        <div class="mdui-card-header-title">班级操行分管理系统</div>
        <div class="mdui-card-header-subtitle">管理员登录</div>
    </div>
    <form method="post" action="admin_login.php">
        <div class="mdui-card-content">
            <?php if ($error): ?>
            <div class="mdui-chip" style="background: #ffebee; margin-bottom: 16px;">
                <span class="mdui-chip-title" style="color: #c62828;"><?php echo h($error); ?></span>
            </div>
            <?php endif; ?>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <i class="mdui-icon material-icons">person</i>
                <label class="mdui-textfield-label">用户名</label>
                <input class="mdui-textfield-input" type="text" name="username" required autocomplete="username"/>
            </div>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <i class="mdui-icon material-icons">lock</i>
                <label class="mdui-textfield-label">密码</label>
                <input class="mdui-textfield-input" type="password" name="password" required autocomplete="current-password"/>
            </div>
            <label class="mdui-checkbox">
                <input type="checkbox" name="remember" value="1"/>
                <i class="mdui-checkbox-icon"></i>
                记住我
            </label>
        </div>
        <div class="mdui-card-actions">
            <button type="submit" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple mdui-btn-block">登录</button>
        </div>
    </form>
</div>
<script>
$(function() {
    mdui.mutation();
});
</script>
</body>
</html>