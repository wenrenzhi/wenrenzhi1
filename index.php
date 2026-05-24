<?php
require_once __DIR__ . '/init.php';

if (!isInstalled()) {
    header('Location: install.php');
    exit;
}

$db = getDB();
$period = getCurrentPeriod();

$totalAdd = 0;
$totalDeduct = 0;
$netChange = 0;
$topStudent = null;
$topGroup = null;
$days = 0;
$baseScore = 0;

if ($period) {
    $periodId = $period['id'];
    $baseScore = floatval($period['base_score']);
    $days = getPeriodDays($period);

    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(CASE WHEN type = 'add' AND status = 1 THEN score ELSE 0 END), 0) AS total_add,
        COALESCE(SUM(CASE WHEN type = 'deduct' AND status = 1 THEN score ELSE 0 END), 0) AS total_deduct
        FROM records WHERE period_id = ? AND status = 1");
    $stmt->execute([$periodId]);
    $totals = $stmt->fetch();
    $totalAdd = floatval($totals['total_add']);
    $totalDeduct = floatval($totals['total_deduct']);
    $netChange = $totalAdd - $totalDeduct;

    $stmt = $db->prepare("SELECT s.id, s.name,
        COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_add,
        COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0) AS total_deduct
        FROM students s
        LEFT JOIN records r ON r.student_id = s.id AND r.period_id = ? AND r.status = 1
        WHERE s.status = 1
        GROUP BY s.id
        ORDER BY (COALESCE(SUM(CASE WHEN r.type = 'add' AND r.status = 1 THEN r.score ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN r.type = 'deduct' AND r.status = 1 THEN r.score ELSE 0 END), 0)) ASC
        LIMIT 1");
    $stmt->execute([$periodId]);
    $topStudent = $stmt->fetch();

    $stmt = $db->prepare("SELECT g.id, g.name,
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
    $stmt->execute([$periodId]);
    $topGroup = $stmt->fetch();
}

require_once __DIR__ . '/header.php';
?>

<?php if (!$period): ?>
    <div class="empty-state">
        <i class="mdui-icon material-icons" style="font-size: 64px; color: #bbb;">info</i>
        <p style="font-size: 18px; margin-top: 16px;">暂无进行中的统计周期</p>
        <p style="font-size: 14px; color: #999;">请等待管理员创建统计周期</p>
    </div>
<?php else: ?>

    <div class="period-info mdui-shadow-2">
        <div class="mdui-typo-subheading" style="margin-bottom: 4px;">
            <i class="mdui-icon material-icons" style="font-size: 16px; vertical-align: middle;">date_range</i>
            当前统计周期
        </div>
        <div class="mdui-typo-body-2">
            开始时间：<?php echo date('Y-m-d H:i', strtotime($period['start_time'])); ?>&nbsp;&nbsp;&nbsp;
            <?php if ($period['end_time']): ?>
                结束时间：<?php echo date('Y-m-d H:i', strtotime($period['end_time'])); ?>&nbsp;&nbsp;&nbsp;
            <?php else: ?>
                <span class="mdui-text-color-blue">进行中</span>&nbsp;&nbsp;&nbsp;
            <?php endif; ?>
            已持续 <strong><?php echo $days; ?></strong> 天
        </div>
    </div>

    <div class="mdui-row" style="margin-top: 16px;">
        <div class="mdui-col-xs-12 mdui-col-sm-4" style="margin-bottom: 16px;">
            <div class="mdui-card mdui-shadow-3 card-stat">
                <div class="stat-label">全班总加分</div>
                <div class="stat-value" style="color: #4CAF50;">+<?php echo number_format($totalAdd, 1); ?></div>
            </div>
        </div>
        <div class="mdui-col-xs-12 mdui-col-sm-4" style="margin-bottom: 16px;">
            <div class="mdui-card mdui-shadow-3 card-stat">
                <div class="stat-label">全班总扣分</div>
                <div class="stat-value" style="color: #2196F3;">-<?php echo number_format($totalDeduct, 1); ?></div>
            </div>
        </div>
        <div class="mdui-col-xs-12 mdui-col-sm-4" style="margin-bottom: 16px;">
            <div class="mdui-card mdui-shadow-3 card-stat">
                <div class="stat-label" style="margin-bottom: 4px;">
                    全班净变动
                    <span style="font-size: 11px; color: #999; display: block;">(加分 - 扣分)</span>
                </div>
                <div class="stat-value <?php
                    if ($netChange > 0) echo 'mdui-text-color-green';
                    elseif ($netChange < 0) echo 'mdui-text-color-blue';
                    else echo 'mdui-text-color-black';
                ?>">
                    <?php echo $netChange >= 0 ? '+' : ''; ?><?php echo number_format($netChange, 1); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="top3-preview" style="margin-top: 16px;">
        <div class="mdui-card mdui-shadow-3" style="min-width: 250px;">
            <div class="mdui-card-primary">
                <div class="mdui-card-primary-title">
                    <i class="mdui-icon material-icons" style="vertical-align: middle; font-size: 20px;">person</i>
                    个人榜 #1
                </div>
            </div>
            <div class="mdui-card-content">
                <?php if ($topStudent): ?>
                    <?php
                        $sNet = floatval($topStudent['total_add']) - floatval($topStudent['total_deduct']);
                        $sBalance = $baseScore + $sNet;
                    ?>
                    <div class="mdui-typo-body-2">
                        <strong><?php echo h($topStudent['name']); ?></strong>
                        <span class="rank-badge rank-mvp" style="margin-left: 8px;">MVP</span>
                    </div>
                    <table class="mdui-table" style="margin-top: 8px; border: none; box-shadow: none;">
                        <tr>
                            <td style="border: none; padding: 4px 8px;">净变动</td>
                            <td style="border: none; padding: 4px 8px;" class="<?php echo $sNet > 0 ? 'mdui-text-color-green' : ($sNet < 0 ? 'mdui-text-color-blue' : 'mdui-text-color-black'); ?>">
                                <strong><?php echo $sNet >= 0 ? '+' : ''; ?><?php echo number_format($sNet, 1); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 8px;">总加分</td>
                            <td style="border: none; padding: 4px 8px;" class="mdui-text-color-green">+<?php echo number_format(floatval($topStudent['total_add']), 1); ?></td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 8px;">总扣分</td>
                            <td style="border: none; padding: 4px 8px;" class="mdui-text-color-blue">-<?php echo number_format(floatval($topStudent['total_deduct']), 1); ?></td>
                        </tr>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #999;">暂无排名数据</div>
                <?php endif; ?>
            </div>
            <div class="mdui-card-actions">
                <a href="personal.php" class="mdui-btn mdui-btn-dense mdui-color-theme mdui-ripple">查看完整榜单</a>
            </div>
        </div>

        <div class="mdui-card mdui-shadow-3" style="min-width: 250px;">
            <div class="mdui-card-primary">
                <div class="mdui-card-primary-title">
                    <i class="mdui-icon material-icons" style="vertical-align: middle; font-size: 20px;">group</i>
                    小组榜 #1
                </div>
            </div>
            <div class="mdui-card-content">
                <?php if ($topGroup): ?>
                    <?php
                        $gNet = floatval($topGroup['total_add']) - floatval($topGroup['total_deduct']);
                    ?>
                    <div class="mdui-typo-body-2">
                        <strong><?php echo h($topGroup['name']); ?></strong>
                        <span class="rank-badge rank-mvp" style="margin-left: 8px;">MVP</span>
                    </div>
                    <table class="mdui-table" style="margin-top: 8px; border: none; box-shadow: none;">
                        <tr>
                            <td style="border: none; padding: 4px 8px;">净变动</td>
                            <td style="border: none; padding: 4px 8px;" class="<?php echo $gNet > 0 ? 'mdui-text-color-green' : ($gNet < 0 ? 'mdui-text-color-blue' : 'mdui-text-color-black'); ?>">
                                <strong><?php echo $gNet >= 0 ? '+' : ''; ?><?php echo number_format($gNet, 1); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 8px;">总加分</td>
                            <td style="border: none; padding: 4px 8px;" class="mdui-text-color-green">+<?php echo number_format(floatval($topGroup['total_add']), 1); ?></td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 4px 8px;">总扣分</td>
                            <td style="border: none; padding: 4px 8px;" class="mdui-text-color-blue">-<?php echo number_format(floatval($topGroup['total_deduct']), 1); ?></td>
                        </tr>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #999;">暂无排名数据</div>
                <?php endif; ?>
            </div>
            <div class="mdui-card-actions">
                <a href="group.php" class="mdui-btn mdui-btn-dense mdui-color-theme mdui-ripple">查看完整榜单</a>
            </div>
        </div>
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

<?php require_once __DIR__ . '/footer.php'; ?>
