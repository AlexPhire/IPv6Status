<?php

class Config
{

    public static function render(Typecho_Widget_Helper_Form $form)
    { 
        if (isset($_GET['action']) && $_GET['action'] == 'recheck') {
            while (ob_get_level()) ob_end_clean();
            error_reporting(0);
            ini_set('display_errors', 0);
            $support = Helper::detectAndCacheIpv6Support();
            header('Content-Type: application/json');
            echo json_encode(['support' => $support]);
            exit;
        }

        $support = Helper::getCachedDetection();
        $disabled = !$support ? 'disabled="disabled"' : '';

        $icons = Icons::getAll();
        $warningIcon = $icons['warning'];
        $loadingIcon = $icons['loading'];
        $refreshIcon = $icons['refresh'];
        $timeoutIcon = $icons['timeout'];
        $successIcon = $icons['success'];
        $errorIcon = $icons['error'];
        $infoIcon = $icons['info'];

        $styles = array(
            'badge'  => '徽章样式',
            'glass'  => '毛玻璃样式',
            'signal' => '信号样式',
            'dot'    => '圆点样式',
        );
        $styleSelect = new Typecho_Widget_Helper_Form_Element_Select(
            'style',
            $styles,
            'badge',
            _t('显示样式'),
            _t('选择 IPv6 状态标识的显示样式')
        );
        $styleSelect->setAttribute('id', 'style-select');
        if ($disabled) $styleSelect->setAttribute('disabled', 'disabled');
        $form->addInput($styleSelect);

        $showText = new Typecho_Widget_Helper_Form_Element_Radio(
            'show_text',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('显示状态文字'),
            _t('是否显示“您正在使用 IPv6 ✓”或“本站支持 IPv6”等文字')
        );
        if ($disabled) $showText->setAttribute('disabled', 'disabled');
        $form->addInput($showText);

        $darkMode = new Typecho_Widget_Helper_Form_Element_Radio(
            'dark_mode',
            array('1' => _t('开启'), '0' => _t('关闭')),
            '1',
            _t('暗色模式适配'),
            _t('是否跟随系统暗色主题自动切换配色')
        );
        if ($disabled) $darkMode->setAttribute('disabled', 'disabled');
        $form->addInput($darkMode);

        $autoInsert = new Typecho_Widget_Helper_Form_Element_Radio(
            'auto_insert',
            array('1' => _t('自动插入（推荐）'), '0' => _t('手动插入')),
            '1',
            _t('插入方式'),
            _t('选择“自动插入”则无需修改主题文件,默认插入到模版的footer.php文件的最下方；选择“手动插入”则需在主题文件的对应位置添加 &lt;?php Ipv6Status_Plugin::render(); ?&gt;')
        );
        if ($disabled) $autoInsert->setAttribute('disabled', 'disabled');
        $form->addInput($autoInsert);

        $notice = '';
        if (!$support) {
            $notice = <<<HTML
<div id="ipv6-notice" style="padding: 12px 18px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 15px; border-radius: 4px;">
    <div id="notice-content">
        <span style="color: #856404;">{$warningIcon} 检测到您的网站 <span style="color: #d39e00; font-weight: bold;">暂不支持IPv6</span></span><br>
        当前域名 <code>{$_SERVER['HTTP_HOST']}</code> 未解析到IPv6地址（AAAA 记录），建议您启用IPv6后重新检测。
    </div>
    <button id="recheck-btn" style="margin-top: 8px; padding: 4px 14px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">{$refreshIcon} 重新检测</button>
    <span style="margin-left: 10px; color: #6c757d; font-size: 12px;">（点击后自动检测，无需刷新页面）</span>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var recheckBtn = document.getElementById('recheck-btn');
    var noticeDiv = document.getElementById('ipv6-notice');
    var noticeContent = document.getElementById('notice-content');

    var icons = {
        warning: `{$warningIcon}`,
        loading: `{$loadingIcon}`,
        refresh: `{$refreshIcon}`,
        timeout: `{$timeoutIcon}`,
        success: `{$successIcon}`,
        error: `{$errorIcon}`
    };

    function setNotice(html, type) {
        if (!noticeContent) return;
        noticeContent.innerHTML = html;
        var bg, border;
        if (type === 'success') {
            bg = '#d4edda'; border = '#28a745';
        } else if (type === 'error') {
            bg = '#f8d7da'; border = '#dc3545';
        } else if (type === 'timeout') {
            bg = '#fff3cd'; border = '#ffc107';
        } else {
            bg = '#fff3cd'; border = '#ffc107';
        }
        noticeDiv.style.background = bg;
        noticeDiv.style.borderLeftColor = border;
    }

    if (recheckBtn) {
        recheckBtn.addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = icons.loading + ' 检测中...';
            btn.style.opacity = '0.7';
            setNotice(icons.loading + ' 正在检测域名IPv6解析，请稍候...', 'loading');

            var xhr = new XMLHttpRequest();
            var url = window.location.href;
            if (url.indexOf('action=') !== -1) {
                url = url.replace(/action=[^&]*/, 'action=recheck');
            } else {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'action=recheck';
            }
            xhr.open('GET', url, true);
            xhr.timeout = 15000;
            xhr.ontimeout = function() {
                setNotice(icons.timeout + ' 检测超时，请检查网络或刷新页面重试。', 'timeout');
                btn.disabled = false;
                btn.innerHTML = icons.refresh + ' 重新检测';
                btn.style.opacity = '1';
            };
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.support) {
                            setNotice(icons.success + ' 检测到您的域名已解析到IPv6地址，插件可正常使用。', 'success');
                            var formElements = document.querySelectorAll('.typecho-option input, .typecho-option select');
                            formElements.forEach(function(el) { el.disabled = false; });
                            document.querySelectorAll('.typecho-option').forEach(function(el) {
                                el.style.opacity = '1';
                                el.style.pointerEvents = 'auto';
                            });
                            var lockMsg = document.querySelector('.typecho-option + p');
                            if (lockMsg && lockMsg.textContent.includes('设置已锁定')) {
                                lockMsg.remove();
                            }
                        } else {
                            setNotice(icons.warning + ' 仍未检测到IPv6地址，请确认域名AAAA记录已正确配置后重试。', 'error');
                        }
                    } catch (e) {
                        console.error('Invalid JSON:', xhr.responseText);
                        setNotice(icons.error + ' 服务器返回数据格式错误，请刷新页面重试。', 'error');
                    }
                } else {
                    setNotice(icons.error + ' 请求失败（HTTP ' + xhr.status + '），请刷新页面重试。', 'error');
                }
                btn.disabled = false;
                btn.innerHTML = icons.refresh + ' 重新检测';
                btn.style.opacity = '1';
            };
            xhr.onerror = function() {
                setNotice(icons.error + ' 网络错误，请检查网络连接或刷新页面。', 'error');
                btn.disabled = false;
                btn.innerHTML = icons.refresh + ' 重新检测';
                btn.style.opacity = '1';
            };
            xhr.send();
        });
    }
});
</script>
HTML;
        } else {
            $notice = '<div id="ipv6-notice" style="padding: 12px 18px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; margin-bottom: 15px;">' . $successIcon . ' 检测到您的网站支持 IPv6，插件可正常使用。</div>';
        }
        echo $notice;

        if (!$support) {
            echo <<<HTML
<style>
    .typecho-option { opacity: 0.6; pointer-events: none; }
    .typecho-option input, .typecho-option select { cursor: not-allowed; }
    .typecho-option label { color: #6c757d; }
    .typecho-option .description { color: #6c757d; }
</style>
<p style="color: #856404; background: #fff3cd; padding: 8px 12px; border-radius: 4px; border: 1px solid #ffeeba; margin-top: 10px;">
    <strong>{$infoIcon} 设置已锁定</strong> — 您的网站暂不支持IPv6，请先配置IPv6后再使用此插件。
</p>
HTML;
        }

        echo <<<CSS
<style>
    .ipv6-icon {
        display: inline-block;
        width: 18px;
        height: 18px;
        vertical-align: middle;
        margin-right: 2px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .ipv6-spin {
        animation: ipv6-spin 1s linear infinite;
    }
    @keyframes ipv6-spin {
        100% { transform: rotate(360deg); }
    }
    #ipv6-notice .ipv6-icon {
        stroke-width: 2;
    }
    #recheck-btn .ipv6-icon {
        width: 16px;
        height: 16px;
        stroke-width: 2.5;
    }
</style>
CSS;
    }
}
