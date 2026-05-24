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
$(function() {
    mdui.mutation();

    function loadPeriods() {
        $.get('../api.php?action=periods_list', function(res) {
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
        if (!startTime) { showToast('请选择开始时间', 'error'); return; }
        $.post('../api.php?action=period_create', { start_time: startTime, base_score: baseScore, remark: remark }, function(res) {
            if (checkResponse(res)) return;
            showToast('周期创建成功', 'success');
            loadPeriods();
            var dialog = mdui.Dialog.getInstance(document.getElementById('period-create-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
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
        $.post('../api.php?action=period_update', { id: id, remark: remark }, function(res) {
            if (checkResponse(res)) return;
            showToast('备注保存成功', 'success');
            loadPeriods();
            var dialog = mdui.Dialog.getInstance(document.getElementById('period-remark-dialog'));
            if (dialog) dialog.close();
        }, 'json').fail(handleAjaxFail);
    });

    $(document).on('click', '.btn-end-period', function() {
        var id = $(this).data('id');
        mdui.confirm('确定要结束这个统计周期吗？', function() {
            $.post('../api.php?action=period_end', { id: id }, function(res) {
                if (checkResponse(res)) return;
                showToast('周期已结束', 'success');
                loadPeriods();
            }, 'json').fail(handleAjaxFail);
        });
    });
});
</script>
