<?php
require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_super'] = $user['is_super'];
            $_SESSION['must_change_pwd'] = $user['must_change_pwd'];

            logOperation($user['id'], '登录', $_SERVER['REMOTE_ADDR'] ?? '');

            if ($user['must_change_pwd']) {
                header('Location: index.php?page=dashboard');
            } else {
                header('Location: index.php?page=dashboard');
            }
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - <?php echo defined('SITE_TITLE') ? h(SITE_TITLE) : '班级操行分'; ?></title>
    <link rel="stylesheet" href="https://cdn.hoha.top/mdui-v1.0.2/css/mdui.min.css">
    <script src="https://cdn.hoha.top/js/jquery-4.0.0.min.js"></script>
    <script src="https://cdn.hoha.top/mdui-v1.0.2/js/mdui.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; padding: 48px 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 100%; max-width: 400px; }
        .login-card h2 { text-align: center; margin: 0 0 32px 0; font-size: 28px; color: #333; }
        .login-card .mdui-textfield { margin-bottom: 8px; }
        .login-card .mdui-btn { width: 100%; margin-top: 16px; }
        .back-link { text-align: center; margin-top: 24px; }
        .back-link a { color: rgba(255,255,255,0.9); text-decoration: none; font-size: 14px; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body class="mdui-theme-primary-indigo">
<div class="login-card mdui-shadow-5">
    <h2>后台登录</h2>
    <?php if (!empty($error)): ?>
    <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 16px; text-align: center;">
        <?php echo h($error); ?>
    </div>
    <?php endif; ?>
    <form method="POST">
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">用户名</label>
            <input class="mdui-textfield-input" type="text" name="username" required autofocus/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">密码</label>
            <input class="mdui-textfield-input" type="password" name="password" required/>
        </div>
        <button type="submit" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple">登 录</button>
    </form>
</div>
<div class="back-link">
    <a href="../index.php">← 返回首页</a>
</div>
</body>
</html>
