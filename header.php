<?php
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-siteapp" />
    <title><?php echo defined('SITE_TITLE') ? h(SITE_TITLE) : '班级操行分'; ?></title>
    <link rel="stylesheet" href="https://cdn.hoha.top/mdui-v1.0.2/css/mdui.min.css">
    <script src="https://cdn.hoha.top/js/jquery-4.0.0.min.js"></script>
    <script src="https://cdn.hoha.top/mdui-v1.0.2/js/mdui.min.js"></script>
    <style>
        body { 
            background: #f5f5f5; 
            margin: 0;
            padding: 0;
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
        .main-content {
            padding-top: 72px;
        }
        .admin-container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 16px; 
        }
        .card-stat { 
            text-align: center; 
            padding: 16px; 
        }
        .card-stat .stat-value { 
            font-size: 28px; 
            font-weight: bold; 
            margin: 8px 0; 
        }
        .card-stat .stat-label { 
            font-size: 14px; 
            color: #757575; 
        }
        .rank-badge { 
            display: inline-block; 
            padding: 2px 8px; 
            border-radius: 4px; 
            font-size: 12px; 
            font-weight: bold; 
        }
        .rank-mvp { 
            background: #FFD700; 
            color: #333; 
        }
        .mdui-table td, 
        .mdui-table th { 
            white-space: nowrap; 
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
        .period-info { 
            padding: 16px; 
            background: #fff; 
            border-radius: 8px; 
            margin-bottom: 16px; 
            border-left: 4px solid #2196F3; 
        }
        .top3-preview { 
            display: flex; 
            gap: 16px; 
            flex-wrap: wrap; 
        }
        .top3-preview .mdui-card { 
            flex: 1; 
            min-width: 250px; 
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
        @media (max-width: 600px) {
            .top3-preview { 
                flex-direction: column; 
            }
            .mdui-table { 
                font-size: 13px; 
            }
        }
    </style>
</head>
<body class="mdui-theme-primary-indigo mdui-theme-accent-blue mdui-drawer-body-left mdui-appbar-with-toolbar">

<!-- 固定顶部工具栏 -->
<header class="mdui-appbar mdui-appbar-fixed">
    <div class="mdui-toolbar mdui-color-theme">
        <!-- 导航菜单按钮 -->
        <span class="mdui-btn mdui-btn-icon mdui-ripple" mdui-drawer="{target: '#main-drawer'}">
            <i class="mdui-icon material-icons">menu</i>
        </span>
        
        <!-- 网站标题 -->
        <span class="mdui-typo-title"><?php echo defined('SITE_TITLE') ? h(SITE_TITLE) : '班级操行分'; ?></span>
        
        <div class="mdui-toolbar-spacer"></div>
        
        <!-- 后台管理链接 -->
        <a href="admin/index.php" class="mdui-btn mdui-ripple mdui-text-color-white">
            <i class="mdui-icon material-icons" style="vertical-align: middle;">admin_panel_settings</i>
            后台管理
        </a>
    </div>
</header>

<!-- 侧边导航抽屉 -->
<div class="mdui-drawer" id="main-drawer">
    <div class="mdui-list" mdui-collapse="{accordion: true}" style="margin-bottom: 68px;">
        <!-- 顶部标题 -->
        <div class="mdui-list-item mdui-typo-title" style="font-weight: bold; font-size: 18px; color: #2196F3; padding: 16px;">
            <?php echo defined('SITE_TITLE') ? h(SITE_TITLE) : '班级操行分'; ?>
        </div>
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        
        <!-- 导航菜单项 -->
        <a href="index.php" class="mdui-list-item mdui-ripple<?php echo $currentScript === 'index.php' ? ' mdui-list-item-active' : ''; ?>">
            <i class="mdui-list-item-icon mdui-icon material-icons">home</i>
            &emsp;主页
        </a>
        <a href="personal.php" class="mdui-list-item mdui-ripple<?php echo $currentScript === 'personal.php' ? ' mdui-list-item-active' : ''; ?>">
            <i class="mdui-list-item-icon mdui-icon material-icons">person</i>
            &emsp;个人榜
        </a>
        <a href="group.php" class="mdui-list-item mdui-ripple<?php echo $currentScript === 'group.php' ? ' mdui-list-item-active' : ''; ?>">
            <i class="mdui-list-item-icon mdui-icon material-icons">group</i>
            &emsp;小组榜
        </a>
        <a href="group_personal.php" class="mdui-list-item mdui-ripple<?php echo $currentScript === 'group_personal.php' ? ' mdui-list-item-active' : ''; ?>">
            <i class="mdui-list-item-icon mdui-icon material-icons">group_work</i>
            &emsp;组内个人榜
        </a>
        
        <!-- 分隔线 -->
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        
        <!-- 周期信息 -->
        <?php
        $period = getCurrentPeriod();
        if ($period):
            $days = getPeriodDays($period);
        ?>
        <div class="mdui-list-item">
            <i class="mdui-list-item-icon mdui-icon material-icons">date_range</i>
            <div class="mdui-list-item-content">
                <div class="mdui-list-item-title">当前周期</div>
                <div class="mdui-list-item-text mdui-text-color-blue">
                    开始：<?php echo date('Y-m-d H:i', strtotime($period['start_time'])); ?>
                </div>
                <div class="mdui-list-item-text">已持续 <?php echo $days; ?> 天</div>
            </div>
        </div>
        <?php else: ?>
        <div class="mdui-list-item">
            <i class="mdui-list-item-icon mdui-icon material-icons">info</i>
            <div class="mdui-list-item-content">
                <div class="mdui-list-item-text">暂无进行中的统计周期</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 分隔线 -->
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        
        <!-- 底部链接 -->
        <a href="admin/index.php" class="mdui-list-item mdui-ripple">
            <i class="mdui-list-item-icon mdui-icon material-icons">settings</i>
            &emsp;后台管理
        </a>
    </div>
</div>

<!-- 主内容区域 -->
<div class="main-content">
    <div class="admin-container">
