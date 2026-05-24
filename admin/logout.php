<?php
require_once __DIR__ . '/../init.php';

if (!empty($_SESSION['user_id'])) {
    logOperation($_SESSION['user_id'], '退出登录', $_SERVER['REMOTE_ADDR'] ?? '');
}

session_destroy();
header('Location: login.php');
exit;
?>
