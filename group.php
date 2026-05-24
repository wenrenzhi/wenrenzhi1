<?php
require_once __DIR__ . '/init.php';

if (!isInstalled()) {
    header('Location: install.php');
    exit;
}

$db = getDB();
$periodId = (int)($_GET['period_id'] ?? 0);

$allPeriods = $db->query("SELECT * FROM periods ORDER BY start_time DESC")->fetchAll();

if (empty($periodId)) {
    $current = getCurrentPeriod();
    if ($current) {
        $periodId = $current['id'];
    } elseif (!empty($allPeriods)) {
        $periodId = $allPeriods[0]['id'];
    }
}

$period = null;
$data = [];

if ($periodId) {
    $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $period = $stmt->fetch();

    if ($period) {
        $stmt = $db->prepare("SELECT g.id, g.name,
            COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
            COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct,
            COUNT(DISTINCT s.id) AS member_count
            FROM `groups` g
            LEFT JOIN students s ON s.group_id = g.id AND s.status = 1
            LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
            GROUP BY g.id
            HAVING member_count > 0
            ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC, g.name ASC");
        $stmt->execute([$periodId]);
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
        $data = $rankedData;
    }
}

require_once __DIR__ . '/header.php';
?>

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

    <form method="get" action="group.php" style="margin-bottom: 16px;">
        <?php renderPeriodSelector('period_id'); ?>
    </form>

    <?php if (!$period): ?>
        <div class="empty-state">
            <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">error_outline</i>
            <p style="font-size: 18px; margin-top: 16px;">未找到该周期</p>
            <p style="font-size: 14px; color: #999;">请从上方下拉菜单选择有效的统计周期</p>
        </div>
    <?php elseif (empty($data)): ?>
        <div class="empty-state">
            <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">group</i>
            <p style="font-size: 18px; margin-top: 16px;">暂无数据</p>
            <p style="font-size: 14px; color: #999;">该周期暂无小组排名数据</p>
        </div>
    <?php else: ?>

        <div class="mdui-typo-caption" style="margin-bottom: 8px; color: #666;">
            <i class="mdui-icon material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
            排名规则：按小组净变动总和升序排列（净变动 = 加分 - 扣分；净变动越小排名越靠前）
        </div>

        <div class="mdui-table-fluid">
            <table class="mdui-table mdui-table-hoverable">
                <thead>
                    <tr>
                        <th style="width: 60px;">排名</th>
                        <th>组名</th>
                        <th class="mdui-text-right">净变动总和</th>
                        <th class="mdui-text-right">成员数</th>
                        <th class="mdui-text-right">总加分</th>
                        <th class="mdui-text-right">总扣分</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $item):
                        $row = $item['data'];
                        $net = $item['net'];
                        $displayRank = $item['displayRank'];
                        $absoluteRank = $item['absoluteRank'];
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
                            <strong><?php echo h($row['name']); ?><?php echo $medal; ?></strong>
                        </td>
                        <td class="mdui-text-right <?php echo $netClass; ?>">
                            <strong><?php echo $net >= 0 ? '+' : ''; ?><?php echo number_format($net, 1); ?></strong>
                        </td>
                        <td class="mdui-text-right"><?php echo (int)$row['member_count']; ?></td>
                        <td class="mdui-text-right mdui-text-color-green">+<?php echo number_format(floatval($row['total_add']), 1); ?></td>
                        <td class="mdui-text-right mdui-text-color-blue">-<?php echo number_format(floatval($row['total_deduct']), 1); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

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

<?php require_once __DIR__ . '/footer.php'; ?>
