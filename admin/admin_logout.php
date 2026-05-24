<?php
require_once __DIR__ . '/init.php';
session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
header('Location: admin_login.php');
exit;