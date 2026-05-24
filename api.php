<?php
// 设置错误处理，确保总是返回 JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '服务器错误: ' . $error['message']]);
        exit;
    }
}
register_shutdown_function('handleFatalError');
set_exception_handler(function($e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
});

require_once __DIR__ . '/init.php';

if (!isInstalled()) {
    jsonError('系统未安装', 503);
}

// 自动数据库迁移
try {
    $db = getDB();
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) NULL DEFAULT NULL");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL");
} catch (Exception $e) {}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ============ 认证 ============
    case 'login':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        if (empty($username) || empty($password)) jsonError('请输入用户名和密码');
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || $user['password'] !== md5($password)) jsonError('用户名或密码错误');
        if ($user['status'] == 0) jsonError('账号已被禁用，请联系管理员');
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_super'] = (bool)$user['is_super'];
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['must_change_pwd'] = (bool)$user['must_change_pwd'];
        if ($remember) {
            try {
                $token = bin2hex(random_bytes(32));
                $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + 604800, '/', '', true, true);
            } catch (Exception $e) {
                $db->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL DEFAULT NULL");
                $token = bin2hex(random_bytes(32));
                $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + 604800, '/', '', true, true);
            }
        }
        logOperation($user['id'], 'login', ['username' => $username]);
        jsonResponse([
            'success' => true,
            'must_change_pwd' => (bool)$user['must_change_pwd'],
            'is_super' => (bool)$user['is_super']
        ]);

    case 'logout':
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        jsonResponse(['success' => true]);

    case 'change_password':
        requireLogin();
        $oldPwd = $_POST['old_password'] ?? '';
        $newPwd = $_POST['new_password'] ?? '';
        if (empty($oldPwd) || empty($newPwd)) jsonError('请填写完整');
        if (strlen($newPwd) < 6) jsonError('新密码至少6位');
        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user['password'] !== md5($oldPwd)) jsonError('原密码错误');
        $db->prepare("UPDATE users SET password = ?, must_change_pwd = 0, updated_at = NOW() WHERE id = ?")
            ->execute([md5($newPwd), $_SESSION['user_id']]);
        $_SESSION['must_change_pwd'] = false;
        logOperation($_SESSION['user_id'], 'change_password', []);
        jsonResponse(['success' => true]);

    case 'user_change_pwd_first':
        requireLogin();
        $newPwd = $_POST['new_password'] ?? '';
        if (empty($newPwd)) jsonError('请填写新密码');
        if (strlen($newPwd) < 6) jsonError('新密码至少6位');
        $db = getDB();
        $db->prepare("UPDATE users SET password = ?, must_change_pwd = 0, updated_at = NOW() WHERE id = ?")
            ->execute([md5($newPwd), $_SESSION['user_id']]);
        $_SESSION['must_change_pwd'] = false;
        logOperation($_SESSION['user_id'], 'change_password', ['first_login' => true]);
        jsonResponse(['success' => true]);

    // ============ 学生管理 ============
    case 'students_list':
        requireLogin();
        $search = $_GET['search'] ?? '';
        $group_id = $_GET['group_id'] ?? '';
        $status = $_GET['status'] ?? '1';
        $db = getDB();
        $where = [];
        $params = [];
        if ($status !== 'all') { $where[] = "s.status = ?"; $params[] = (int)$status; }
        if ($search) { $where[] = "s.name LIKE ?"; $params[] = "%$search%"; }
        if ($group_id !== '') { $where[] = "s.group_id = ?"; $params[] = (int)$group_id; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare("SELECT s.*, g.name AS group_name FROM students s LEFT JOIN `groups` g ON s.group_id = g.id $whereSQL ORDER BY s.id ASC");
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());

    case 'student_create':
        requirePermission('students');
        $names = trim($_POST['names'] ?? '');
        if (empty($names)) jsonError('请输入姓名');
        $nameArr = array_filter(explode("\n", str_replace("\r", "\n", $names)), function($n) { return trim($n) !== ''; });
        if (empty($nameArr)) jsonError('请输入有效姓名');
        $db = getDB();
        $count = 0;
        foreach ($nameArr as $name) {
            $name = trim($name);
            if (mb_strlen($name) > 50) continue;
            $db->prepare("INSERT INTO students (name, created_at) VALUES (?, NOW())")->execute([$name]);
            $count++;
        }
        logOperation($_SESSION['user_id'], 'student_create', ['names' => $nameArr, 'count' => $count]);
        jsonResponse(['success' => true, 'count' => $count]);

    case 'student_update':
        requirePermission('students');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $group_id = $_POST['group_id'] ?? null;
        if (empty($id) || empty($name)) jsonError('参数错误');
        if ($group_id === '' || $group_id === '0') $group_id = null;
        else $group_id = (int)$group_id;
        $db = getDB();
        $db->prepare("UPDATE students SET name = ?, group_id = ? WHERE id = ?")->execute([$name, $group_id, $id]);
        logOperation($_SESSION['user_id'], 'student_update', ['id' => $id, 'name' => $name, 'group_id' => $group_id]);
        jsonResponse(['success' => true]);

    case 'student_delete':
        requirePermission('students');
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $db->prepare("UPDATE students SET status = 0 WHERE id = ?")->execute([$id]);
        logOperation($_SESSION['user_id'], 'student_delete', ['id' => $id]);
        jsonResponse(['success' => true]);

    // ============ 小组管理 ============
    case 'groups_list':
        requireLogin();
        $search = $_GET['search'] ?? '';
        $db = getDB();
        if ($search) {
            $stmt = $db->prepare("SELECT * FROM `groups` WHERE name LIKE ? ORDER BY id ASC");
            $stmt->execute(["%$search%"]);
        } else {
            $stmt = $db->query("SELECT * FROM `groups` ORDER BY id ASC");
        }
        $groups = $stmt->fetchAll();
        foreach ($groups as &$g) {
            $cnt = $db->prepare("SELECT COUNT(*) AS cnt FROM students WHERE group_id = ? AND status = 1");
            $cnt->execute([$g['id']]);
            $g['member_count'] = (int)$cnt->fetch()['cnt'];
        }
        jsonResponse($groups);

    case 'group_create':
        requirePermission('groups');
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) jsonError('请输入组名');
        $db = getDB();
        $db->prepare("INSERT INTO `groups` (name, created_at) VALUES (?, NOW())")->execute([$name]);
        logOperation($_SESSION['user_id'], 'group_create', ['name' => $name]);
        jsonResponse(['success' => true]);

    case 'group_update':
        requirePermission('groups');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (empty($id) || empty($name)) jsonError('参数错误');
        $db = getDB();
        $db->prepare("UPDATE `groups` SET name = ? WHERE id = ?")->execute([$name, $id]);
        logOperation($_SESSION['user_id'], 'group_update', ['id' => $id, 'name' => $name]);
        jsonResponse(['success' => true]);

    case 'group_delete':
        requirePermission('groups');
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $db->prepare("UPDATE students SET group_id = NULL WHERE group_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM `groups` WHERE id = ?")->execute([$id]);
        logOperation($_SESSION['user_id'], 'group_delete', ['id' => $id]);
        jsonResponse(['success' => true]);

    case 'group_add_members':
        requirePermission('groups');
        $group_id = (int)($_POST['group_id'] ?? 0);
        $student_ids = $_POST['student_ids'] ?? '';
        if (empty($group_id) || empty($student_ids)) jsonError('参数错误');
        $ids = array_map('intval', explode(',', $student_ids));
        $db = getDB();
        foreach ($ids as $sid) {
            $db->prepare("UPDATE students SET group_id = ? WHERE id = ?")->execute([$group_id, $sid]);
        }
        logOperation($_SESSION['user_id'], 'group_add_members', ['group_id' => $group_id, 'student_ids' => $ids]);
        jsonResponse(['success' => true]);

    case 'group_members':
        requireLogin();
        $group_id = (int)($_GET['group_id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM students WHERE group_id = ? AND status = 1 ORDER BY name ASC");
        $stmt->execute([$group_id]);
        jsonResponse($stmt->fetchAll());

    case 'ungrouped_students':
        requireLogin();
        $db = getDB();
        $stmt = $db->query("SELECT * FROM students WHERE group_id IS NULL AND status = 1 ORDER BY name ASC");
        jsonResponse($stmt->fetchAll());

    // ============ 统计周期 ============
    case 'periods_list':
        requireLogin();
        $db = getDB();
        $stmt = $db->query("SELECT * FROM periods ORDER BY start_time DESC");
        $periods = $stmt->fetchAll();
        foreach ($periods as &$p) {
            $p['can_end'] = ($p['status'] == 1);
            $p['days'] = getPeriodDays($p);
        }
        jsonResponse($periods);

    case 'period_create':
        requirePermission('periods');
        $start_time = $_POST['start_time'] ?? '';
        $base_score = floatval($_POST['base_score'] ?? 0);
        $remark = trim($_POST['remark'] ?? '');
        if (empty($start_time)) jsonError('请选择开始时间');
        
        // 转换日期格式从 datetime-local 到 MySQL datetime
        if (strpos($start_time, 'T') !== false) {
            $start_time = str_replace('T', ' ', $start_time);
        }
        
        $db = getDB();
        $current = $db->query("SELECT id FROM periods WHERE status = 1 LIMIT 1")->fetch();
        if ($current) jsonError('当前有未结束的统计周期，请先结束');
        
        $stmt = $db->prepare("SELECT id FROM periods WHERE start_time <= ? AND (end_time >= ? OR end_time IS NULL) LIMIT 1");
        $stmt->execute([$start_time, $start_time]);
        if ($stmt->fetch()) jsonError('该时间段与已有周期重叠');
        
        $db->prepare("INSERT INTO periods (start_time, base_score, remark, status, created_at) VALUES (?, ?, ?, 1, NOW())")
            ->execute([$start_time, $base_score, $remark]);
        logOperation($_SESSION['user_id'], 'period_create', ['start_time' => $start_time, 'base_score' => $base_score]);
        jsonResponse(['success' => true]);

    case 'period_end':
        requirePermission('periods');
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $period = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $period->execute([$id]);
        $period = $period->fetch();
        if (!$period) jsonError('周期不存在');
        if ($period['status'] == 0) jsonError('该周期已结束');
        $db->prepare("UPDATE periods SET end_time = NOW(), status = 0 WHERE id = ?")->execute([$id]);
        logOperation($_SESSION['user_id'], 'period_end', ['id' => $id]);
        jsonResponse(['success' => true]);

    case 'period_update':
        requirePermission('periods');
        $id = (int)($_POST['id'] ?? 0);
        $remark = trim($_POST['remark'] ?? '');
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $db->prepare("UPDATE periods SET remark = ? WHERE id = ?")->execute([$remark, $id]);
        logOperation($_SESSION['user_id'], 'period_update', ['id' => $id, 'remark' => $remark]);
        jsonResponse(['success' => true]);

    // ============ 记录管理 ============
    case 'records_list':
        requireLogin();
        $student_id = $_GET['student_id'] ?? '';
        $period_id = $_GET['period_id'] ?? '';
        $type = $_GET['type'] ?? '';
        $order = $_GET['order'] ?? 'DESC';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $db = getDB();
        $where = ["r.status = 1"];
        $params = [];
        if ($student_id) { $where[] = "r.student_id = ?"; $params[] = (int)$student_id; }
        if ($period_id) { $where[] = "r.period_id = ?"; $params[] = (int)$period_id; }
        if ($type) { $where[] = "r.type = ?"; $params[] = $type; }
        $whereSQL = implode(' AND ', $where);
        $orderDir = ($order === 'ASC') ? 'ASC' : 'DESC';
        $countStmt = $db->prepare("SELECT COUNT(*) FROM records r WHERE $whereSQL");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("SELECT r.*, s.name AS student_name, u.username AS operator_name 
            FROM records r 
            LEFT JOIN students s ON r.student_id = s.id 
            LEFT JOIN users u ON r.operator_id = u.id 
            WHERE $whereSQL 
            ORDER BY r.created_at $orderDir 
            LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        jsonResponse(['total' => (int)$total, 'page' => $page, 'perPage' => $perPage, 'data' => $stmt->fetchAll()]);

    case 'record_create':
        requirePermission('scoring');
        $student_ids = $_POST['student_ids'] ?? '';
        $period_id = (int)($_POST['period_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $score = floatval($_POST['score'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $extra_note = trim($_POST['extra_note'] ?? '');
        if (empty($student_ids) || empty($period_id) || empty($type) || $score <= 0) jsonError('参数错误');
        if (!in_array($type, ['add', 'deduct'])) jsonError('类型错误');
        $ids = array_map('intval', explode(',', $student_ids));
        $db = getDB();
        foreach ($ids as $sid) {
            $db->prepare("INSERT INTO records (student_id, period_id, type, score, reason, extra_note, operator_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$sid, $period_id, $type, $score, $reason, $extra_note, $_SESSION['user_id']]);
        }
        logOperation($_SESSION['user_id'], 'record_create', [
            'student_ids' => $ids, 'period_id' => $period_id, 'type' => $type,
            'score' => $score, 'reason' => $reason
        ]);
        jsonResponse(['success' => true, 'count' => count($ids)]);

    case 'record_update':
        requirePermission('scoring');
        $id = (int)($_POST['id'] ?? 0);
        $score = floatval($_POST['score'] ?? 0);
        $type = $_POST['type'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        if (empty($id) || $score <= 0 || !in_array($type, ['add', 'deduct'])) jsonError('参数错误');
        $db = getDB();
        $db->prepare("UPDATE records SET score = ?, type = ?, reason = ? WHERE id = ?")
            ->execute([$score, $type, $reason, $id]);
        logOperation($_SESSION['user_id'], 'record_update', ['id' => $id, 'score' => $score, 'type' => $type]);
        jsonResponse(['success' => true]);

    case 'record_delete':
        requirePermission('scoring');
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $db->prepare("UPDATE records SET status = 0 WHERE id = ?")->execute([$id]);
        logOperation($_SESSION['user_id'], 'record_delete', ['id' => $id]);
        jsonResponse(['success' => true]);

    case 'record_restore':
        requirePermission('scoring');
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $db->prepare("UPDATE records SET status = 1 WHERE id = ?")->execute([$id]);
        logOperation($_SESSION['user_id'], 'record_restore', ['id' => $id]);
        jsonResponse(['success' => true]);

    // ============ 用户管理 ============
    case 'users_list':
        requireSuperAdmin();
        $db = getDB();
        $stmt = $db->query("SELECT id, username, is_super, permissions, must_change_pwd, status, created_at FROM users ORDER BY id ASC");
        jsonResponse($stmt->fetchAll());

    case 'user_create':
        requireSuperAdmin();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = $_POST['permissions'] ?? '{}';
        if (empty($username) || empty($password)) jsonError('请填写完整');
        $db = getDB();
        $exists = $db->prepare("SELECT id FROM users WHERE username = ?");
        $exists->execute([$username]);
        if ($exists->fetch()) jsonError('用户名已存在');
        $db->prepare("INSERT INTO users (username, password, is_super, permissions, must_change_pwd, status, created_at) VALUES (?, ?, 0, ?, 1, 1, NOW())")
            ->execute([$username, md5($password), $permissions]);
        logOperation($_SESSION['user_id'], 'user_create', ['username' => $username]);
        jsonResponse(['success' => true]);

    case 'user_update':
        requireSuperAdmin();
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $permissions = $_POST['permissions'] ?? '';
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $user = $db->prepare("SELECT is_super FROM users WHERE id = ?");
        $user->execute([$id]);
        $user = $user->fetch();
        if (!$user) jsonError('用户不存在');
        if ($user['is_super']) jsonError('不能修改超级管理员');
        $updates = [];
        $params = [];
        if ($status !== '') { $updates[] = "status = ?"; $params[] = (int)$status; }
        if ($permissions !== '') { $updates[] = "permissions = ?"; $params[] = $permissions; }
        if ($updates) {
            $updates[] = "updated_at = NOW()";
            $params[] = $id;
            $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        }
        logOperation($_SESSION['user_id'], 'user_update', ['id' => $id]);
        jsonResponse(['success' => true]);

    case 'user_reset_password':
        requireSuperAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $user = $db->prepare("SELECT is_super FROM users WHERE id = ?");
        $user->execute([$id]);
        $user = $user->fetch();
        if (!$user) jsonError('用户不存在');
        if ($user['is_super']) jsonError('不能重置超级管理员密码');
        $newPwd = substr(bin2hex(random_bytes(4)), 0, 8);
        $db->prepare("UPDATE users SET password = ?, must_change_pwd = 1 WHERE id = ?")
            ->execute([md5($newPwd), $id]);
        logOperation($_SESSION['user_id'], 'user_reset_password', ['id' => $id]);
        jsonResponse(['success' => true, 'new_password' => $newPwd]);

    case 'user_delete':
        requireSuperAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) jsonError('参数错误');
        $db = getDB();
        $user = $db->prepare("SELECT is_super FROM users WHERE id = ?");
        $user->execute([$id]);
        $user = $user->fetch();
        if (!$user) jsonError('用户不存在');
        if ($user['is_super']) jsonError('不能删除超级管理员');
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        logOperation($_SESSION['user_id'], 'user_delete', ['id' => $id]);
        jsonResponse(['success' => true]);

    // ============ 操作日志 ============
    case 'operations_list':
        requirePermission('logs');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $db = getDB();
        $count = $db->query("SELECT COUNT(*) FROM operation_logs")->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("SELECT o.*, u.username FROM operation_logs o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute();
        jsonResponse(['total' => (int)$count, 'page' => $page, 'perPage' => $perPage, 'data' => $stmt->fetchAll()]);

    // ============ 公开接口 ============
    case 'public_overview':
        $db = getDB();
        $period = getCurrentPeriod();
        if (!$period) jsonResponse(['has_period' => false]);
        $baseScore = floatval($period['base_score']);
        $periodId = $period['id'];
        $stmt = $db->prepare("SELECT 
            COALESCE(SUM(CASE WHEN type = 'add' AND status = 1 THEN score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN type = 'deduct' AND status = 1 THEN score ELSE 0 END), 0) AS total_deduct
            FROM records WHERE period_id = ? AND status = 1");
        $stmt->execute([$periodId]);
        $totals = $stmt->fetch();
        $totalAdd = floatval($totals['total_add']);
        $totalDeduct = floatval($totals['total_deduct']);
        $netChange = $totalAdd - $totalDeduct;
        // Personal top 1
        $topStudent = $db->prepare("SELECT s.id, s.name,
            COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct
            FROM students s
            LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
            WHERE s.status = 1
            GROUP BY s.id
            ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC
            LIMIT 1");
        $topStudent->execute([$periodId]);
        $topStudent = $topStudent->fetch();
        // Group top 1
        $topGroup = $db->prepare("SELECT g.id, g.name,
            COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct,
            COUNT(DISTINCT s.id) AS member_count
            FROM `groups` g
            LEFT JOIN students s ON s.group_id = g.id AND s.status = 1
            LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
            GROUP BY g.id
            HAVING member_count > 0
            ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC
            LIMIT 1");
        $topGroup->execute([$periodId]);
        $topGroup = $topGroup->fetch();
        jsonResponse([
            'has_period' => true,
            'period' => $period,
            'total_add' => $totalAdd,
            'total_deduct' => $totalDeduct,
            'net_change' => $netChange,
            'top_student' => $topStudent ?: null,
            'top_group' => $topGroup ?: null,
            'base_score' => $baseScore
        ]);

    case 'public_personal_ranking':
        $periodId = (int)($_GET['period_id'] ?? 0);
        $search = $_GET['search'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        if (empty($periodId)) {
            $current = getCurrentPeriod();
            if (!$current) jsonResponse(['has_period' => false]);
            $periodId = $current['id'];
        }
        $db = getDB();
        $period = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $period->execute([$periodId]);
        $period = $period->fetch();
        if (!$period) jsonError('周期不存在');
        $baseScore = floatval($period['base_score']);
        $where = "s.status = 1";
        $params = [$periodId];
        if ($search) { $where .= " AND s.name LIKE ?"; $params[] = "%$search%"; }
        $countSQL = "SELECT COUNT(*) FROM students s WHERE $where";
        $countStmt = $db->prepare($countSQL);
        $countStmt->execute(array_slice($params, 1));
        $total = $countStmt->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT s.id, s.name, g.name AS group_name,
            COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct
            FROM students s
            LEFT JOIN `groups` g ON s.group_id = g.id
            LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
            WHERE $where
            GROUP BY s.id
            ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC
            LIMIT $perPage OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        foreach ($data as &$row) {
            $row['net'] = floatval($row['total_add']) - floatval($row['total_deduct']);
            $row['balance'] = $baseScore + $row['net'];
            $row['total_add'] = floatval($row['total_add']);
            $row['total_deduct'] = floatval($row['total_deduct']);
        }
        jsonResponse([
            'has_period' => true,
            'period' => $period,
            'base_score' => $baseScore,
            'total' => (int)$total,
            'page' => $page,
            'perPage' => $perPage,
            'data' => $data
        ]);

    case 'public_group_ranking':
        $periodId = (int)($_GET['period_id'] ?? 0);
        if (empty($periodId)) {
            $current = getCurrentPeriod();
            if (!$current) jsonResponse(['has_period' => false]);
            $periodId = $current['id'];
        }
        $db = getDB();
        $period = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $period->execute([$periodId]);
        $period = $period->fetch();
        if (!$period) jsonError('周期不存在');
        $stmt = $db->prepare("SELECT g.id, g.name,
            COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct,
            COUNT(DISTINCT s.id) AS member_count
            FROM `groups` g
            LEFT JOIN students s ON s.group_id = g.id AND s.status = 1
            LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
            GROUP BY g.id
            HAVING member_count > 0
            ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC");
        $stmt->execute([$periodId]);
        $data = $stmt->fetchAll();
        foreach ($data as &$row) {
            $row['net'] = floatval($row['total_add']) - floatval($row['total_deduct']);
            $row['total_add'] = floatval($row['total_add']);
            $row['total_deduct'] = floatval($row['total_deduct']);
            $row['member_count'] = (int)$row['member_count'];
        }
        jsonResponse(['has_period' => true, 'period' => $period, 'data' => $data]);

    case 'public_group_personal_ranking':
        $periodId = (int)($_GET['period_id'] ?? 0);
        $groupId = (int)($_GET['group_id'] ?? 0);
        $search = $_GET['search'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        if (empty($periodId)) {
            $current = getCurrentPeriod();
            if (!$current) jsonResponse(['has_period' => false]);
            $periodId = $current['id'];
        }
        if (empty($groupId)) jsonError('请选择小组');
        $db = getDB();
        $period = $db->prepare("SELECT * FROM periods WHERE id = ?");
        $period->execute([$periodId]);
        $period = $period->fetch();
        if (!$period) jsonError('周期不存在');
        $baseScore = floatval($period['base_score']);
        $where = "s.group_id = ? AND s.status = 1";
        $params = [$groupId, $periodId];
        if ($search) { $where .= " AND s.name LIKE ?"; $params[] = "%$search%"; }
        $countSQL = "SELECT COUNT(*) FROM students s WHERE $where";
        $countStmt = $db->prepare($countSQL);
        $countStmt->execute([$groupId]);  // Only need group_id for count
        $total = $countStmt->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT s.id, s.name,
            COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct
            FROM students s
            LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
            WHERE $where
            GROUP BY s.id
            ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC
            LIMIT $perPage OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        foreach ($data as &$row) {
            $row['net'] = floatval($row['total_add']) - floatval($row['total_deduct']);
            $row['balance'] = $baseScore + $row['net'];
            $row['total_add'] = floatval($row['total_add']);
            $row['total_deduct'] = floatval($row['total_deduct']);
        }
        jsonResponse(['has_period' => true, 'period' => $period, 'base_score' => $baseScore, 'total' => (int)$total, 'page' => $page, 'perPage' => $perPage, 'data' => $data]);

    case 'public_student_detail':
        $studentId = (int)($_GET['student_id'] ?? 0);
        if (empty($studentId)) jsonError('参数错误');
        $db = getDB();
        $student = $db->prepare("SELECT s.*, g.name AS group_name FROM students s LEFT JOIN `groups` g ON s.group_id = g.id WHERE s.id = ?");
        $student->execute([$studentId]);
        $student = $student->fetch();
        if (!$student || $student['status'] == 0) jsonError('学生不存在');
        $periods = $db->query("SELECT * FROM periods ORDER BY start_time DESC")->fetchAll();
        $history = [];
        foreach ($periods as $p) {
            $stats = getStudentStats($studentId, $p['id']);
            $history[] = [
                'period_id' => $p['id'],
                'start_time' => $p['start_time'],
                'end_time' => $p['end_time'],
                'base_score' => floatval($p['base_score']),
                'status' => (int)$p['status'],
                'total_add' => $stats['total_add'],
                'total_deduct' => $stats['total_deduct'],
                'net' => $stats['net'],
                'balance' => floatval($p['base_score']) + $stats['net']
            ];
        }
        $records = $db->prepare("SELECT r.*, u.username AS operator_name 
            FROM records r LEFT JOIN users u ON r.operator_id = u.id 
            WHERE r.student_id = ? AND r.status = 1 
            ORDER BY r.created_at DESC LIMIT 50");
        $records->execute([$studentId]);
        $records = $records->fetchAll();
        jsonResponse(['student' => $student, 'history' => $history, 'records' => $records]);

    case 'public_period_list':
        $db = getDB();
        $periods = $db->query("SELECT id, start_time, end_time, status FROM periods ORDER BY start_time DESC")->fetchAll();
        jsonResponse($periods);

    case 'public_group_list':
        $db = getDB();
        $groups = $db->query("SELECT id, name FROM `groups` ORDER BY name ASC")->fetchAll();
        jsonResponse($groups);

    default:
        jsonError('未知操作', 404);
}