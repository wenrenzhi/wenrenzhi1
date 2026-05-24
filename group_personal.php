<?php
require_once __DIR__ . '/init.php';

if (!isInstalled()) {
    header('Location: install.php');
    exit;
}

$db = getDB();

$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$periodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)$_GET['page'] ?? 1);
$perPage = 15;

// 先获取所有周期，用于判断是否有历史周期
$allPeriods = $db->query("SELECT * FROM periods ORDER BY start_time DESC")->fetchAll();

if (empty($periodId)) {
    // 先尝试获取当前周期
    $currentPeriod = getCurrentPeriod();
    if ($currentPeriod) {
        $periodId = $currentPeriod['id'];
    } elseif (!empty($allPeriods)) {
        // 如果没有当前周期，就选择第一个历史周期
        $periodId = $allPeriods[0]['id'];
    }
}

$period = null;
$baseScore = 0;
if ($periodId) {
    $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $period = $stmt->fetch();
    if ($period) {
        $baseScore = floatval($period['base_score']);
    }
}

$groups = $db->query("SELECT id, name FROM `groups` ORDER BY name ASC")->fetchAll();

// 如果没有小组，则跳过选择逻辑
if (!empty($groups)) {
    // 如果没有选中小组，或者选中的小组不存在，则默认选择第一个
    if (!$groupId || !in_array($groupId, array_column($groups, 'id'))) {
        $groupId = $groups[0]['id'];
    }
}

$data = [];
$total = 0;

if ($groupId && $periodId) {
    $where = "s.group_id = ? AND s.status = 1";
    $countParams = [$groupId];
    $queryParams = [$periodId, $groupId];
    if ($search) {
        $where .= " AND s.name LIKE ?";
        $countParams[] = "%$search%";
        $queryParams[] = "%$search%";
    }

    $countSQL = "SELECT COUNT(*) FROM students s WHERE $where";
    $countStmt = $db->prepare($countSQL);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT s.id, s.name,
        COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
        COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct
        FROM students s
        LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
        WHERE $where
        GROUP BY s.id
        ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC, s.name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($queryParams);
    $allData = $stmt->fetchAll();

    $rankedData = [];
    $currentRank = 1;
    $prevNet = null;
    $prevDisplayRank = null;
    $i = 0;

    foreach ($allData as $row) {
        $net = floatval($row['total_add']) - floatval($row['total_deduct']);
        $displayRank = null;
        if ($net !== 0) {
            if ($prevNet === null || $net !== $prevNet) {
                $displayRank = $currentRank;
            } else {
                $displayRank = $prevDisplayRank;
            }
            $prevNet = $net;
            $prevDisplayRank = $displayRank;
        }

        $rankedData[] = [
            'data' => $row,
            'net' => $net,
            'displayRank' => $displayRank,
            'absoluteRank' => $i + 1,
            'balance' => $baseScore + $net
        ];
        $currentRank++;
        $i++;
    }

    $offset = ($page - 1) * $perPage;
    $data = array_slice($rankedData, $offset, $perPage);
}

$selectedGroupName = '';
foreach ($groups as $g) {
    if ($g['id'] == $groupId) {
        $selectedGroupName = $g['name'];
        break;
    }
}

$urlPattern = 'group_personal.php?group_id=' . $groupId . '&period_id=' . $periodId . '&search=' . urlencode($search) . '&page={page}';

pageHeader('组内个人榜');
?>

<div class="mdui-toolbar mdui-color-theme" style="margin-bottom: 16px;">
    <?php renderSidebarToggle(); ?>
    <span class="mdui-typo-title">组内个人榜</span>
    <div class="mdui-toolbar-spacer"></div>
    <a href="admin_login.php" class="mdui-btn mdui-ripple mdui-text-color-white">
        <i class="mdui-icon material-icons" style="vertical-align: middle;">admin_panel_settings</i>
        后台管理
    </a>
</div>
<div class="mdui-container" style="padding-top: 16px; padding-bottom: 16px;">

<?php if (empty($allPeriods)): ?>
    <div class="empty-state">
        <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">info</i>
        <p style="font-size: 18px; margin-top: 16px;">暂无统计周期</p>
        <p style="font-size: 14px; color: #999;">请等待管理员创建统计周期</p>
    </div>
<?php else: ?>

    <div class="mdui-typo-caption" style="margin-bottom: 16px; color: #666;">
        <i class="mdui-icon material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
        提示：您可以通过上方的下拉菜单查看历史统计周期的数据
    </div>

    <form method="GET" action="group_personal.php" class="mdui-card mdui-p-a-3" style="padding: 16px; margin-bottom: 16px;">
        <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;">
            <?php if (!empty($groups)): ?>
            <div class="mdui-textfield mdui-textfield-floating-label" style="min-width: 200px;">
                <i class="mdui-icon material-icons" style="position:absolute;top:50%;transform:translateY(-50%);">group</i>
                <select class="mdui-select" name="group_id" id="group_id" onchange="this.form.submit()" style="padding-left:32px;">
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $groupId == $g['id'] ? 'selected' : ''; ?>><?php echo h($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>选择小组</label>
            </div>
            <?php endif; ?>

            <?php renderPeriodSelector('period_id'); ?>

            <div class="mdui-textfield mdui-textfield-floating-label" style="min-width: 160px; flex: 1;">
                <i class="mdui-icon material-icons" style="position:absolute;top:50%;transform:translateY(-50%);">search</i>
                <input class="mdui-textfield-input" type="text" name="search" value="<?php echo h($search); ?>" placeholder="搜索姓名" style="padding-left:32px;"/>
                <label>搜索</label>
            </div>

            <button type="submit" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" style="margin-bottom: 8px;">
                <i class="mdui-icon material-icons">search</i> 查询
            </button>
        </div>
    </form>

    <?php if (empty($groups)): ?>
    <div class="empty-state">
        <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">group_off</i>
        <p style="font-size: 18px; margin-top: 16px;">暂无小组</p>
        <p style="font-size: 14px; color: #999;">请先创建小组</p>
    </div>
    <?php elseif (!$period): ?>
    <div class="empty-state">
        <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">error_outline</i>
        <p style="font-size: 18px; margin-top: 16px;">未找到该周期</p>
        <p style="font-size: 14px; color: #999;">请从上方下拉菜单选择有效的统计周期</p>
    </div>
    <?php elseif (empty($data)): ?>
    <div class="empty-state">
        <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">person_off</i>
        <p style="font-size: 18px; margin-top: 16px;">暂无学生数据</p>
    </div>
    <?php else: ?>
    <div class="mdui-card" style="overflow-x: auto;">
        <div class="mdui-card-content" style="padding: 0;">
            <table class="mdui-table mdui-table-hoverable" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 60px;">排名</th>
                        <th>姓名</th>
                        <th>净变动</th>
                        <th>总加分</th>
                        <th>总扣分</th>
                        <th>结余</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $item):
                        $row = $item['data'];
                        $net = $item['net'];
                        $displayRank = $item['displayRank'];
                        $absoluteRank = $item['absoluteRank'];
                        $balance = $item['balance'];
                        $medal = '';
                        $badge = '';
                        if ($absoluteRank == 1 && $net !== 0) { $medal = '🥇'; $badge = ' <span class="rank-badge rank-mvp">MVP</span>'; }
                        elseif ($absoluteRank == 2 && $net !== 0) { $medal = '🥈'; }
                        elseif ($absoluteRank == 3 && $net !== 0) { $medal = '🥉'; }
                        $scoreClass = $net > 0 ? 'score-positive' : ($net < 0 ? 'score-negative' : 'score-zero');
                        $netPrefix = $net > 0 ? '+' : '';
                    ?>
                    <tr>
                        <td><?php echo $medal; ?><?php echo $displayRank !== null ? $displayRank : ''; ?><?php echo $badge; ?></td>
                        <td><a href="student.php?id=<?php echo $row['id']; ?>"><?php echo h($row['name']); ?></a></td>
                        <td class="<?php echo $scoreClass; ?>"><?php echo $netPrefix . number_format($net, 1); ?></td>
                        <td class="score-positive"><?php echo floatval($row['total_add']) > 0 ? '+' . number_format(floatval($row['total_add']), 1) : number_format(floatval($row['total_add']), 1); ?></td>
                        <td class="score-negative"><?php echo floatval($row['total_deduct']) > 0 ? '-' . number_format(floatval($row['total_deduct']), 1) : number_format(floatval($row['total_deduct']), 1); ?></td>
                        <td class="<?php echo $net > 0 ? 'score-positive' : ($net < 0 ? 'score-negative' : 'score-zero'); ?>">
                            <?php echo $net > 0 ? '+' . number_format($balance, 1) : number_format($balance, 1); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div style="padding: 8px 16px; font-size: 13px; color: #757575;">
        排名规则：按净变动升序排列（净变动越小，排名越高）
    </div>

    <?php renderPagination($total, $page, $perPage, $urlPattern); ?>
    <?php endif; ?>

    <?php if ($period && $period['remark']): ?>
    <div class="period-info" style="margin-top: 16px;">
        <strong>周期备注：</strong><?php echo h($period['remark']); ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<?php pageFooter(); ?>