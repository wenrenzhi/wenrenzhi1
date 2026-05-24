<?php
require_once __DIR__ . '/init.php';
requireLogin();

$page = $_GET['page'] ?? 'dashboard';

if ($page === 'users') requireSuperAdmin();
if ($page === 'logs') requirePermission('logs');

$mustChangePwd = !empty($_SESSION['must_change_pwd']);
$isSuperAdmin = !empty($_SESSION['is_super']);
$currentUser = $_SESSION['username'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? 0;

$navItems = [
    'dashboard' => ['icon' => 'home', 'text' => '首页'],
    'students'  => ['icon' => 'people', 'text' => '同学管理'],
    'groups'    => ['icon' => 'group_work', 'text' => '小组管理'],
    'scoring'   => ['icon' => 'edit_note', 'text' => '加分扣分'],
    'periods'   => ['icon' => 'date_range', 'text' => '统计周期'],
];
if ($isSuperAdmin) {
    $navItems['users'] = ['icon' => 'manage_accounts', 'text' => '用户管理'];
}
if (checkPermission('logs')) {
    $navItems['logs'] = ['icon' => 'history', 'text' => '操作日志'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>后台管理 - <?php echo defined('SITE_TITLE') ? h(SITE_TITLE) : '班级操行分'; ?></title>
    <link rel="stylesheet" href="https://cdn.hoha.top/mdui-v1.0.2/css/mdui.min.css">
    <script src="https://cdn.hoha.top/js/jquery-4.0.0.min.js"></script>
    <script src="https://cdn.hoha.top/mdui-v1.0.2/js/mdui.min.js"></script>
    <style>
        body { background: #f5f5f5; }
        .mdui-drawer { max-width: 280px; }
        .mdui-drawer .mdui-list-item { border-radius: 8px; margin: 2px 8px; }
        .mdui-drawer .mdui-list-item.mdui-list-item-active { background: rgba(33,150,243,0.12); color: #2196F3; }
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 16px; }
        .admin-header { margin-bottom: 24px; }
        .admin-header h3 { margin: 0 0 8px 0; font-size: 24px; }
        .admin-header p { margin: 0; color: #757575; }
        .quick-actions { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .quick-actions .mdui-card { flex: 1; min-width: 200px; cursor: pointer; transition: box-shadow .2s; }
        .quick-actions .mdui-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .quick-actions .mdui-card-content { text-align: center; padding: 24px 16px; }
        .stat-cards { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .stat-cards .mdui-card { flex: 1; min-width: 180px; }
        .stat-cards .mdui-card-content { text-align: center; padding: 20px 16px; }
        .stat-value { font-size: 32px; font-weight: bold; }
        .stat-label { font-size: 14px; color: #757575; margin-top: 4px; }
        .score-positive { color: #4CAF50; }
        .score-negative { color: #2196F3; }
        .score-zero { color: #212121; }
        .empty-state { text-align: center; padding: 60px 20px; color: #9e9e9e; }
        .empty-state i { font-size: 64px; display: block; margin-bottom: 16px; }
        .mdui-table td, .mdui-table th { white-space: nowrap; }
        .period-info { padding: 16px; background: #fff; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #2196F3; }
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 4px; padding: 16px 0; flex-wrap: wrap; }
        .pagination a, .pagination span { display: inline-block; min-width: 36px; height: 36px; line-height: 36px; text-align: center; border-radius: 4px; text-decoration: none; color: #333; cursor: pointer; }
        .pagination a { background: #f5f5f5; }
        .pagination a:hover { background: #e0e0e0; }
        .pagination .active { background: #2196F3; color: #fff; }
        .pagination .disabled { color: #ccc; background: #fafafa; cursor: default; }
        .tab-bar { display: flex; gap: 0; margin-bottom: 16px; border-bottom: 2px solid #e0e0e0; }
        .tab-bar .tab-btn { padding: 10px 24px; cursor: pointer; border: none; background: none; font-size: 15px; color: #757575; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .2s; }
        .tab-bar .tab-btn.active { color: #2196F3; border-bottom-color: #2196F3; }
        .tab-bar .tab-btn:hover { color: #2196F3; }
        .dialog-form { padding: 16px 0; }
        .dialog-form .mdui-textfield { width: 100%; }
        .checkbox-grid { display: flex; flex-wrap: wrap; gap: 8px; max-height: 300px; overflow-y: auto; }
        .checkbox-grid label { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; }
        .header-appbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: #2196F3; height: 56px; display: flex; align-items: center; padding: 0 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
        .header-appbar .title { color: #fff; font-size: 18px; margin-left: 16px; font-weight: 500; }
        .header-appbar .spacer { flex: 1; }
        .header-appbar .user-info { color: rgba(255,255,255,0.85); font-size: 14px; }
        .content-wrapper { padding-top: 72px; }
        @media (max-width: 600px) {
            .quick-actions, .stat-cards { flex-direction: column; }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body class="mdui-theme-primary-indigo mdui-theme-accent-blue mdui-drawer-body-left">

<div class="mdui-drawer mdui-shadow-3" id="admin-drawer">
    <div class="mdui-list" style="padding-top: 16px;">
        <div class="mdui-list-item mdui-typo-title" style="font-weight: bold; font-size: 18px;">
            后台管理
        </div>
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        <?php foreach ($navItems as $key => $item):
            $activeClass = ($page === $key) ? ' mdui-list-item-active' : '';
        ?>
        <a href="admin.php?page=<?php echo $key; ?>" class="mdui-list-item mdui-ripple<?php echo $activeClass; ?>">
            <i class="mdui-list-item-icon mdui-icon material-icons"><?php echo $item['icon']; ?></i>
            <div class="mdui-list-item-content"><?php echo $item['text']; ?></div>
        </a>
        <?php endforeach; ?>
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        <a href="index.php" class="mdui-list-item mdui-ripple">
            <i class="mdui-list-item-icon mdui-icon material-icons">arrow_back</i>
            <div class="mdui-list-item-content">返回前台</div>
        </a>
        <a href="admin_logout.php" class="mdui-list-item mdui-ripple">
            <i class="mdui-list-item-icon mdui-icon material-icons">exit_to_app</i>
            <div class="mdui-list-item-content">退出登录</div>
        </a>
    </div>
</div>

<div class="header-appbar">
    <button class="mdui-btn mdui-btn-icon mdui-text-color-white" mdui-drawer="{target: '#admin-drawer', swipe: true}">
        <i class="mdui-icon material-icons">menu</i>
    </button>
    <span class="title">后台管理</span>
    <span class="spacer"></span>
    <span class="user-info"><?php echo h($currentUser); ?></span>
</div>

<div class="content-wrapper">
    <div class="admin-container">

<?php if ($mustChangePwd): ?>
<div id="must-change-pwd-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">首次登录，请修改密码</div>
    <div class="mdui-dialog-content">
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">新密码（至少6位）</label>
            <input class="mdui-textfield-input" type="password" id="new-pwd-input" minlength="6"/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">确认新密码</label>
            <input class="mdui-textfield-input" type="password" id="new-pwd-confirm"/>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" id="pwd-cancel-btn">取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="pwd-submit-btn">确认修改</button>
    </div>
</div>
<script>
$(function() {
    var dialog = new mdui.Dialog('#must-change-pwd-dialog', { modal: true, history: false });
    dialog.open();
    $('#pwd-submit-btn').click(function() {
        var pwd = $('#new-pwd-input').val();
        var confirm = $('#new-pwd-confirm').val();
        if (!pwd || pwd.length < 6) { mdui.snackbar({message: '密码至少6位', position: 'right-top'}); return; }
        if (pwd !== confirm) { mdui.snackbar({message: '两次密码不一致', position: 'right-top'}); return; }
        $.post('api.php?action=user_change_pwd_first', { new_password: pwd }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '密码修改成功', position: 'right-top'});
            dialog.close();
            location.reload();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });
    $('#pwd-cancel-btn').click(function() { location.href = 'admin_logout.php'; });
});
</script>
<?php endif; ?>

<?php
if ($page === 'dashboard'):
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
    <div class="mdui-card mdui-ripple" onclick="location.href='admin.php?page=scoring'">
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
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
$(function() {
    mdui.mutation();

    <?php if ($currentPeriod): ?>
    $.get('api.php?action=public_overview', function(res) {
        if (res.has_period) {
            $('#stat-total-add').text('+' + parseFloat(res.total_add).toFixed(1));
            $('#stat-total-deduct').text('-' + parseFloat(res.total_deduct).toFixed(1));
        }
    }, 'json');

    $.get('api.php?action=students_list', { status: '1' }, function(res) {
        $('#stat-student-count').text(res.length);
    }, 'json');

    $.get('api.php?action=groups_list', function(res) {
        $('#stat-group-count').text(res.length);
    }, 'json');

    var searchTimer;
    $('#dashboard-search').on('input', function() {
        clearTimeout(searchTimer);
        var val = $(this).val().trim();
        if (!val) { $('#dashboard-search-results').html(''); return; }
        searchTimer = setTimeout(function() {
            $.get('api.php?action=students_list', { search: val, status: 'all' }, function(res) {
                if (!res.length) { $('#dashboard-search-results').html('<p style="color:#757575;">未找到匹配的同学</p>'); return; }
                var html = '<ul class="mdui-list mdui-shadow-1" style="background:#fff;border-radius:4px;">';
                $.each(res, function(i, s) {
                    html += '<li class="mdui-list-item mdui-ripple" style="cursor:pointer;" onclick="location.href=\'admin.php?page=scoring\'">';
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
        $.get('api.php?action=public_overview', function(res) {
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
        $.post('api.php?action=period_end', { id: <?php echo $currentPeriod['id']; ?> }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '周期已结束', position: 'right-top'});
            location.reload();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
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
        if (!startTime) { mdui.snackbar({message: '请选择开始时间', position: 'right-top'}); return; }
        $.post('api.php?action=period_create', { start_time: startTime, base_score: baseScore, remark: remark }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '周期创建成功', position: 'right-top'});
            location.reload();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });
});
</script>

<?php
elseif ($page === 'students'):
?>
<div class="admin-header">
    <h3>同学管理</h3>
    <p>批量导入和管理同学信息</p>
</div>

<div class="mdui-card" style="padding: 16px; margin-bottom: 16px;">
    <div class="mdui-typo-subheading" style="margin-bottom: 8px;">批量导入同学</div>
    <div class="mdui-textfield">
        <textarea class="mdui-textfield-input" id="import-names" rows="4" placeholder="每行一个姓名，支持批量导入"></textarea>
    </div>
    <button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" id="btn-import">导入</button>
</div>

<div class="filter-bar">
    <div class="mdui-textfield mdui-textfield-has-bottom" style="flex:1; max-width: 350px;">
        <i class="mdui-icon material-icons">search</i>
        <input class="mdui-textfield-input" type="text" id="student-search" placeholder="搜索姓名..."/>
    </div>
    <button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" id="btn-add-student">添加同学</button>
</div>

<div class="mdui-table-fluid">
    <table class="mdui-table mdui-table-hoverable">
        <thead>
            <tr><th>姓名</th><th>小组</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody id="students-tbody">
            <tr><td colspan="4" style="text-align:center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<div id="student-edit-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">编辑同学</div>
    <div class="mdui-dialog-content dialog-form">
        <input type="hidden" id="se-id"/>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">姓名</label>
            <input class="mdui-textfield-input" type="text" id="se-name"/>
        </div>
        <div class="mdui-select-wrapper" style="margin-top: 16px;">
            <select class="mdui-select" id="se-group-id">
                <option value="">未分组</option>
            </select>
            <label>所属小组</label>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="se-submit-btn">保存</button>
    </div>
</div>

<script>
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
$(function() {
    mdui.mutation();

    function loadStudents(search) {
        var params = { status: 'all' };
        if (search) params.search = search;
        $.get('api.php?action=students_list', params, function(res) {
            var html = '';
            if (!res.length) {
                html = '<tr><td colspan="4"><div class="empty-state"><i class="mdui-icon material-icons">person_off</i><p>暂无数据</p></div></td></tr>';
            } else {
                $.each(res, function(i, s) {
                    html += '<tr>';
                    html += '<td>' + $h(s.name) + '</td>';
                    html += '<td>' + (s.group_name ? $h(s.group_name) : '未分组') + '</td>';
                    html += '<td>' + (s.status == 1 ? '<span style="color:#4CAF50;">正常</span>' : '<span style="color:#f44336;">已禁用</span>') + '</td>';
                    html += '<td>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-edit-student" data-id="' + s.id + '" data-name="' + $h(s.name) + '" data-group="' + (s.group_id || '') + '"><i class="mdui-icon material-icons">edit</i></button>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-del-student" data-id="' + s.id + '" data-name="' + $h(s.name) + '"><i class="mdui-icon material-icons" style="color:#f44336;">delete</i></button>';
                    html += '</td>';
                    html += '</tr>';
                });
            }
            $('#students-tbody').html(html);
        }, 'json');
    }

    loadStudents();

    $('#student-search').on('input', function() {
        loadStudents($(this).val().trim());
    });

    $('#btn-import').click(function() {
        var names = $('#import-names').val().trim();
        if (!names) { mdui.snackbar({message: '请输入姓名', position: 'right-top'}); return; }
        $.post('api.php?action=student_create', { names: names }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '成功导入 ' + res.count + ' 位同学', position: 'right-top'});
            $('#import-names').val('');
            loadStudents();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $('#btn-add-student').click(function() {
        mdui.prompt('请输入姓名', function(name) {
            if (!name || !name.trim()) return;
            $.post('api.php?action=student_create', { names: name.trim() }, function(res) {
                if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
                mdui.snackbar({message: '添加成功', position: 'right-top'});
                loadStudents();
            }, 'json');
        });
    });

    function loadGroupOptions(selectedId) {
        $.get('api.php?action=groups_list', function(res) {
            var html = '<option value="">未分组</option>';
            $.each(res, function(i, g) {
                html += '<option value="' + g.id + '"' + (g.id == selectedId ? ' selected' : '') + '>' + $h(g.name) + '</option>';
            });
            $('#se-group-id').html(html);
            mdui.mutation();
        }, 'json');
    }

    $(document).on('click', '.btn-edit-student', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var group = $(this).data('group');
        $('#se-id').val(id);
        $('#se-name').val(name);
        loadGroupOptions(group);
        var dialog = new mdui.Dialog('#student-edit-dialog', { modal: true, history: false });
        dialog.open();
    });

    $('#se-submit-btn').click(function() {
        var id = $('#se-id').val();
        var name = $('#se-name').val().trim();
        var groupId = $('#se-group-id').val();
        if (!name) { mdui.snackbar({message: '请输入姓名', position: 'right-top'}); return; }
        $.post('api.php?action=student_update', { id: id, name: name, group_id: groupId }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '保存成功', position: 'right-top'});
            loadStudents($('#student-search').val().trim());
            var dialog = mdui.Dialog.getInstance(document.getElementById('student-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-del-student', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        mdui.confirm('确定要删除同学「' + name + '」吗？', function() {
            $.post('api.php?action=student_delete', { id: id }, function(res) {
                if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
                mdui.snackbar({message: '已删除', position: 'right-top'});
                loadStudents($('#student-search').val().trim());
            }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
        });
    });
});
</script>

<?php
elseif ($page === 'groups'):
?>
<div class="admin-header">
    <h3>小组管理</h3>
    <p>管理小组信息与成员分配</p>
</div>

<div class="filter-bar">
    <div class="mdui-textfield mdui-textfield-has-bottom" style="flex:1; max-width: 350px;">
        <i class="mdui-icon material-icons">search</i>
        <input class="mdui-textfield-input" type="text" id="group-search" placeholder="搜索组名..."/>
    </div>
    <div class="mdui-textfield mdui-textfield-has-bottom" style="flex:1; max-width: 250px;">
        <input class="mdui-textfield-input" type="text" id="group-name-input" placeholder="新小组名称"/>
    </div>
    <button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" id="btn-create-group">创建</button>
</div>

<div class="mdui-table-fluid">
    <table class="mdui-table mdui-table-hoverable">
        <thead>
            <tr><th>组名</th><th>成员数</th><th>操作</th></tr>
        </thead>
        <tbody id="groups-tbody">
            <tr><td colspan="3" style="text-align:center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<div id="group-edit-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">编辑小组</div>
    <div class="mdui-dialog-content dialog-form">
        <input type="hidden" id="ge-id"/>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">组名</label>
            <input class="mdui-textfield-input" type="text" id="ge-name"/>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="ge-submit-btn">保存</button>
    </div>
</div>

<div id="group-members-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">添加成员</div>
    <div class="mdui-dialog-content dialog-form">
        <input type="hidden" id="gm-group-id"/>
        <div class="checkbox-grid" id="gm-checkboxes"></div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="gm-submit-btn">添加选中成员</button>
    </div>
</div>

<script>
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
$(function() {
    mdui.mutation();

    function loadGroups(search) {
        var params = {};
        if (search) params.search = search;
        $.get('api.php?action=groups_list', params, function(res) {
            var html = '';
            if (!res.length) {
                html = '<tr><td colspan="3"><div class="empty-state"><i class="mdui-icon material-icons">group_off</i><p>暂无数据</p></div></td></tr>';
            } else {
                $.each(res, function(i, g) {
                    html += '<tr>';
                    html += '<td>' + $h(g.name) + '</td>';
                    html += '<td>' + g.member_count + '</td>';
                    html += '<td>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-edit-group" data-id="' + g.id + '" data-name="' + $h(g.name) + '"><i class="mdui-icon material-icons">edit</i></button>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-add-members" data-id="' + g.id + '" data-name="' + $h(g.name) + '"><i class="mdui-icon material-icons">person_add</i></button>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-del-group" data-id="' + g.id + '" data-name="' + $h(g.name) + '"><i class="mdui-icon material-icons" style="color:#f44336;">delete</i></button>';
                    html += '</td>';
                    html += '</tr>';
                });
            }
            $('#groups-tbody').html(html);
        }, 'json');
    }

    loadGroups();

    $('#group-search').on('input', function() {
        loadGroups($(this).val().trim());
    });

    $('#btn-create-group').click(function() {
        var name = $('#group-name-input').val().trim();
        if (!name) { mdui.snackbar({message: '请输入组名', position: 'right-top'}); return; }
        $.post('api.php?action=group_create', { name: name }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '小组创建成功', position: 'right-top'});
            $('#group-name-input').val('');
            loadGroups($('#group-search').val().trim());
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-edit-group', function() {
        $('#ge-id').val($(this).data('id'));
        $('#ge-name').val($(this).data('name'));
        var dialog = new mdui.Dialog('#group-edit-dialog', { modal: true, history: false });
        dialog.open();
    });

    $('#ge-submit-btn').click(function() {
        var id = $('#ge-id').val();
        var name = $('#ge-name').val().trim();
        if (!name) { mdui.snackbar({message: '请输入组名', position: 'right-top'}); return; }
        $.post('api.php?action=group_update', { id: id, name: name }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '保存成功', position: 'right-top'});
            loadGroups($('#group-search').val().trim());
            var dialog = mdui.Dialog.getInstance(document.getElementById('group-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-del-group', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        mdui.confirm('确定要删除小组「' + name + '」吗？该小组成员将变为未分组状态。', function() {
            $.post('api.php?action=group_delete', { id: id }, function(res) {
                if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
                mdui.snackbar({message: '已删除', position: 'right-top'});
                loadGroups($('#group-search').val().trim());
            }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
        });
    });

    $(document).on('click', '.btn-add-members', function() {
        var groupId = $(this).data('id');
        $('#gm-group-id').val(groupId);
        $.get('api.php?action=ungrouped_students', function(res) {
            var html = '';
            if (!res.length) {
                html = '<p style="color:#757575;">没有未分组的同学</p>';
            } else {
                $.each(res, function(i, s) {
                    html += '<label class="mdui-checkbox">';
                    html += '<input type="checkbox" value="' + s.id + '"/>';
                    html += '<i class="mdui-checkbox-icon"></i>';
                    html += $h(s.name);
                    html += '</label>';
                });
            }
            $('#gm-checkboxes').html(html);
            mdui.mutation();
        }, 'json');
        var dialog = new mdui.Dialog('#group-members-dialog', { modal: true, history: false });
        dialog.open();
    });

    $('#gm-submit-btn').click(function() {
        var groupId = $('#gm-group-id').val();
        var ids = [];
        $('#gm-checkboxes input:checked').each(function() { ids.push($(this).val()); });
        if (!ids.length) { mdui.snackbar({message: '请选择要添加的成员', position: 'right-top'}); return; }
        $.post('api.php?action=group_add_members', { group_id: groupId, student_ids: ids.join(',') }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '成员添加成功', position: 'right-top'});
            loadGroups($('#group-search').val().trim());
            var dialog = mdui.Dialog.getInstance(document.getElementById('group-members-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });
});
</script>

<?php
elseif ($page === 'scoring'):
    $currentPeriod = getCurrentPeriod();
?>
<div class="admin-header">
    <h3>加分扣分操作</h3>
    <p>为同学进行加分或扣分操作</p>
</div>

<?php if ($currentPeriod): ?>
<div class="mdui-card" style="padding: 16px; margin-bottom: 16px;">
    <div class="mdui-typo-subheading" style="margin-bottom: 12px;">当前周期：<strong><?php echo date('Y-m-d H:i', strtotime($currentPeriod['start_time'])); ?> 开始</strong>（基础分：<?php echo number_format(floatval($currentPeriod['base_score']), 1); ?>）</div>

    <div class="tab-bar">
        <button class="tab-btn active" id="tab-single">单个选择</button>
        <button class="tab-btn" id="tab-group">按小组选择</button>
    </div>

    <div id="panel-single">
        <div class="checkbox-grid" id="scoring-student-checkboxes" style="margin-bottom: 12px;"></div>
    </div>
    <div id="panel-group" style="display:none;">
        <div class="mdui-select-wrapper" style="margin-bottom: 12px;">
            <select class="mdui-select" id="scoring-group-select">
                <option value="">请选择小组</option>
            </select>
            <label>选择小组（将选中该组所有成员）</label>
        </div>
    </div>

    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <div class="mdui-textfield mdui-textfield-floating-label" style="max-width: 160px;">
            <label class="mdui-textfield-label">分数</label>
            <input class="mdui-textfield-input" type="number" id="scoring-score" value="1" step="0.1" min="0.1"/>
        </div>
        <div class="mdui-select-wrapper" style="min-width: 100px;">
            <select class="mdui-select" id="scoring-type">
                <option value="add">加分</option>
                <option value="deduct">扣分</option>
            </select>
            <label>类型</label>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label" style="flex:1; min-width: 200px;">
            <label class="mdui-textfield-label">原因（可选）</label>
            <input class="mdui-textfield-input" type="text" id="scoring-reason"/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label" style="flex:1; min-width: 200px;">
            <label class="mdui-textfield-label">额外备注（可选）</label>
            <input class="mdui-textfield-input" type="text" id="scoring-extra-note"/>
        </div>
        <button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" id="btn-submit-scoring">提交</button>
    </div>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="mdui-icon material-icons">event_busy</i>
    <p>暂无进行中的统计周期</p>
    <p style="font-size: 14px;">请先新建统计周期</p>
</div>
<?php endif; ?>

<div class="admin-header" style="margin-top: 24px;">
    <h4>操作记录</h4>
</div>

<div class="filter-bar">
    <div class="mdui-textfield mdui-textfield-has-bottom" style="max-width: 200px;">
        <i class="mdui-icon material-icons">search</i>
        <input class="mdui-textfield-input" type="text" id="record-search" placeholder="搜索学生..."/>
    </div>
    <div class="mdui-select-wrapper" style="min-width: 120px;">
        <select class="mdui-select" id="record-type-filter">
            <option value="">全部类型</option>
            <option value="add">加分</option>
            <option value="deduct">扣分</option>
        </select>
        <label>类型</label>
    </div>
    <div class="mdui-select-wrapper" style="min-width: 100px;">
        <select class="mdui-select" id="record-order">
            <option value="DESC">最新在前</option>
            <option value="ASC">最早在前</option>
        </select>
        <label>排序</label>
    </div>
</div>

<div class="mdui-table-fluid">
    <table class="mdui-table mdui-table-hoverable">
        <thead>
            <tr><th>学生</th><th>类型</th><th>分数</th><th>原因</th><th>时间</th><th>操作人</th><th>操作</th></tr>
        </thead>
        <tbody id="records-tbody">
            <tr><td colspan="7" style="text-align:center;">加载中...</td></tr>
        </tbody>
    </table>
</div>
<div id="records-pagination" class="pagination"></div>

<div id="record-edit-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">编辑记录</div>
    <div class="mdui-dialog-content dialog-form">
        <input type="hidden" id="re-id"/>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">分数</label>
            <input class="mdui-textfield-input" type="number" id="re-score" step="0.1" min="0.1"/>
        </div>
        <div class="mdui-select-wrapper" style="margin-top: 16px;">
            <select class="mdui-select" id="re-type">
                <option value="add">加分</option>
                <option value="deduct">扣分</option>
            </select>
            <label>类型</label>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">原因</label>
            <textarea class="mdui-textfield-input" id="re-reason" rows="2"></textarea>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="re-submit-btn">保存</button>
    </div>
</div>

<script>
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
$(function() {
    mdui.mutation();

    <?php if ($currentPeriod): ?>
    var currentPeriodId = <?php echo $currentPeriod['id']; ?>;

    $.get('api.php?action=students_list', { status: '1' }, function(res) {
        var html = '';
        if (!res.length) {
            html = '<p style="color:#757575;">暂无活跃同学</p>';
        } else {
            $.each(res, function(i, s) {
                html += '<label class="mdui-checkbox">';
                html += '<input type="checkbox" value="' + s.id + '" class="scoring-student-cb"/>';
                html += '<i class="mdui-checkbox-icon"></i>';
                html += $h(s.name);
                html += '</label>';
            });
        }
        $('#scoring-student-checkboxes').html(html);
        mdui.mutation();
    }, 'json');

    $.get('api.php?action=groups_list', function(res) {
        var html = '<option value="">请选择小组</option>';
        $.each(res, function(i, g) {
            html += '<option value="' + g.id + '">' + $h(g.name) + '</option>';
        });
        $('#scoring-group-select').html(html);
        mdui.mutation();
    }, 'json');

    $('#tab-single').click(function() {
        $(this).addClass('active');
        $('#tab-group').removeClass('active');
        $('#panel-single').show();
        $('#panel-group').hide();
    });

    $('#tab-group').click(function() {
        $(this).addClass('active');
        $('#tab-single').removeClass('active');
        $('#panel-single').hide();
        $('#panel-group').show();
    });

    $('#btn-submit-scoring').click(function() {
        var isGroupTab = $('#tab-group').hasClass('active');
        var studentIds = [];
        if (isGroupTab) {
            var groupId = $('#scoring-group-select').val();
            if (!groupId) { mdui.snackbar({message: '请选择一个小组', position: 'right-top'}); return; }
            $.get('api.php?action=group_members', { group_id: groupId }, function(members) {
                if (!members.length) { mdui.snackbar({message: '该小组没有成员', position: 'right-top'}); return; }
                var ids = $.map(members, function(m) { return m.id; });
                submitScoring(ids.join(','));
            }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
        } else {
            $('#scoring-student-checkboxes input:checked').each(function() { studentIds.push($(this).val()); });
            if (!studentIds.length) { mdui.snackbar({message: '请选择至少一位同学', position: 'right-top'}); return; }
            submitScoring(studentIds.join(','));
        }
    });

    function submitScoring(studentIds) {
        var score = parseFloat($('#scoring-score').val());
        var type = $('#scoring-type').val();
        var reason = $('#scoring-reason').val().trim();
        var extraNote = $('#scoring-extra-note').val().trim();
        if (!score || score <= 0) { mdui.snackbar({message: '请输入有效的分数', position: 'right-top'}); return; }
        $.post('api.php?action=record_create', {
            student_ids: studentIds,
            period_id: currentPeriodId,
            type: type,
            score: score,
            reason: reason,
            extra_note: extraNote
        }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '操作成功，共 ' + res.count + ' 条记录', position: 'right-top'});
            $('#scoring-score').val('1');
            $('#scoring-reason').val('');
            $('#scoring-extra-note').val('');
            $('#scoring-student-checkboxes input').prop('checked', false);
            mdui.mutation();
            loadRecords(1);
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    }
    <?php endif; ?>

    var currentRecordsPage = 1;

    function loadRecords(page) {
        currentRecordsPage = page || 1;
        var params = {
            page: currentRecordsPage,
            order: $('#record-order').val(),
            type: $('#record-type-filter').val()
        };
        var search = $('#record-search').val().trim();
        <?php if ($currentPeriod): ?>
        params.period_id = currentPeriodId;
        <?php endif; ?>
        if (search) params.student_id = search;

        $.get('api.php?action=records_list', params, function(res) {
            var html = '';
            if (!res.data || !res.data.length) {
                html = '<tr><td colspan="7"><div class="empty-state"><i class="mdui-icon material-icons">receipt_long</i><p>暂无数据</p></div></td></tr>';
            } else {
                $.each(res.data, function(i, r) {
                    html += '<tr>';
                    html += '<td>' + $h(r.student_name || '未知') + '</td>';
                    html += '<td>' + (r.type === 'add' ? '<span style="color:#4CAF50;">加分</span>' : '<span style="color:#2196F3;">扣分</span>') + '</td>';
                    html += '<td>' + (r.type === 'add' ? '<span style="color:#4CAF50;">+' : '<span style="color:#2196F3;">-') + parseFloat(r.score).toFixed(1) + '</span></td>';
                    html += '<td>' + $h(r.reason || '-') + '</td>';
                    html += '<td>' + (r.created_at || '') + '</td>';
                    html += '<td>' + $h(r.operator_name || '') + '</td>';
                    html += '<td>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-edit-record" data-id="' + r.id + '" data-score="' + r.score + '" data-type="' + r.type + '" data-reason="' + $h(r.reason || '') + '"><i class="mdui-icon material-icons">edit</i></button>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-del-record" data-id="' + r.id + '"><i class="mdui-icon material-icons" style="color:#f44336;">delete</i></button>';
                    html += '</td>';
                    html += '</tr>';
                });
            }
            $('#records-tbody').html(html);

            var pagHtml = '';
            if (res.total > res.perPage) {
                var totalPages = Math.ceil(res.total / res.perPage);
                if (currentRecordsPage > 1) {
                    pagHtml += '<a onclick="window._loadRecords(' + (currentRecordsPage - 1) + ')">上一页</a>';
                } else {
                    pagHtml += '<span class="disabled">上一页</span>';
                }
                for (var i = 1; i <= totalPages; i++) {
                    if (i === currentRecordsPage) {
                        pagHtml += '<span class="active">' + i + '</span>';
                    } else {
                        pagHtml += '<a onclick="window._loadRecords(' + i + ')">' + i + '</a>';
                    }
                }
                if (currentRecordsPage < totalPages) {
                    pagHtml += '<a onclick="window._loadRecords(' + (currentRecordsPage + 1) + ')">下一页</a>';
                } else {
                    pagHtml += '<span class="disabled">下一页</span>';
                }
            }
            $('#records-pagination').html(pagHtml);
        }, 'json');
    }

    window._loadRecords = loadRecords;
    loadRecords(1);

    $('#record-search').on('input', function() { loadRecords(1); });
    $('#record-type-filter').change(function() { loadRecords(1); });
    $('#record-order').change(function() { loadRecords(1); });

    $(document).on('click', '.btn-edit-record', function() {
        $('#re-id').val($(this).data('id'));
        $('#re-score').val($(this).data('score'));
        $('#re-type').val($(this).data('type'));
        $('#re-reason').val($(this).data('reason'));
        mdui.mutation();
        var dialog = new mdui.Dialog('#record-edit-dialog', { modal: true, history: false });
        dialog.open();
    });

    $('#re-submit-btn').click(function() {
        var id = $('#re-id').val();
        var score = parseFloat($('#re-score').val());
        var type = $('#re-type').val();
        var reason = $('#re-reason').val().trim();
        if (!score || score <= 0) { mdui.snackbar({message: '请输入有效的分数', position: 'right-top'}); return; }
        $.post('api.php?action=record_update', { id: id, score: score, type: type, reason: reason }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '保存成功', position: 'right-top'});
            loadRecords(currentRecordsPage);
            var dialog = mdui.Dialog.getInstance(document.getElementById('record-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-del-record', function() {
        var id = $(this).data('id');
        mdui.confirm('确定要删除这条记录吗？', function() {
            $.post('api.php?action=record_delete', { id: id }, function(res) {
                if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
                mdui.snackbar({message: '已删除', position: 'right-top'});
                loadRecords(currentRecordsPage);
            }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
        });
    });
});
</script>

<?php
elseif ($page === 'periods'):
?>
<div class="admin-header">
    <h3>统计周期管理</h3>
    <p>管理操行分统计周期</p>
</div>

<button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" id="btn-new-period">新建统计周期</button>

<div class="mdui-table-fluid" style="margin-top: 16px;">
    <table class="mdui-table mdui-table-hoverable">
        <thead>
            <tr><th>开始时间</th><th>结束时间</th><th>基础分</th><th>状态</th><th>备注</th><th>操作</th></tr>
        </thead>
        <tbody id="periods-tbody">
            <tr><td colspan="6" style="text-align:center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<div id="period-create-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">新建统计周期</div>
    <div class="mdui-dialog-content dialog-form">
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">开始时间</label>
            <input class="mdui-textfield-input" type="datetime-local" id="pc-start-time"/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">基础分</label>
            <input class="mdui-textfield-input" type="number" id="pc-base-score" value="0" step="0.1"/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">备注（可选）</label>
            <textarea class="mdui-textfield-input" id="pc-remark" rows="2"></textarea>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="pc-submit-btn">创建</button>
    </div>
</div>

<div id="period-end-confirm-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">结束统计周期</div>
    <div class="mdui-dialog-content" id="pec-content"></div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="pec-submit-btn">确认结束</button>
    </div>
</div>

<div id="period-remark-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">编辑备注</div>
    <div class="mdui-dialog-content dialog-form">
        <input type="hidden" id="pr-id"/>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">备注</label>
            <textarea class="mdui-textfield-input" id="pr-remark" rows="3"></textarea>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="pr-submit-btn">保存</button>
    </div>
</div>

<script>
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
$(function() {
    mdui.mutation();

    function loadPeriods() {
        $.get('api.php?action=periods_list', function(res) {
            var html = '';
            if (!res.length) {
                html = '<tr><td colspan="6"><div class="empty-state"><i class="mdui-icon material-icons">event_busy</i><p>暂无数据</p></div></td></tr>';
            } else {
                $.each(res, function(i, p) {
                    html += '<tr>';
                    html += '<td>' + (p.start_time || '') + '</td>';
                    html += '<td>' + (p.end_time || '-') + '</td>';
                    html += '<td>' + parseFloat(p.base_score).toFixed(1) + '</td>';
                    html += '<td>' + (p.status == 1 ? '<span style="color:#4CAF50;">进行中</span>' : '<span style="color:#757575;">已结束</span>') + '</td>';
                    html += '<td>' + $h(p.remark || '-') + '</td>';
                    html += '<td>';
                    html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-edit-remark" data-id="' + p.id + '" data-remark="' + $h(p.remark || '') + '"><i class="mdui-icon material-icons">edit</i></button>';
                    if (p.status == 1) {
                        html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-end-period" data-id="' + p.id + '"><i class="mdui-icon material-icons">stop</i></button>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
            }
            $('#periods-tbody').html(html);
        }, 'json');
    }

    loadPeriods();

    $('#btn-new-period').click(function() {
        var dialog = new mdui.Dialog('#period-create-dialog', { modal: true, history: false });
        $('#pc-start-time').val('');
        $('#pc-base-score').val('0');
        $('#pc-remark').val('');
        dialog.open();
    });

    $('#pc-submit-btn').click(function() {
        var startTime = $('#pc-start-time').val();
        var baseScore = $('#pc-base-score').val();
        var remark = $('#pc-remark').val();
        if (!startTime) { mdui.snackbar({message: '请选择开始时间', position: 'right-top'}); return; }
        $.post('api.php?action=period_create', { start_time: startTime, base_score: baseScore, remark: remark }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '周期创建成功', position: 'right-top'});
            loadPeriods();
            var dialog = mdui.Dialog.getInstance(document.getElementById('period-create-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-edit-remark', function() {
        $('#pr-id').val($(this).data('id'));
        $('#pr-remark').val($(this).data('remark'));
        var dialog = new mdui.Dialog('#period-remark-dialog', { modal: true, history: false });
        dialog.open();
    });

    $('#pr-submit-btn').click(function() {
        var id = $('#pr-id').val();
        var remark = $('#pr-remark').val();
        $.post('api.php?action=period_update', { id: id, remark: remark }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '备注保存成功', position: 'right-top'});
            loadPeriods();
            var dialog = mdui.Dialog.getInstance(document.getElementById('period-remark-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-end-period', function() {
        var id = $(this).data('id');
        mdui.confirm('确定要结束这个统计周期吗？', function() {
            $.post('api.php?action=period_end', { id: id }, function(res) {
                if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
                mdui.snackbar({message: '周期已结束', position: 'right-top'});
                loadPeriods();
            }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
        });
    });
});
</script>

<?php
elseif ($page === 'users' && $isSuperAdmin):
?>
<div class="admin-header">
    <h3>用户管理</h3>
    <p>管理后台管理员账号</p>
</div>

<button class="mdui-btn mdui-btn-raised mdui-color-theme mdui-ripple" id="btn-new-user">创建用户</button>

<div class="mdui-table-fluid" style="margin-top: 16px;">
    <table class="mdui-table mdui-table-hoverable">
        <thead>
            <tr><th>用户名</th><th>是否超管</th><th>强制改密</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody id="users-tbody">
            <tr><td colspan="5" style="text-align:center;">加载中...</td></tr>
        </tbody>
    </table>
</div>

<div id="user-create-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">创建用户</div>
    <div class="mdui-dialog-content dialog-form">
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">用户名</label>
            <input class="mdui-textfield-input" type="text" id="uc-username"/>
        </div>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">密码</label>
            <input class="mdui-textfield-input" type="password" id="uc-password"/>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="uc-submit-btn">创建</button>
    </div>
</div>

<div id="user-edit-dialog" class="mdui-dialog">
    <div class="mdui-dialog-title">编辑用户</div>
    <div class="mdui-dialog-content dialog-form">
        <input type="hidden" id="ue-id"/>
        <div class="mdui-textfield mdui-textfield-floating-label">
            <label class="mdui-textfield-label">用户名</label>
            <input class="mdui-textfield-input" type="text" id="ue-username" disabled/>
        </div>
        <div class="mdui-select-wrapper" style="margin-top: 16px;">
            <select class="mdui-select" id="ue-status">
                <option value="1">启用</option>
                <option value="0">禁用</option>
            </select>
            <label>状态</label>
        </div>
    </div>
    <div class="mdui-dialog-actions">
        <button class="mdui-btn mdui-ripple" mdui-dialog-cancel>取消</button>
        <button class="mdui-btn mdui-ripple mdui-color-theme" id="ue-submit-btn">保存</button>
    </div>
</div>

<script>
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
$(function() {
    mdui.mutation();

    function loadUsers() {
        $.get('api.php?action=users_list', function(res) {
            var html = '';
            if (!res.length) {
                html = '<tr><td colspan="5"><div class="empty-state"><i class="mdui-icon material-icons">people_outline</i><p>暂无数据</p></div></td></tr>';
            } else {
                $.each(res, function(i, u) {
                    html += '<tr>';
                    html += '<td>' + $h(u.username) + '</td>';
                    html += '<td>' + (u.is_super ? '是' : '否') + '</td>';
                    html += '<td>' + (u.must_change_pwd ? '是' : '否') + '</td>';
                    html += '<td>' + (u.status == 1 ? '<span style="color:#4CAF50;">启用</span>' : '<span style="color:#f44336;">禁用</span>') + '</td>';
                    html += '<td>';
                    if (!u.is_super) {
                        html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-edit-user" data-id="' + u.id + '" data-username="' + $h(u.username) + '" data-status="' + u.status + '"><i class="mdui-icon material-icons">edit</i></button>';
                        html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-reset-pwd" data-id="' + u.id + '" data-username="' + $h(u.username) + '"><i class="mdui-icon material-icons">vpn_key</i></button>';
                        html += '<button class="mdui-btn mdui-btn-icon mdui-ripple btn-del-user" data-id="' + u.id + '" data-username="' + $h(u.username) + '"><i class="mdui-icon material-icons" style="color:#f44336;">delete</i></button>';
                    } else {
                        html += '<span style="color:#757575;">-</span>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
            }
            $('#users-tbody').html(html);
        }, 'json');
    }

    loadUsers();

    $('#btn-new-user').click(function() {
        var dialog = new mdui.Dialog('#user-create-dialog', { modal: true, history: false });
        $('#uc-username').val('');
        $('#uc-password').val('');
        dialog.open();
    });

    $('#uc-submit-btn').click(function() {
        var username = $('#uc-username').val().trim();
        var password = $('#uc-password').val();
        if (!username || !password) { mdui.snackbar({message: '请填写完整', position: 'right-top'}); return; }
        $.post('api.php?action=user_create', { username: username, password: password }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '用户创建成功', position: 'right-top'});
            loadUsers();
            var dialog = mdui.Dialog.getInstance(document.getElementById('user-create-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-edit-user', function() {
        $('#ue-id').val($(this).data('id'));
        $('#ue-username').val($(this).data('username'));
        $('#ue-status').val($(this).data('status'));
        mdui.mutation();
        var dialog = new mdui.Dialog('#user-edit-dialog', { modal: true, history: false });
        dialog.open();
    });

    $('#ue-submit-btn').click(function() {
        var id = $('#ue-id').val();
        var status = $('#ue-status').val();
        $.post('api.php?action=user_update', { id: id, status: status }, function(res) {
            if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
            mdui.snackbar({message: '用户更新成功', position: 'right-top'});
            loadUsers();
            var dialog = mdui.Dialog.getInstance(document.getElementById('user-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
    });

    $(document).on('click', '.btn-reset-pwd', function() {
        var id = $(this).data('id');
        var username = $(this).data('username');
        mdui.confirm('确定要重置用户「' + username + '」的密码吗？', function() {
            $.post('api.php?action=user_reset_password', { id: id }, function(res) {
                if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
                mdui.alert('新密码：' + res.new_password, function() {
                    mdui.snackbar({message: '密码重置成功', position: 'right-top'});
                    loadUsers();
                });
            }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
        });
    });

    $(document).on('click', '.btn-del-user', function() {
        var id = $(this).data('id');
        var username = $(this).data('username');
        mdui.confirm('确定要删除用户「' + username + '」吗？', function() {
            $.post('api.php?action=user_delete', { id: id }, function(res) {
                if (res.error) { mdui.snackbar({message: res.error, position: 'right-top'}); return; }
                mdui.snackbar({message: '用户已删除', position: 'right-top'});
                loadUsers();
            }, 'json').fail(function(jqXHR) {
    var msg = '网络错误';
    try {
        var res = JSON.parse(jqXHR.responseText);
        if (res.error) msg = res.error;
    } catch(e) {}
    mdui.snackbar({message: msg, position: 'right-top'});
});
        });
    });
});
</script>

<?php
elseif ($page === 'logs' && checkPermission('logs')):
?>
<div class="admin-header">
    <h3>操作日志</h3>
    <p>查看系统操作记录</p>
</div>

<div class="mdui-table-fluid">
    <table class="mdui-table mdui-table-hoverable">
        <thead>
            <tr><th>操作人</th><th>操作</th><th>IP</th><th>时间</th></tr>
        </thead>
        <tbody id="logs-tbody">
            <tr><td colspan="4" style="text-align:center;">加载中...</td></tr>
        </tbody>
    </table>
</div>
<div id="logs-pagination" class="pagination"></div>

<script>
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
$(function() {
    mdui.mutation();

    var currentPage = 1;

    function loadLogs(page) {
        currentPage = page || 1;
        $.get('api.php?action=operations_list', { page: currentPage }, function(res) {
            var html = '';
            if (!res.data || !res.data.length) {
                html = '<tr><td colspan="4"><div class="empty-state"><i class="mdui-icon material-icons">history</i><p>暂无数据</p></div></td></tr>';
            } else {
                $.each(res.data, function(i, l) {
                    html += '<tr>';
                    html += '<td>' + $h(l.username || '未知') + '</td>';
                    html += '<td>' + $h(l.action || '') + '</td>';
                    html += '<td>' + $h(l.ip || '') + '</td>';
                    html += '<td>' + (l.created_at || '') + '</td>';
                    html += '</tr>';
                });
            }
            $('#logs-tbody').html(html);

            var pagHtml = '';
            if (res.total > res.perPage) {
                var totalPages = Math.ceil(res.total / res.perPage);
                if (currentPage > 1) {
                    pagHtml += '<a onclick="window._loadLogs(' + (currentPage - 1) + ')">上一页</a>';
                } else {
                    pagHtml += '<span class="disabled">上一页</span>';
                }
                for (var i = 1; i <= totalPages; i++) {
                    if (i === currentPage) {
                        pagHtml += '<span class="active">' + i + '</span>';
                    } else {
                        pagHtml += '<a onclick="window._loadLogs(' + i + ')">' + i + '</a>';
                    }
                }
                if (currentPage < totalPages) {
                    pagHtml += '<a onclick="window._loadLogs(' + (currentPage + 1) + ')">下一页</a>';
                } else {
                    pagHtml += '<span class="disabled">下一页</span>';
                }
            }
            $('#logs-pagination').html(pagHtml);
        }, 'json');
    }

    window._loadLogs = loadLogs;
    loadLogs(1);
});
</script>

<?php endif; ?>
</div>
</div>
</body>
</html>
