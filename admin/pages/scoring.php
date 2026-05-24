<?php
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
$(function() {
    mdui.mutation();

    <?php if ($currentPeriod): ?>
    var currentPeriodId = <?php echo $currentPeriod['id']; ?>;

    $.get('../api.php?action=students_list', { status: '1' }, function(res) {
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

    $.get('../api.php?action=groups_list', function(res) {
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
            if (!groupId) { showToast('请选择一个小组', 'error'); return; }
            $.get('../api.php?action=group_members', { group_id: groupId }, function(members) {
                if (!members.length) { showToast('该小组没有成员', 'error'); return; }
                var ids = $.map(members, function(m) { return m.id; });
                submitScoring(ids.join(','));
            }, 'json').fail(handleAjaxFail);
        } else {
            $('#scoring-student-checkboxes input:checked').each(function() { studentIds.push($(this).val()); });
            if (!studentIds.length) { showToast('请选择至少一位同学', 'error'); return; }
            submitScoring(studentIds.join(','));
        }
    });

    function submitScoring(studentIds) {
        var score = parseFloat($('#scoring-score').val());
        var type = $('#scoring-type').val();
        var reason = $('#scoring-reason').val().trim();
        var extraNote = $('#scoring-extra-note').val().trim();
        if (!score || score <= 0) { showToast('请输入有效的分数', 'error'); return; }
        $.post('../api.php?action=record_create', {
            student_ids: studentIds,
            period_id: currentPeriodId,
            type: type,
            score: score,
            reason: reason,
            extra_note: extraNote
        }, function(res) {
            if (checkResponse(res)) return;
            showToast('操作成功，共 ' + res.count + ' 条记录', 'success');
            $('#scoring-score').val('1');
            $('#scoring-reason').val('');
            $('#scoring-extra-note').val('');
            $('#scoring-student-checkboxes input').prop('checked', false);
            mdui.mutation();
            loadRecords(1);
        }, 'json').fail(handleAjaxFail);
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

        $.get('../api.php?action=records_list', params, function(res) {
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
        if (!score || score <= 0) { showToast('请输入有效的分数', 'error'); return; }
        $.post('../api.php?action=record_update', { id: id, score: score, type: type, reason: reason }, function(res) {
            if (checkResponse(res)) return;
            showToast('保存成功', 'success');
            loadRecords(currentRecordsPage);
            var dialog = mdui.Dialog.getInstance(document.getElementById('record-edit-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
    });

    $(document).on('click', '.btn-del-record', function() {
        var id = $(this).data('id');
        mdui.confirm('确定要删除这条记录吗？', function() {
            $.post('../api.php?action=record_delete', { id: id }, function(res) {
                if (checkResponse(res)) return;
                showToast('已删除', 'success');
                loadRecords(currentRecordsPage);
            }, 'json').fail(handleAjaxFail);
        });
    });
});
</script>
