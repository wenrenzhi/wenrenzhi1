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
    <?php if (isset($mustChangePwd) && $mustChangePwd): ?>
    var dialog = new mdui.Dialog('#must-change-pwd-dialog', { 
        modal: true, 
        history: false,
        closeOnEsc: false,
        closeOnCancel: false
    });
    
    dialog.open();
    
    $('#pwd-submit-btn').click(function() {
        var pwd1 = $('#new-pwd-input').val();
        var pwd2 = $('#new-pwd-confirm').val();
        if (!pwd1 || pwd1.length < 6) {
            showToast('密码至少6位', 'error');
            return;
        }
        if (pwd1 !== pwd2) {
            showToast('两次密码不一致', 'error');
            return;
        }
        $.post('api.php', {
            action: 'change_pwd',
            password: pwd1
        }, function(res) {
            if (checkResponse(res)) return;
            showToast('密码修改成功', 'success');
            dialog.close();
            location.reload();
        }).fail(handleAjaxFail);
    });
    <?php endif; ?>
    mdui.mutation();
});
</script>
</body>
</html>
