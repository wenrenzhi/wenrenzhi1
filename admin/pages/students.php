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
$(function() {
    mdui.mutation();

    function loadStudents(search) {
        var params = { status: 'all' };
        if (search) params.search = search;
        $.get('../api.php?action=students_list', params, function(res) {
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
        if (!names) { showToast('请输入姓名', 'error'); return; }
        $.post('../api.php?action=student_create', { names: names }, function(res) {
            if (checkResponse(res)) return;
            showToast('成功导入 ' + res.count + ' 位同学', 'success');
            $('#import-names').val('');
            loadStudents();
        }, 'json').fail(handleAjaxFail);
    });

    $('#btn-add-student').click(function() {
        mdui.prompt('请输入姓名', function(name) {
            if (!name || !name.trim()) return;
            $.post('../api.php?action=student_create', { names: name.trim() }, function(res) {
                if (checkResponse(res)) return;
                showToast('添加成功', 'success');
                loadStudents();
            }, 'json').fail(handleAjaxFail);
        });
    });

    function loadGroupOptions(selectedId) {
        $.get('../api.php?action=groups_list', function(res) {
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
        if (!name) { showToast('请输入姓名', 'error'); return; }
        $.post('../api.php?action=student_update', { id: id, name: name, group_id: groupId }, function(res) {
            if (checkResponse(res)) return;
            showToast('保存成功', 'success');
            loadStudents($('#student-search').val().trim());
            var dialog = mdui.Dialog.getInstance(document.getElementById('student-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
    });

    $(document).on('click', '.btn-del-student', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        mdui.confirm('确定要删除同学「' + name + '」吗？', function() {
            $.post('../api.php?action=student_delete', { id: id }, function(res) {
                if (checkResponse(res)) return;
                showToast('已删除', 'success');
                loadStudents($('#student-search').val().trim());
            }, 'json').fail(handleAjaxFail);
        });
    });
});
</script>
