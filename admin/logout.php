<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    logOperation($_SESSION['user_id'], '退出登录', $_SERVER['REMOTE_ADDR'] ?? '');
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: admin_login.php');
exit;
?>
