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
$(function() {
    mdui.mutation();

    var currentPage = 1;

    function loadLogs(page) {
        currentPage = page || 1;
        $.get('../api.php?action=operations_list', { page: currentPage }, function(res) {
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
