<?php
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo defined('SITE_TITLE') ? h(SITE_TITLE) : '班级操行分'; ?> - 后台管理</title>
    <link rel="stylesheet" href="https://cdn.hoha.top/mdui-v1.0.2/css/mdui.min.css">
    <script src="https://cdn.hoha.top/js/jquery-4.0.0.min.js"></script>
    <script src="https://cdn.hoha.top/mdui-v1.0.2/js/mdui.min.js"></script>
    <style>
        body { 
            background: #f5f5f5; 
            margin: 0;
            padding: 0;
        }
        .toolbar-title { 
            font-size: 20px; 
            font-weight: 500; 
            color: #fff; 
        }
        .mdui-drawer { 
            width: 280px; 
        }
        .mdui-drawer .mdui-list {
            margin-bottom: 68px;
        }
        .mdui-drawer .mdui-list-item { 
            border-radius: 8px; 
            margin: 2px 8px; 
            padding-left: 16px;
        }
        .mdui-drawer .mdui-list-item.mdui-list-item-active { 
            background: rgba(33,150,243,0.12); 
            color: #2196F3; 
        }
        .mdui-collapse-item-header {
            padding-left: 16px;
        }
        .mdui-collapse-item-body .mdui-list-item {
            padding-left: 40px;
        }
        .admin-container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 16px; 
        }
        .admin-header { 
            margin-bottom: 24px; 
        }
        .admin-header h3 { 
            margin: 0 0 8px 0; 
            font-size: 24px; 
        }
        .admin-header p { 
            margin: 0; 
            color: #757575; 
        }
        .quick-actions { 
            display: flex; 
            gap: 16px; 
            flex-wrap: wrap; 
            margin-bottom: 24px; 
        }
        .quick-actions .mdui-card { 
            flex: 1; 
            min-width: 200px; 
            cursor: pointer; 
            transition: box-shadow .2s; 
        }
        .quick-actions .mdui-card:hover { 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
        }
        .quick-actions .mdui-card-content { 
            text-align: center; 
            padding: 24px 16px; 
        }
        .stat-cards { 
            display: flex; 
            gap: 16px; 
            flex-wrap: wrap; 
            margin-bottom: 24px; 
        }
        .stat-cards .mdui-card { 
            flex: 1; 
            min-width: 180px; 
        }
        .stat-cards .mdui-card-content { 
            text-align: center; 
            padding: 20px 16px; 
        }
        .stat-value { 
            font-size: 32px; 
            font-weight: bold; 
        }
        .stat-label { 
            font-size: 14px; 
            color: #757575; 
            margin-top: 4px; 
        }
        .score-positive { 
            color: #4CAF50; 
        }
        .score-negative { 
            color: #2196F3; 
        }
        .score-zero { 
            color: #212121; 
        }
        .empty-state { 
            text-align: center; 
            padding: 60px 20px; 
            color: #9e9e9e; 
        }
        .empty-state i { 
            font-size: 64px; 
            display: block; 
            margin-bottom: 16px; 
        }
        .mdui-table td, 
        .mdui-table th { 
            white-space: nowrap; 
        }
        .period-info { 
            padding: 16px; 
            background: #fff; 
            border-radius: 8px; 
            margin-bottom: 16px; 
            border-left: 4px solid #2196F3; 
        }
        .filter-bar { 
            display: flex; 
            gap: 12px; 
            flex-wrap: wrap; 
            align-items: center; 
            margin-bottom: 16px; 
        }
        .pagination { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            gap: 4px; 
            padding: 16px 0; 
            flex-wrap: wrap; 
        }
        .pagination a, 
        .pagination span { 
            display: inline-block; 
            min-width: 36px; 
            height: 36px; 
            line-height: 36px; 
            text-align: center; 
            border-radius: 4px; 
            text-decoration: none; 
            color: #333; 
            cursor: pointer; 
        }
        .pagination a { 
            background: #f5f5f5; 
        }
        .pagination a:hover { 
            background: #e0e0e0; 
        }
        .pagination .active { 
            background: #2196F3; 
            color: #fff; 
        }
        .pagination .disabled { 
            color: #ccc; 
            background: #fafafa; 
            cursor: default; 
        }
        .tab-bar { 
            display: flex; 
            gap: 0; 
            margin-bottom: 16px; 
            border-bottom: 2px solid #e0e0e0; 
        }
        .tab-bar .tab-btn { 
            padding: 10px 24px; 
            cursor: pointer; 
            border: none; 
            background: none; 
            font-size: 15px; 
            color: #757575; 
            border-bottom: 2px solid transparent; 
            margin-bottom: -2px; 
            transition: all .2s; 
        }
        .tab-bar .tab-btn.active { 
            color: #2196F3; 
            border-bottom-color: #2196F3; 
        }
        .tab-bar .tab-btn:hover { 
            color: #2196F3; 
        }
        .dialog-form { 
            padding: 16px 0; 
        }
        .dialog-form .mdui-textfield { 
            width: 100%; 
        }
        .checkbox-grid { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 8px; 
            max-height: 300px; 
            overflow-y: auto; 
        }
        .checkbox-grid label { 
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
            cursor: pointer; 
        }
        @media (max-width: 600px) {
            .quick-actions, 
            .stat-cards { 
                flex-direction: column; 
            }
            .filter-bar { 
                flex-direction: column; 
                align-items: stretch; 
            }
        }
        .main-content {
            padding-top: 72px;
        }
    </style>
</head>
<body class="mdui-theme-primary-indigo mdui-theme-accent-blue mdui-drawer-body-left mdui-appbar-with-toolbar">

<!-- 固定顶部工具栏 -->
<header class="mdui-appbar mdui-appbar-fixed">
    <div class="mdui-toolbar mdui-color-theme">
        <!-- 导航菜单按钮 -->
        <span class="mdui-btn mdui-btn-icon mdui-ripple" mdui-drawer="{target: '#admin-drawer'}">
            <i class="mdui-icon material-icons">menu</i>
        </span>
        
        <!-- 网站标题 -->
        <span class="mdui-typo-title toolbar-title">后台管理</span>
        
        <!-- 用户信息菜单 -->
        <a href="#" class="mdui-list-item mdui-float-right" mdui-menu="{target: '#user-menu'}">
            <i class="mdui-list-item-icon mdui-icon material-icons">account_box</i>
        </a>
        <ul class="mdui-menu" id="user-menu">
            <li class="mdui-menu-item">
                <a class="mdui-ripple">
                    <i class="mdui-menu-item-icon mdui-icon material-icons">account_circle</i>
                    <?php echo h($currentUser); ?>
                </a>
            </li>
            <li class="mdui-divider"></li>
            <li class="mdui-menu-item">
                <a href="logout.php" class="mdui-ripple">
                    <i class="mdui-menu-item-icon mdui-icon material-icons">exit_to_app</i>
                    退出登录
                </a>
            </li>
        </ul>
    </div>
</header>

<!-- 侧边导航 -->
<div class="mdui-drawer" id="admin-drawer">
    <div class="mdui-list" mdui-collapse="{accordion: true}" style="margin-bottom: 68px;">
        <!-- 顶部标题 -->
        <div class="mdui-list-item mdui-typo-title" style="font-weight: bold; font-size: 18px; color: #2196F3; padding: 16px;">
            后台管理
        </div>
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        
        <!-- 导航菜单项 -->
        <?php foreach ($navItems as $key => $item):
            $activeClass = ($page === $key) ? ' mdui-list-item-active' : '';
        ?>
        <a href="index.php?page=<?php echo $key; ?>" class="mdui-list-item mdui-ripple<?php echo $activeClass; ?>">
            <i class="mdui-list-item-icon mdui-icon material-icons"><?php echo $item['icon']; ?></i>
            &emsp;<?php echo $item['text']; ?>
        </a>
        <?php endforeach; ?>
        
        <!-- 分隔线 -->
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        
        <!-- 底部链接 -->
        <a href="../index.php" class="mdui-list-item mdui-ripple">
            <i class="mdui-list-item-icon mdui-icon material-icons">arrow_back</i>
            &emsp;返回前台
        </a>
        <a href="logout.php" class="mdui-list-item mdui-ripple">
            <i class="mdui-list-item-icon mdui-icon material-icons">exit_to_app</i>
            &emsp;退出登录
        </a>
    </div>
</div>

<!-- 主内容区域 -->
<div class="main-content">
    <div class="admin-container">
        <?php if ($mustChangePwd): ?>
        <!-- 首次登录修改密码对话框 -->
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
        <?php endif; ?>

