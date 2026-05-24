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
$(function() {
    mdui.mutation();

    function loadUsers() {
        $.get('../api.php?action=users_list', function(res) {
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
        if (!username || !password) { showToast('请填写完整', 'error'); return; }
        $.post('../api.php?action=user_create', { username: username, password: password }, function(res) {
            if (checkResponse(res)) return;
            showToast('用户创建成功', 'success');
            loadUsers();
            var dialog = mdui.Dialog.getInstance(document.getElementById('user-create-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
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
        $.post('../api.php?action=user_update', { id: id, status: status }, function(res) {
            if (checkResponse(res)) return;
            showToast('用户更新成功', 'success');
            loadUsers();
            var dialog = mdui.Dialog.getInstance(document.getElementById('user-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
    });

    $(document).on('click', '.btn-reset-pwd', function() {
        var id = $(this).data('id');
        var username = $(this).data('username');
        mdui.confirm('确定要重置用户「' + username + '」的密码吗？', function() {
            $.post('../api.php?action=user_reset_password', { id: id }, function(res) {
                if (checkResponse(res)) return;
                mdui.alert('新密码：' + res.new_password, function() {
                    showToast('密码重置成功', 'success');
                    loadUsers();
                });
            }, 'json').fail(handleAjaxFail);
        });
    });

    $(document).on('click', '.btn-del-user', function() {
        var id = $(this).data('id');
        var username = $(this).data('username');
        mdui.confirm('确定要删除用户「' + username + '」吗？', function() {
            $.post('../api.php?action=user_delete', { id: id }, function(res) {
                if (checkResponse(res)) return;
                showToast('用户已删除', 'success');
                loadUsers();
            }, 'json').fail(handleAjaxFail);
        });
    });
});
</script>
