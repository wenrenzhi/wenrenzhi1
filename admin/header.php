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
        /* 基础样式保留 */
        body { 
            background: #f5f5f5; 
            margin: 0;
            padding: 0;
        }
        
        /* 工具栏样式 */
        .toolbar-title { 
            font-size: 20px; 
            font-weight: 500; 
            color: #fff; 
        }
        
        /* 抽屉样式 - 参考示例调整 */
        .mdui-drawer { 
            width: 280px; 
        }
        
        .mdui-drawer .mdui-list {
            margin-bottom: 68px; /* 底部留出空间 */
        }
        
        .mdui-drawer .mdui-list-item { 
            border-radius: 8px; 
            margin: 2px 8px; 
            padding-left: 16px; /* 统一内边距 */
        }
        
        .mdui-drawer .mdui-list-item mdui-list-item-active { 
            background: rgba(33,150,243,0.12); 
            color: #2196F3; 
        }
        
        /* 折叠面板样式（参考示例的折叠菜单） */
        .mdui-collapse-item-header {
            padding-left: 16px;
        }
        
        .mdui-collapse-item-body .mdui-list-item {
            padding-left: 40px; /* 子菜单缩进 */
        }
        
        /* 原有其他样式保留 */
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
        
        <!-- 用户信息菜单（参考示例的下拉菜单） -->
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

<!-- 侧边导航 - 参考示例的抽屉栏结构修改 -->
<div class="mdui-drawer" id="admin-drawer">
    <div class="mdui-list" mdui-collapse="{accordion: true}" style="margin-bottom: 68px;">
        <!-- 顶部标题 -->
        <div class="mdui-list-item mdui-typo-title" style="font-weight: bold; font-size: 18px; color: #2196F3; padding: 16px;">
            后台管理
        </div>
        <div class="mdui-divider" style="margin: 8px 0;"></div>
        
        <!-- 导航菜单项（一级菜单） -->
        <?php foreach ($navItems as $key => $item):
            $activeClass = ($page === $key) ? ' mdui-list-item-active' : '';
            // 假设$item中包含是否为折叠菜单的标识，如has_children
            if (empty($item['children'])):
        ?>
        <a href="index.php?page=<?php echo $key; ?>" class="mdui-list-item mdui-ripple<?php echo $activeClass; ?>">
            <i class="mdui-list-item-icon mdui-icon material-icons"><?php echo $item['icon']; ?></i>
            &emsp;<?php echo $item['text']; ?>
        </a>
        <?php else: ?>
        <!-- 折叠菜单（参考示例的mdui-collapse） -->
        <div class="mdui-collapse-item">
            <div class="mdui-collapse-item-header mdui-list-item mdui-ripple<?php echo $activeClass; ?>">
                <i class="mdui-list-item-icon mdui-icon material-icons"><?php echo $item['icon']; ?></i>
                &emsp;<?php echo $item['text']; ?>
                <i class="mdui-collapse-item-arrow mdui-icon material-icons">keyboard_arrow_down</i>
            </div>
            <div class="mdui-collapse-item-body mdui-list">
                <?php foreach ($item['children'] as $childKey => $childItem):
                    $childActive = ($page === $childKey) ? ' mdui-list-item-active' : '';
                ?>
                <a href="index.php?page=<?php echo $childKey; ?>" class="mdui-list-item mdui-ripple<?php echo $childActive; ?>">
                    <?php echo $childItem['text']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; endforeach; ?>
        
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
        
        <!-- 页面主要内容 -->
        <div id="main-content">
            <?php 
            // 这里放置您的主要页面内容
            ?>
        </div>
    </div>
</div>

<script>
// 全局函数定义
function $h(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function checkResponse(res) {
    if (res && res.error) {
        if (res.error.indexOf('权限') !== -1 || res.error.indexOf('无权') !== -1) {
            showError(res.error);
        } else {
            showToast(res.error, 'error');
        }
        return true;
    }
    return false;
}

function handleAjaxFail(jqXHR) {
    var msg = '网络错误';
    try {
        var r = JSON.parse(jqXHR.responseText);
        if (r.error) msg = r.error;
    } catch(e) {}
    if (msg.indexOf('权限') !== -1 || msg.indexOf('无权') !== -1) {
        showError(msg);
    } else {
        showToast(msg, 'error');
    }
}

function showError(msg) {
    mdui.dialog({
        title: '提示',
        content: msg,
        buttons: [{ text: '确定' }]
    });
}

function showToast(msg, type) {
    var color = type === 'success' ? '#4CAF50' : (type === 'error' ? '#f44336' : '#2196F3');
    mdui.snackbar({
        message: msg,
        position: 'right-top',
        backgroundColor: color
    });
}

// 首次登录修改密码对话框
$(function() {
    <?php if ($mustChangePwd): ?>
    var dialog = new mdui.Dialog('#must-change-pwd-dialog', { 
        modal: true, 
        history: false,
        closeOnEsc: false,
        closeOnCancel: false
    });
    
    dialog.open();
    
    $('#pwd-submit-btn').click(function() {
