<?php
$currentPeriod = getCurrentPeriod();
?>
<div class="admin-header">
    <h3>后台首页</h3>
    <p>欢迎回来，<?php echo h($currentUser); ?></p>
</div>

<div class="quick-actions">
    <div class="mdui-card mdui-ripple" id="btn-new-period">
        <div class="mdui-card-content">
            <i class="mdui-icon material-icons">add_circle</i>
            <div>新建统计周期</div>
        </div>
    </div>
    <div class="mdui-card mdui-ripple" id="btn-end-period">
        <div class="mdui-card-content">
            <i class="mdui-icon material-icons">stop_circle</i>
            <div>结束当前周期</div>
        </div>
    </div>
    <div class="mdui-card mdui-ripple" onclick="location.href='index.php?page=scoring'">
        <div class="mdui-card-content">
            <i class="mdui-icon material-icons">edit_note</i>
            <div>快速加分扣分</div>
        </div>
    </div>
</div>

<?php if ($currentPeriod): $days = getPeriodDays($currentPeriod); ?>
<div class="period-info">
    <strong>当前统计周期</strong><br>
    开始时间：<?php echo date('Y-m-d H:i', strtotime($currentPeriod['start_time'])); ?><br>
    基础分：<?php echo number_format(floatval($currentPeriod['base_score']), 1); ?> 分 &nbsp;|&nbsp;
    已持续 <strong><?php echo $days; ?></strong> 天
    <?php if ($currentPeriod['remark']): ?>
    <br>备注：<?php echo h($currentPeriod['remark']); ?>
    <?php endif; ?>
</div>

<div class="stat-cards" id="dashboard-stats">
    <div class="mdui-card">
        <div class="mdui-card-content">
            <div class="stat-value" id="stat-student-count">-</div>
            <div class="stat-label">同学总数</div>
        </div>
    </div>
    <div class="mdui-card">
        <div class="mdui-card-content">
            <div class="stat-value" id="stat-group-count">-</div>
            <div class="stat-label">小组数量</div>
        </div>
    </div>
    <div class="mdui-card">
        <div class="mdui-card-content">
            <div class="stat-value score-positive" id="stat-total-add">-</div>
            <div class="stat-label">总加分</div>
        </div>
    </div>
    <div class="mdui-card">
        <div class="mdui-card-content">
            <div class="stat-value score-negative" id="stat-total-deduct">-</div>
            <div class="stat-label">总扣分</div>
        </div>
    </div>
</div>

<div class="mdui-card" style="padding: 16px; margin-bottom: 16px;">
    <div class="mdui-textfield" style="width: 100%; max-width: 400px;">
        <i class="mdui-icon material-icons">search</i>
        <input class="mdui-textfield-input" type="text" id="dashboard-search" placeholder="搜索同学姓名..."/>
    </div>
    <div id="dashboard-search-results" style="margin-top: 8px;"></div>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="mdui-icon material-icons">event_busy</i>
    <p>暂无进行中的统计周期</p>
    <p style="font-size: 14px;">点击"新建统计周期"开始使用</p>
</div>
<?php endif; ?>

<div id="new-period-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">新建统计周期</div>
    <div class="mdui-dialog-content dialog-form">
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">开始时间</label>
            <input class="mdui-textfield-input" type="datetime-local" id="np-start-time"/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">基础分</label>
            <input class="mdui-textfield-input" type="number" id="np-base-score" value="0" step="0.1"/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">备注（可选）</label>
            <textarea class="mdui-textfield-input" id="np-remark" rows="2"></textarea>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="np-submit-btn">创建</button>
    </div>
</div>

<div id="end-period-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">结束当前周期</div>
    <div class="mdui-dialog-content" id="end-period-content">
        <p>加载中...</p>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="ep-submit-btn">确认结束</button>
    </div>
</div>

<script>
$(function() {
    mdui.mutation();

    <?php if ($currentPeriod): ?>
    $.get('../api.php?action=public_overview', function(res) {
        if (res.has_period) {
            $('#stat-total-add').text('+' + parseFloat(res.total_add).toFixed(1));
            $('#stat-total-deduct').text('-' + parseFloat(res.total_deduct).toFixed(1));
        }
    }, 'json');

    $.get('../api.php?action=students_list', { status: '1' }, function(res) {
        $('#stat-student-count').text(res.length);
    }, 'json');

    $.get('../api.php?action=groups_list', function(res) {
        $('#stat-group-count').text(res.length);
    }, 'json');

    var searchTimer;
    $('#dashboard-search').on('input', function() {
        clearTimeout(searchTimer);
        var val = $(this).val().trim();
        if (!val) { $('#dashboard-search-results').html(''); return; }
        searchTimer = setTimeout(function() {
            $.get('../api.php?action=students_list', { search: val, status: 'all' }, function(res) {
                if (!res.length) { $('#dashboard-search-results').html('<p style="color:#757575;">未找到匹配的同学</p>'); return; }
                var html = '<ul class="mdui-list mdui-shadow-1" style="background:#fff;border-radius:4px;">';
                $.each(res, function(i, s) {
                    html += '<li class="mdui-list-item mdui-ripple" style="cursor:pointer;" onclick="location.href=\'index.php?page=scoring\'">';
                    html += '<div class="mdui-list-item-content">' + $h(s.name) + '</div>';
                    html += '<div class="mdui-list-item-text">' + (s.group_name ? $h(s.group_name) : '未分组') + (s.status == 0 ? ' (已禁用)' : '') + '</div>';
                    html += '</li>';
                });
                html += '</ul>';
                $('#dashboard-search-results').html(html);
            }, 'json');
        }, 300);
    });

    $('#btn-end-period').click(function() {
        $.get('../api.php?action=public_overview', function(res) {
            var html = '';
            if (res.has_period) {
                html += '<p><strong>周期开始：</strong>' + res.period.start_time + '</p>';
                html += '<p><strong>基础分：</strong>' + parseFloat(res.base_score).toFixed(1) + '</p>';
                html += '<p><strong>总加分：</strong><span style="color:#4CAF50;">+' + parseFloat(res.total_add).toFixed(1) + '</span></p>';
                html += '<p><strong>总扣分：</strong><span style="color:#2196F3;">-' + parseFloat(res.total_deduct).toFixed(1) + '</span></p>';
                html += '<p><strong>净变化：</strong>' + (res.net_change >= 0 ? '+' : '') + parseFloat(res.net_change).toFixed(1) + '</p>';
                if (res.top_student) {
                    html += '<p><strong>表现最优：</strong>' + $h(res.top_student.name) + ' (净' + (parseFloat(res.top_student.total_add) - parseFloat(res.top_student.total_deduct) >= 0 ? '+' : '') + (parseFloat(res.top_student.total_add) - parseFloat(res.top_student.total_deduct)).toFixed(1) + ')</p>';
                }
            } else {
                html = '<p>暂无进行中的周期</p>';
            }
            $('#end-period-content').html(html);
            var dialog = new mdui.Dialog('#end-period-dialog', { modal: true, history: false });
            dialog.open();
        }, 'json');
    });
    <?php endif; ?>

    $('#ep-submit-btn').click(function() {
        <?php if ($currentPeriod): ?>
        $.post('../api.php?action=period_end', { id: <?php echo $currentPeriod['id']; ?> }, function(res) {
            if (checkResponse(res)) return;
            showToast('周期已结束', 'success');
            location.reload();
        }, 'json').fail(handleAjaxFail);
        <?php endif; ?>
    });

    $('#btn-new-period').click(function() {
        var dialog = new mdui.Dialog('#new-period-dialog', { modal: true, history: false });
        $('#np-start-time').val('');
        $('#np-base-score').val('0');
        $('#np-remark').val('');
        dialog.open();
    });

    $('#np-submit-btn').click(function() {
        var startTime = $('#np-start-time').val();
        var baseScore = $('#np-base-score').val();
        var remark = $('#np-remark').val();
        if (!startTime) { showToast('请选择开始时间', 'error'); return; }
        $.post('../api.php?action=period_create', { start_time: startTime, base_score: baseScore, remark: remark }, function(res) {
            if (checkResponse(res)) return;
            showToast('周期创建成功', 'success');
            location.reload();
        }, 'json').fail(handleAjaxFail);
    });
});
</script>
