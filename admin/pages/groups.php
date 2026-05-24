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
$(function() {
    mdui.mutation();

    function loadGroups(search) {
        var params = {};
        if (search) params.search = search;
        $.get('../api.php?action=groups_list', params, function(res) {
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
        if (!name) { showToast('请输入组名', 'error'); return; }
        $.post('../api.php?action=group_create', { name: name }, function(res) {
            if (checkResponse(res)) return;
            showToast('小组创建成功', 'success');
            $('#group-name-input').val('');
            loadGroups($('#group-search').val().trim());
        }, 'json').fail(handleAjaxFail);
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
        if (!name) { showToast('请输入组名', 'error'); return; }
        $.post('../api.php?action=group_update', { id: id, name: name }, function(res) {
            if (checkResponse(res)) return;
            showToast('保存成功', 'success');
            loadGroups($('#group-search').val().trim());
            var dialog = mdui.Dialog.getInstance(document.getElementById('group-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
    });

    $(document).on('click', '.btn-del-group', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        mdui.confirm('确定要删除小组「' + name + '」吗？该小组成员将变为未分组状态。', function() {
            $.post('../api.php?action=group_delete', { id: id }, function(res) {
                if (checkResponse(res)) return;
                showToast('已删除', 'success');
                loadGroups($('#group-search').val().trim());
            }, 'json').fail(handleAjaxFail);
        });
    });

    $(document).on('click', '.btn-add-members', function() {
        var groupId = $(this).data('id');
        $('#gm-group-id').val(groupId);
        $.get('../api.php?action=ungrouped_students', function(res) {
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
        }, 'json').fail(handleAjaxFail);
        var dialog = new mdui.Dialog('#group-members-dialog', { modal: true, history: false });
        dialog.open();
    });

    $('#gm-submit-btn').click(function() {
        var groupId = $('#gm-group-id').val();
        var ids = [];
        $('#gm-checkboxes input:checked').each(function() { ids.push($(this).val()); });
        if (!ids.length) { showToast('请选择要添加的成员', 'error'); return; }
        $.post('../api.php?action=group_add_members', { group_id: groupId, student_ids: ids.join(',') }, function(res) {
            if (checkResponse(res)) return;
            showToast('成员添加成功', 'success');
            loadGroups($('#group-search').val().trim());
            var dialog = mdui.Dialog.getInstance(document.getElementById('group-members-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
    });
});
</script>
