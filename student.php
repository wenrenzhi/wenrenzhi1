<?php
require_once __DIR__ . '/init.php';

if (!isInstalled()) {
    header('Location: install.php');
    exit;
}

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$db = getDB();

$stmt = $db->prepare("SELECT s.*, g.name AS group_name FROM students s LEFT JOIN `groups` g ON s.group_id = g.id WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student || $student['status'] == 0) {
    header('HTTP/1.0 404 Not Found');
    pageHeader('学生不存在');
    ?>
    <div class="mdui-toolbar mdui-color-theme" style="margin-bottom: 16px;">
        <?php renderSidebarToggle(); ?>
        <span class="mdui-typo-title">学生不存在</span>
        <div class="mdui-toolbar-spacer"></div>
        <a href="admin_login.php" class="mdui-btn mdui-ripple mdui-text-color-white">
            <i class="mdui-icon material-icons" style="vertical-align: middle;">admin_panel_settings</i>
            后台管理
        </a>
    </div>
    <div class="mdui-container" style="padding-top: 16px;">
        <div class="empty-state">
            <i class="mdui-icon material-icons">person_off</i>
            <p>该学生不存在或已被禁用</p>
            <a href="group_personal.php" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" style="margin-top: 16px;">返回组内个人榜</a>
        </div>
    </div>
    <?php
    pageFooter();
    exit;
}

$currentPeriod = getCurrentPeriod();
$currentStats = null;
if ($currentPeriod) {
    $currentStats = getStudentStats($studentId, $currentPeriod['id']);
    $currentStats['balance'] = floatval($currentPeriod['base_score']) + $currentStats['net'];
}

$periods = $db->query("SELECT * FROM periods ORDER BY start_time DESC")->fetchAll();
$history = [];
foreach ($periods as $p) {
    $stats = getStudentStats($studentId, $p['id']);
    $history[] = [
        'id' => $p['id'],
        'start_time' => $p['start_time'],
        'end_time' => $p['end_time'],
        'base_score' => floatval($p['base_score']),
        'status' => (int)$p['status'],
        'total_add' => $stats['total_add'],
        'total_deduct' => $stats['total_deduct'],
        'net' => $stats['net'],
        'balance' => floatval($p['base_score']) + $stats['net'],
    ];
}

$recStmt = $db->prepare("SELECT r.*, u.username AS operator_name
    FROM records r
    LEFT JOIN users u ON r.operator_id = u.id
    WHERE r.student_id = ? AND r.status = 1
    ORDER BY r.created_at DESC LIMIT 50");
$recStmt->execute([$studentId]);
$records = $recStmt->fetchAll();

$groupName = $student['group_name'] ?? '未分组';

pageHeader('学生详情');
?>

<div class="mdui-toolbar mdui-color-theme" style="margin-bottom: 16px;">
    <?php renderSidebarToggle(); ?>
    <span class="mdui-typo-title">学生详情</span>
    <div class="mdui-toolbar-spacer"></div>
    <a href="admin_login.php" class="mdui-btn mdui-ripple mdui-text-color-white">
        <i class="mdui-icon material-icons" style="vertical-align: middle;">admin_panel_settings</i>
        后台管理
    </a>
</div>
<div class="mdui-container" style="padding-top: 16px; padding-bottom: 16px;">

    <div class="mdui-card mdui-p-a-3" style="padding: 24px; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #e3f2fd; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #1976d2; font-weight: bold; flex-shrink: 0;">
                <?php echo h(mb_substr($student['name'], 0, 1)); ?>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <div class="mdui-typo-title" style="font-size: 22px; margin-bottom: 4px;"><?php echo h($student['name']); ?></div>
                <div class="mdui-typo-body-1" style="color: #757575;">
                    <i class="mdui-icon material-icons" style="font-size: 16px; vertical-align: middle;">group</i>
                    <?php echo h($groupName); ?>
                </div>
            </div>
            <?php if ($currentPeriod && $currentStats): ?>
            <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                <div class="card-stat">
                    <div class="stat-label">总加分</div>
                    <div class="stat-value score-positive">+<?php echo number_format($currentStats['total_add'], 1); ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-label">总扣分</div>
                    <div class="stat-value score-negative">-<?php echo number_format($currentStats['total_deduct'], 1); ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-label">净变动</div>
                    <?php
                    $net = $currentStats['net'];
                    $netClass = $net > 0 ? 'score-positive' : ($net < 0 ? 'score-negative' : 'score-zero');
                    $netPrefix = $net > 0 ? '+' : '';
                    ?>
                    <div class="stat-value <?php echo $netClass; ?>"><?php echo $netPrefix . number_format($net, 1); ?></div>
                </div>
                <div class="card-stat">
                    <div class="stat-label">结余</div>
                    <?php
                    $balance = $currentStats['balance'];
                    $balClass = $balance > 0 ? 'score-positive' : ($balance < 0 ? 'score-negative' : 'score-zero');
                    ?>
                    <div class="stat-value <?php echo $balClass; ?>"><?php echo number_format($balance, 1); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($history)): ?>
    <div class="mdui-card" style="overflow-x: auto; margin-bottom: 16px;">
        <div class="mdui-card-primary">
            <div class="mdui-card-primary-title">历史周期数据对比</div>
        </div>
        <div class="mdui-card-content" style="padding: 0;">
            <table class="mdui-table mdui-table-hoverable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>周期</th>
                        <th>总加分</th>
                        <th>总扣分</th>
                        <th>净变动</th>
                        <th>结余</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h):
                        $hNet = $h['net'];
                        $hNetClass = $hNet > 0 ? 'score-positive' : ($hNet < 0 ? 'score-negative' : 'score-zero');
                        $hNetPrefix = $hNet > 0 ? '+' : '';
                        $hBal = $h['balance'];
                        $hBalClass = $hBal > 0 ? 'score-positive' : ($hBal < 0 ? 'score-negative' : 'score-zero');
                        $periodLabel = date('Y-m-d', strtotime($h['start_time'])) . ' ~ ' . ($h['end_time'] ? date('Y-m-d', strtotime($h['end_time'])) : '至今');
                        if ($currentPeriod && $h['id'] == $currentPeriod['id']) $periodLabel .= ' [当前]';
                    ?>
                    <tr>
                        <td><?php echo h($periodLabel); ?></td>
                        <td class="score-positive">+<?php echo number_format($h['total_add'], 1); ?></td>
                        <td class="score-negative">-<?php echo number_format($h['total_deduct'], 1); ?></td>
                        <td class="<?php echo $hNetClass; ?>"><?php echo $hNetPrefix . number_format($hNet, 1); ?></td>
                        <td class="<?php echo $hBalClass; ?>"><?php echo number_format($hBal, 1); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="mdui-card" style="overflow-x: auto; margin-bottom: 16px;">
        <div class="mdui-card-primary">
            <div class="mdui-card-primary-title">加分扣分记录</div>
            <div class="mdui-card-primary-subtitle">最近 50 条记录</div>
        </div>
        <div class="mdui-card-content" style="padding: 0;">
            <?php if (empty($records)): ?>
            <div class="empty-state" style="padding: 40px 20px;">
                <i class="mdui-icon material-icons">inbox</i>
                <p>暂无数据</p>
            </div>
            <?php else: ?>
            <table class="mdui-table mdui-table-hoverable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>类型</th>
                        <th>分数</th>
                        <th>原因</th>
                        <th>操作人</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r):
                        $isAdd = $r['type'] === 'add';
                        $typeLabel = $isAdd ? '加分' : '扣分';
                        $typeClass = $isAdd ? 'score-positive' : 'score-negative';
                        $scoreDisplay = $isAdd ? '+' . number_format($r['score'], 1) : '-' . number_format($r['score'], 1);
                    ?>
                    <tr>
                        <td><?php echo h(smartTime($r['created_at'])); ?></td>
                        <td class="<?php echo $typeClass; ?>"><?php echo $typeLabel; ?></td>
                        <td class="<?php echo $typeClass; ?>"><?php echo $scoreDisplay; ?></td>
                        <td><?php echo h($r['reason']); ?></td>
                        <td><?php echo h($r['operator_name'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php pageFooter(); ?>