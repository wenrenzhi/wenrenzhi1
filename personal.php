<?php
require_once __DIR__ . '/init.php';

if (!isInstalled()) {
    header('Location: install.php');
    exit;
}

$db = getDB();
$periodId = (int)($_GET['period_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// 先获取所有周期，用于判断是否有历史周期
$allPeriods = $db->query("SELECT * FROM periods ORDER BY start_time DESC")->fetchAll();

if (empty($periodId)) {
    // 先尝试获取当前周期
    $current = getCurrentPeriod();
    if ($current) {
        $periodId = $current['id'];
    } elseif (!empty($allPeriods)) {
        // 如果没有当前周期，就选择第一个历史周期
        $periodId = $allPeriods[0]['id'];
    }
}

$period = null;
$baseScore = 0;
$data = [];
$total = 0;

if ($periodId) {
    $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $period = $stmt->fetch();

    if ($period) {
        $baseScore = floatval($period['base_score']);

        $where = "s.status = 1";
        $params = [$periodId];
        if ($search) {
            $where .= " AND s.name LIKE ?";
            $params[] = "%$search%";
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM students s WHERE $where");
        $countStmt->execute(array_slice($params, 1));
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT s.id, s.name, g.name AS group_name,
            COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct
            FROM students s
            LEFT JOIN `groups` g ON s.group_id = g.id
            LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
            WHERE $where
            GROUP BY s.id
            ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC, s.name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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
                'absoluteRank' => $i + 1
            ];
            $currentRank++;
            $i++;
        }

        $offset = ($page - 1) * $perPage;
        $data = array_slice($rankedData, $offset, $perPage);
    }
}

pageHeader("个人榜");
?>

<div class="mdui-toolbar mdui-color-theme">
    <?php renderSidebarToggle(); ?>
    <span class="mdui-typo-title">个人榜</span>
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

    <form method="get" action="personal.php" class="mdui-row" style="margin-bottom: 16px;">
        <div class="mdui-col-xs-12 mdui-col-sm-4">
            <?php renderPeriodSelector('period_id'); ?>
        </div>
        <div class="mdui-col-xs-12 mdui-col-sm-4">
            <div class="mdui-textfield mdui-textfield-floating-label">
                <i class="mdui-icon material-icons" style="position:absolute;top:50%;transform:translateY(-50%);">search</i>
                <input class="mdui-textfield-input" type="text" name="search" value="<?php echo h($search); ?>" placeholder="搜索姓名" style="padding-left:32px;"/>
                <label>搜索</label>
            </div>
        </div>
        <div class="mdui-col-xs-12 mdui-col-sm-2" style="display:flex;align-items:center;padding-top:8px;">
            <button type="submit" class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple">查询</button>
            <?php if ($search): ?>
                <a href="personal.php?period_id=<?php echo $periodId; ?>" class="mdui-btn mdui-ripple" style="margin-left:8px;">清除</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$period): ?>
        <div class="empty-state">
            <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">error_outline</i>
            <p style="font-size: 18px; margin-top: 16px;">未找到该周期</p>
            <p style="font-size: 14px; color: #999;">请从上方下拉菜单选择有效的统计周期</p>
        </div>
    <?php elseif (empty($data)): ?>
        <div class="empty-state">
            <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">people</i>
            <p style="font-size: 18px; margin-top: 16px;">暂无数据</p>
            <?php if ($search): ?>
                <p style="font-size: 14px; color: #999;">未找到匹配 "<?php echo h($search); ?>" 的学生</p>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <div class="mdui-typo-caption" style="margin-bottom: 8px; color: #666;">
            <i class="mdui-icon material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
            排名规则：净变动 = 加分 - 扣分；净变动越小排名越靠前（负分表示扣分多于加分，排名优先）
        </div>

        <div class="mdui-table-fluid">
            <table class="mdui-table mdui-table-hoverable">
                <thead>
                    <tr>
                        <th style="width: 60px;">排名</th>
                        <th>姓名</th>
                        <th class="mdui-text-right">净变动</th>
                        <th class="mdui-text-right">总加分</th>
                        <th class="mdui-text-right">总扣分</th>
                        <th class="mdui-text-right">结余</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $item):
                        $row = $item['data'];
                        $net = $item['net'];
                        $displayRank = $item['displayRank'];
                        $absoluteRank = $item['absoluteRank'];
                        $balance = $baseScore + $net;
                        $netClass = $net > 0 ? 'mdui-text-color-green' : ($net < 0 ? 'mdui-text-color-blue' : 'mdui-text-color-black');
                        $medal = '';
                        if ($absoluteRank === 1 && $net !== 0) $medal = ' 🥇';
                        elseif ($absoluteRank === 2 && $net !== 0) $medal = ' 🥈';
                        elseif ($absoluteRank === 3 && $net !== 0) $medal = ' 🥉';
                    ?>
                    <tr>
                        <td><?php echo $displayRank !== null ? $displayRank : ''; ?>
                            <?php if ($absoluteRank === 1 && $net !== 0): ?>
                                <span class="rank-badge rank-mvp" style="margin-left: 4px;">MVP</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="student.php?id=<?php echo $row['id']; ?>" class="mdui-text-color-theme" style="text-decoration: none;">
                                <?php echo h($row['name']); ?><?php echo $medal; ?>
                            </a>
                            <?php if ($row['group_name']): ?>
                                <span style="color: #999; font-size: 12px; margin-left: 4px;">(<?php echo h($row['group_name']); ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="mdui-text-right <?php echo $netClass; ?>">
                            <strong><?php echo $net >= 0 ? '+' : ''; ?><?php echo number_format($net, 1); ?></strong>
                        </td>
                        <td class="mdui-text-right mdui-text-color-green">+<?php echo number_format(floatval($row['total_add']), 1); ?></td>
                        <td class="mdui-text-right mdui-text-color-blue">-<?php echo number_format(floatval($row['total_deduct']), 1); ?></td>
                        <td class="mdui-text-right <?php echo $netClass; ?>"><?php echo number_format($balance, 1); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $urlPattern = "personal.php?period_id={$periodId}" . ($search ? "&search=" . urlencode($search) : "") . "&page={page}";
        renderPagination($total, $page, $perPage, $urlPattern);
        ?>

        <?php if (!empty($period['remark'])): ?>
        <div class="mdui-card mdui-shadow-2" style="margin-top: 16px;">
            <div class="mdui-card-primary">
                <div class="mdui-card-primary-title">周期备注</div>
            </div>
            <div class="mdui-card-content mdui-typo">
                <?php echo nl2br(h($period['remark'])); ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

</div>

<?php pageFooter(); ?>