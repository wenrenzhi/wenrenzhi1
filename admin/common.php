<?php
require_once __DIR__ . '/../init.php';

requireLogin();

// 导航菜单配置
$navItems = [
    'dashboard' => ['icon' => 'home', 'text' => '首页'],
    'students'  => ['icon' => 'people', 'text' => '同学管理'],
    'groups'    => ['icon' => 'group_work', 'text' => '小组管理'],
    'scoring'   => ['icon' => 'edit_note', 'text' => '加分扣分'],
    'periods'   => ['icon' => 'date_range', 'text' => '统计周期'],
];
if ($_SESSION['is_super'] ?? false) {
    $navItems['users'] = ['icon' => 'manage_accounts', 'text' => '用户管理'];
}
if (checkPermission('logs')) {
    $navItems['logs'] = ['icon' => 'history', 'text' => '操作日志'];
}

function renderAdminHeader($page, $title) {
    global $navItems, $currentUser;
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo h($title); ?> - 后台管理</title>
    <link rel="stylesheet" href="https://cdn.hoha.top/mdui-v1.0.2/css/mdui.min.css">
    <script src="https://cdn.hoha.top/js/jquery-4.0.0.min.js"></script>
    <script src="https://cdn.hoha.top/mdui-v1.0.2/js/mdui.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { background: #f5f5f5; margin: 0; padding: 0; }
        .admin-wrapper { display: flex; min-height: 100vh; }
        .admin-sidebar { 
            width: 260px; 
            background: #fff; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            position: fixed; 
            top: 0; left: 0;
            height: 100vh; 
            overflow-y: auto; 
            z-index: 1001; 
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .admin-sidebar.open { transform: translateX(0); }
        .admin-content { flex: 1; margin-left: 0; padding: 16px; padding-top: 72px; }
        .admin-header { 
            background: #2196F3; 
            color: #fff; 
            padding: 12px 16px; 
            position: fixed; 
            top: 0; left: 0; right: 0;
            z-index: 1000; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .admin-sidebar .logo { 
            padding: 16px; 
            font-size: 18px; 
            font-weight: bold; 
            color: #2196F3; 
            border-bottom: 1px solid #eee; 
        }
        .admin-sidebar .nav-item { 
            display: flex; 
            align-items: center; 
            padding: 14px 20px; 
            color: #333; 
            text-decoration: none; 
            transition: all 0.2s; 
            font-size: 15px;
        }
        .admin-sidebar .nav-item:hover { background: #f5f5f5; }
        .admin-sidebar .nav-item.active { 
            background: rgba(33,150,243,0.12); 
            color: #2196F3; 
            font-weight: 500;
        }
        .admin-sidebar .nav-item i { margin-right: 14px; font-size: 22px; }
        .admin-sidebar .divider { height: 1px; background: #eee; margin: 8px 0; }
        .empty-state { text-align: center; padding: 60px 20px; color: #9e9e9e; }
        .empty-state i { font-size: 64px; display: block; margin-bottom: 16px; }
        .score-positive { color: #4CAF50; }
        .score-negative { color: #2196F3; }
        .score-zero { color: #212121; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 6px; padding: 20px 0; flex-wrap: wrap; }
        .pagination a, .pagination span { display: inline-block; min-width: 38px; height: 38px; line-height: 38px; text-align: center; border-radius: 6px; text-decoration: none; color: #333; cursor: pointer; font-weight: 500; }
        .pagination a { background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .pagination a:hover { background: #e3f2fd; color: #2196F3; }
        .pagination .active { background: #2196F3; color: #fff; box-shadow: 0 2px 4px rgba(33,150,243,0.3); }
        .pagination .disabled { color: #ccc; background: #fafafa; cursor: default; }
        .mdui-table-fluid { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .mdui-table { min-width: 600px; }
        .filter-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 16px; }
        .filter-bar > * { flex-shrink: 0; }
        .quick-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .quick-actions .mdui-card { flex: 1; min-width: 160px; }
        .stat-cards { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-cards .mdui-card { flex: 1; min-width: 140px; }
        .admin-header-section { margin-bottom: 16px; }
        .admin-header-section h3 { margin: 0 0 4px 0; font-size: 20px; font-weight: 600; }
        .admin-header-section p { margin: 0; color: #757575; font-size: 14px; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; margin-bottom: 12px; }
        .tab-bar { display: flex; gap: 0; margin-bottom: 16px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 10px 20px; border: none; background: transparent; cursor: pointer; font-size: 15px; color: #666; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: #2196F3; border-bottom-color: #2196F3; font-weight: 500; }
        .tab-btn:hover { color: #2196F3; }
        
        @media (min-width: 769px) {
            .admin-sidebar { transform: translateX(0); }
            .admin-content { margin-left: 260px; padding-top: 20px; }
            .admin-header { left: 260px; position: sticky; margin: -20px -20px 20px -20px; }
        }
    </style>
</head>
<body class="mdui-theme-primary-indigo">
    <div class="admin-wrapper">
        <div class="admin-sidebar" id="adminSidebar">
            <div class="logo">班级操行分系统</div>
            <?php foreach ($navItems as $key => $item): ?>
            <a href="?page=<?php echo $key; ?>" class="nav-item <?php echo $page === $key ? 'active' : ''; ?>">
                <i class="mdui-icon material-icons"><?php echo $item['icon']; ?></i>
                <span><?php echo $item['text']; ?></span>
            </a>
            <?php endforeach; ?>
            <div class="divider"></div>
            <a href="../index.php" class="nav-item">
                <i class="mdui-icon material-icons">arrow_back</i>
                <span>返回前台</span>
            </a>
            <a href="logout.php" class="nav-item">
                <i class="mdui-icon material-icons">exit_to_app</i>
                <span>退出登录</span>
            </a>
        </div>
        <div class="admin-content">
            <div class="admin-header">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center;">
                        <button class="mdui-btn mdui-btn-icon mdui-text-color-white" id="toggleSidebar">
                            <i class="mdui-icon material-icons">menu</i>
                        </button>
                        <span style="font-size: 17px; margin-left: 10px; font-weight: 500;"><?php echo h($title); ?></span>
                    </div>
                    <span style="font-size: 14px;"><?php echo h($_SESSION['username'] ?? ''); ?></span>
                </div>
            </div>
    <?php
}

function renderAdminFooter() {
    ?>
        </div>
    </div>
    <div id="sidebarOverlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.45); z-index:1000; display:none;"></div>
    <script>
        function showToast(msg, type='info') {
            var color = type === 'success' ? '#4CAF50' : (type === 'error' ? '#f44336' : '#2196F3');
            mdui.snackbar({
                message: msg,
                position: 'right-top',
                backgroundColor: color
            });
        }
        
        function $h(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        $(function() {
            mdui.mutation();
            
            function updateSidebarState() {
                if ($(window).width() > 768) {
                    $('#adminSidebar').removeClass('open');
                    $('#sidebarOverlay').hide();
                }
            }
            
            $('#toggleSidebar, #sidebarOverlay').on('click', function() {
                if ($(window).width() <= 768) {
                    $('#adminSidebar').toggleClass('open');
                    if ($('#adminSidebar').hasClass('open')) {
                        $('#sidebarOverlay').show();
                    } else {
                        $('#sidebarOverlay').hide();
                    }
                }
            });
            
            $(window).on('resize', updateSidebarState);
        });
    </script>
</body>
</html>
    <?php
}
?>
