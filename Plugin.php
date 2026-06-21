<?php
/**
 * IPv6 状态标识
 *
 * @package IPv6Status
 * @title   IPv6 状态标识
 * @author  AlexPhire
 * @version 1.0.1
 * @link    https://github.com/AlexPhire/IPv6Status
 */
class IPv6Status_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     */
    public static function activate()
    {
        self::detectAndCacheIpv6Support();
        Typecho_Plugin::factory('Widget_Archive')->footer = array('IPv6Status_Plugin', 'autoRender');
        return _t('IPv6 状态标识插件已启用。');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        $db = Typecho_Db::get();
        $db->query($db->delete('table.options')->where('name = ?', 'IPv6Status_detected'));
        $db->query($db->delete('table.options')->where('name = ?', 'IPv6Status_last_check'));
    }

    private static function detectAndCacheIpv6Support()
    {
        $domain = $_SERVER['HTTP_HOST'];
        if (($pos = strpos($domain, ':')) !== false) {
            $domain = substr($domain, 0, $pos);
        }
        $support = false;
        if (function_exists('dns_get_record')) {
            set_time_limit(5);
            $records = @dns_get_record($domain, DNS_AAAA);
            if (!empty($records)) {
                $support = true;
            }
        }
        $db = Typecho_Db::get();
        $time = time();

      
        $row = $db->fetchRow($db->select('*')->from('table.options')->where('name = ?', 'IPv6Status_detected'));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $support ? '1' : '0'))->where('name = ?', 'IPv6Status_detected'));
        } else {
            $db->query($db->insert('table.options')->rows(array('name' => 'IPv6Status_detected', 'value' => $support ? '1' : '0', 'user' => 0)));
        }

   
        $row = $db->fetchRow($db->select('*')->from('table.options')->where('name = ?', 'IPv6Status_last_check'));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $time))->where('name = ?', 'IPv6Status_last_check'));
        } else {
            $db->query($db->insert('table.options')->rows(array('name' => 'IPv6Status_last_check', 'value' => $time, 'user' => 0)));
        }

        return $support;
    }

    private static function getCachedDetection()
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'IPv6Status_detected'));
        $lastRow = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'IPv6Status_last_check'));

        $lastCheck = $lastRow ? (int)$lastRow['value'] : 0;
        if (time() - $lastCheck > 21600) {
            return self::detectAndCacheIpv6Support();
        }

        if ($row) {
            return $row['value'] == '1';
        }
        return self::detectAndCacheIpv6Support();
    }

    private static function getClientIP()
    {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED']) && !empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR']) && !empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED']) && !empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        if (strpos($ip, '::ffff:') === 0) {
            $ip = substr($ip, 7);
        }
        return $ip;
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
      
        if (isset($_GET['action']) && $_GET['action'] == 'recheck') {
            while (ob_get_level()) ob_end_clean();
            error_reporting(0);
            ini_set('display_errors', 0);
            $support = self::detectAndCacheIpv6Support();
            header('Content-Type: application/json');
            echo json_encode(['support' => $support]);
            exit;
        }

        $support = self::getCachedDetection();
        $disabled = !$support ? 'disabled="disabled"' : '';

 
        $warningIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M12 3C7.03 3 3 7.03 3 12s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z"/></svg>';
        $loadingIcon = '<svg class="ipv6-icon ipv6-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 22v-4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M22 12h-4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>';
        $refreshIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
        $timeoutIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';
        $successIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>';
        $errorIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>';
        $infoIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';


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
            _t('选择“自动插入”则无需修改主题文件；选择“手动插入”则需在主题中添加 &lt;?php IPv6Status_Plugin::render(); ?&gt;')
        );
        if ($disabled) $autoInsert->setAttribute('disabled', 'disabled');
        $form->addInput($autoInsert);

       
        $notice = '';
        if (!$support) {
            $notice = <<<HTML
<div id="ipv6-notice" style="padding: 12px 18px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 15px; border-radius: 4px;">
    <div id="notice-content">
        <span style="color: #856404;">{$warningIcon} 检测到您的网站 <span style="color: #d39e00; font-weight: bold;">暂不支持 IPv6</span></span><br>
        当前域名 <code>{$_SERVER['HTTP_HOST']}</code> 未解析到 IPv6 地址（AAAA 记录），建议您启用 IPv6 后重新检测。
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
        if (type === 'success') { bg = '#d4edda'; border = '#28a745'; }
        else if (type === 'error') { bg = '#f8d7da'; border = '#dc3545'; }
        else if (type === 'timeout') { bg = '#fff3cd'; border = '#ffc107'; }
        else { bg = '#fff3cd'; border = '#ffc107'; }
        noticeDiv.style.background = bg;
        noticeDiv.style.borderLeftColor = border;
    }

    if (recheckBtn) {
        recheckBtn.addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = icons.loading + ' 检测中...';
            btn.style.opacity = '0.7';
            setNotice(icons.loading + ' 正在检测域名 IPv6 解析，请稍候...', 'loading');

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
                            setNotice(icons.success + ' 检测到您的域名已解析到 IPv6 地址，插件可正常使用。', 'success');
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
                            setNotice(icons.warning + ' 仍未检测到 IPv6 地址，请确认域名 AAAA 记录已正确配置后重试。', 'error');
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
    <strong>{$infoIcon} 设置已锁定</strong> — 您的网站暂不支持 IPv6，请先配置 IPv6 后再使用此插件。
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

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    
    public static function autoRender()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('IPv6Status');
        if ($options->auto_insert == '1') {
            self::render();
        }
    }

    
    public static function render()
    {
        $siteSupport = self::getCachedDetection();
        if (!$siteSupport) {
            return;
        }

        $options = Typecho_Widget::widget('Widget_Options')->plugin('IPv6Status');
        $style = $options->style ?: 'badge';
        $showText = isset($options->show_text) ? $options->show_text : '1';
        $darkMode = isset($options->dark_mode) ? $options->dark_mode : '1';

        $clientIP = self::getClientIP();
        $isIPv6 = filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isIPv4 = filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        if (!$isIPv4 && !$isIPv6) {
            $isIPv4 = true;
        }
        $statusClass = $isIPv6 ? 'on' : 'off';
        $statusDisplay = $showText ? ($isIPv6 ? '您正在使用 IPv6 ✓' : '本站支持 IPv6') : '';

        switch ($style) {
            case 'badge':
                echo self::renderBadge($statusClass, $statusDisplay, $darkMode);
                break;
            case 'glass':
                echo self::renderGlass($statusClass, $statusDisplay, $darkMode);
                break;
            case 'signal':
                echo self::renderSignal($statusClass, $darkMode);
                break;
            case 'dot':
            default:
                echo self::renderDot($statusClass, $darkMode);
                break;
        }
    }

    /* ---------- 样式1：徽章样式 ---------- */
    private static function renderBadge($statusClass, $statusDisplay, $darkMode)
    {
        $darkCss = $darkMode ? '' : '/* 暗色模式已关闭 */';
        return <<<HTML
<style>
.ipv6-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px 8px;
    padding: 4px 10px 4px 8px;
    background: rgba(45, 123, 182, 0.10);
    border: 1px solid rgba(45, 123, 182, 0.25);
    border-radius: 24px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 12px;
    line-height: 1.3;
    color: #2c3e50;
    cursor: default;
    user-select: none;
    flex-wrap: wrap;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}
.ipv6-badge .hex-icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    display: block;
}
.ipv6-badge .badge-text {
    display: flex;
    align-items: baseline;
    gap: 3px 6px;
    flex-wrap: wrap;
}
.ipv6-badge .badge-text .label {
    font-weight: 700;
    color: #1a4d6e;
    letter-spacing: 0.2px;
}
.ipv6-badge .badge-text .label .v6 {
    color: #2d7bb6;
}
.ipv6-badge .badge-text .divider {
    color: rgba(44, 62, 80, 0.25);
    font-weight: 300;
    margin: 0 1px;
}
.ipv6-badge .badge-text .status {
    font-weight: 500;
    color: #5a6c7d;
}
.ipv6-badge .badge-text .status.active {
    color: #1a8a4a;
    font-weight: 600;
}
.ipv6-badge .badge-text .status .check {
    display: inline-block;
    margin-left: 1px;
    font-weight: 700;
}
.ipv6-badge .dot-indicator {
    flex-shrink: 0;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #b0c4d9;
    transition: background 0.3s ease;
}
.ipv6-badge .dot-indicator.on {
    background: #22a65e;
    box-shadow: 0 0 8px rgba(34, 166, 94, 0.35);
}
@media (max-width: 600px) {
    .ipv6-badge { font-size: 10px; padding: 3px 8px 3px 6px; gap: 4px 6px; border-radius: 20px; }
    .ipv6-badge .hex-icon { width: 20px; height: 20px; }
}
@media (max-width: 420px) {
    .ipv6-badge { font-size: 9px; padding: 2px 6px 2px 5px; gap: 3px 4px; }
    .ipv6-badge .hex-icon { width: 16px; height: 16px; }
}
{$darkCss}
@media (prefers-color-scheme: dark) {
    .ipv6-badge {
        background: rgba(45, 123, 182, 0.12);
        border-color: rgba(45, 123, 182, 0.30);
        color: #dce3ea;
    }
    .ipv6-badge .badge-text .label { color: #8bb9e6; }
    .ipv6-badge .badge-text .label .v6 { color: #5fa3d9; }
    .ipv6-badge .badge-text .status { color: #a0b4c8; }
    .ipv6-badge .badge-text .status.active { color: #4cdb8a; }
    .ipv6-badge .badge-text .divider { color: rgba(160, 180, 200, 0.30); }
    .ipv6-badge .dot-indicator { background: #5a6f84; }
    .ipv6-badge .dot-indicator.on { background: #4cdb8a; box-shadow: 0 0 10px rgba(76, 219, 138, 0.30); }
}
</style>
<div style="text-align: center;">
    <div class="ipv6-badge">
        <svg class="hex-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs><linearGradient id="hexGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#3a8fd4" /><stop offset="100%" stop-color="#1a5f8a" />
            </linearGradient></defs>
            <polygon points="50,5 95,27.5 95,72.5 50,95 5,72.5 5,27.5" fill="url(#hexGrad)" stroke="#1a4d6e" stroke-width="2.5" />
            <polygon points="50,12 88,30.5 88,69.5 50,88 12,69.5 12,30.5" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="1.5" />
            <text x="50" y="51" font-family="Arial, Helvetica, sans-serif" font-size="46" font-weight="800" fill="white" text-anchor="middle" dominant-baseline="central" style="text-shadow: 0 2px 6px rgba(0,0,0,0.18);">6</text>
        </svg>
        <div class="badge-text">
            <span class="label">IPv<span class="v6">6</span></span>
            <span class="divider">·</span>
            <span class="status {$statusClass}">{$statusDisplay}</span>
        </div>
        <span class="dot-indicator {$statusClass}"></span>
    </div>
</div>
HTML;
    }

    /* ---------- 样式2：毛玻璃样式 ---------- */
    private static function renderGlass($statusClass, $statusDisplay, $darkMode)
    {
        $darkCss = $darkMode ? '' : '/* 暗色模式已关闭 */';
        return <<<HTML
<style>
.ipv6-glass {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 16px 6px 12px;
    background: rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.30);
    border-radius: 40px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.5);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #1a2a3a;
    cursor: default;
    user-select: none;
    flex-wrap: wrap;
    line-height: 1.2;
}
.ipv6-glass .badge-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: linear-gradient(145deg, #4a90d9, #1e5f8a);
    border-radius: 50%;
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    text-shadow: 0 1px 3px rgba(0,0,0,0.2);
    box-shadow: 0 2px 8px rgba(30, 95, 138, 0.30);
    flex-shrink: 0;
}
.ipv6-glass .glass-label { font-weight: 600; letter-spacing: 0.3px; color: #1f3a57; margin-right: 2px; }
.ipv6-glass .glass-label .v6 { color: #2d7bb6; font-weight: 700; }
.ipv6-glass .glass-divider { color: rgba(31, 58, 87, 0.20); font-weight: 300; margin: 0 2px; }
.ipv6-glass .glass-status { display: inline-flex; align-items: center; gap: 6px; color: #4a5b6b; font-weight: 450; }
.ipv6-glass .glass-status.active { color: #1a8a4a; font-weight: 500; }
.ipv6-glass .status-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #b8c9d9;
    transition: background 0.3s ease, box-shadow 0.3s ease;
}
.ipv6-glass .status-dot.on { background: #22a65e; box-shadow: 0 0 0 2px rgba(34, 166, 94, 0.25); }
@media (max-width: 600px) {
    .ipv6-glass { font-size: 11px; padding: 4px 12px 4px 10px; gap: 6px; border-radius: 30px; }
    .ipv6-glass .badge-icon { width: 22px; height: 22px; font-size: 13px; }
}
@media (max-width: 420px) {
    .ipv6-glass { font-size: 10px; padding: 3px 10px 3px 8px; gap: 4px; }
    .ipv6-glass .badge-icon { width: 18px; height: 18px; font-size: 11px; }
}
{$darkCss}
@media (prefers-color-scheme: dark) {
    .ipv6-glass {
        background: rgba(30, 40, 55, 0.50);
        border-color: rgba(255, 255, 255, 0.10);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08);
        color: #dce3ea;
    }
    .ipv6-glass .glass-label { color: #b0cce0; }
    .ipv6-glass .glass-label .v6 { color: #6da9e6; }
    .ipv6-glass .glass-divider { color: rgba(200, 215, 230, 0.25); }
    .ipv6-glass .glass-status { color: #a0b8cc; }
    .ipv6-glass .glass-status.active { color: #4cdb8a; }
    .ipv6-glass .status-dot { background: #5a6f84; }
    .ipv6-glass .status-dot.on { background: #4cdb8a; box-shadow: 0 0 0 2px rgba(76, 219, 138, 0.30); }
    .ipv6-glass .badge-icon { background: linear-gradient(145deg, #3a7fc9, #14527a); box-shadow: 0 2px 8px rgba(20, 82, 122, 0.40); }
}
</style>
<div style="text-align: center;">
    <div class="ipv6-glass">
        <span class="badge-icon">6</span>
        <span class="glass-label">IPv<span class="v6">6</span></span>
        <span class="glass-divider">·</span>
        <span class="glass-status {$statusClass}">{$statusDisplay}</span>
        <span class="status-dot {$statusClass}"></span>
    </div>
</div>
HTML;
    }

    /* ---------- 样式3：信号样式 ---------- */
    private static function renderSignal($statusClass, $darkMode)
    {
        $darkCss = $darkMode ? '' : '/* 暗色模式已关闭 */';
        return <<<HTML
<style>
.ipv6-signal {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #4a5b6b;
    cursor: default;
    user-select: none;
    line-height: 1;
}
.ipv6-signal .signal-icon {
    display: inline-block;
    width: 22px;
    height: 16px;
    flex-shrink: 0;
}
.ipv6-signal .signal-icon svg {
    display: block;
    width: 100%;
    height: 100%;
    fill: none;
    stroke: currentColor;
    stroke-width: 2.2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.ipv6-signal.on { color: #1a8a4a; }
.ipv6-signal.off { color: #9aabb8; }
.ipv6-signal .ipv6-text .num6 { font-weight: 700; color: inherit; }
{$darkCss}
@media (prefers-color-scheme: dark) {
    .ipv6-signal.on { color: #4cdb8a; }
    .ipv6-signal.off { color: #7a8fa0; }
}
@media (max-width: 420px) {
    .ipv6-signal { font-size: 11px; gap: 4px; }
    .ipv6-signal .signal-icon { width: 18px; height: 13px; }
}
</style>
<div style="text-align: center;">
    <span class="ipv6-signal {$statusClass}">
        <span class="signal-icon">
            <svg viewBox="0 0 30 22" xmlns="http://www.w3.org/2000/svg">
                <path d="M3,16 C9,6 21,6 27,16" />
                <path d="M9,19 C13,12 17,12 21,19" />
                <path d="M13,21.5 C15,18.5 15,18.5 17,21.5" />
            </svg>
        </span>
        <span class="ipv6-text">IPv<span class="num6">6</span></span>
    </span>
</div>
HTML;
    }

    /* ---------- 样式4：圆点样式 ---------- */
    private static function renderDot($statusClass, $darkMode)
    {
        $darkCss = $darkMode ? '' : '/* 暗色模式已关闭 */';
        return <<<HTML
<style>
.ipv6-dot {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #6a7b8c;
    cursor: default;
    user-select: none;
    line-height: 1;
}
.ipv6-dot .dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #b8c9d9;
    flex-shrink: 0;
}
.ipv6-dot.on .dot {
    background: #22a65e;
    box-shadow: 0 0 0 2px rgba(34, 166, 94, 0.25);
}
.ipv6-dot.on { color: #1a8a4a; }
.ipv6-dot .num6 { font-weight: 700; }
.ipv6-dot.on .num6 { color: #22a65e; }
.ipv6-dot.off .num6 { color: #b8c9d9; }
{$darkCss}
@media (prefers-color-scheme: dark) {
    .ipv6-dot { color: #a0b4c8; }
    .ipv6-dot .dot { background: #5a6f84; }
    .ipv6-dot.on { color: #4cdb8a; }
    .ipv6-dot.on .dot { background: #4cdb8a; box-shadow: 0 0 0 2px rgba(76, 219, 138, 0.30); }
    .ipv6-dot.on .num6 { color: #4cdb8a; }
    .ipv6-dot.off .num6 { color: #5a6f84; }
}
@media (max-width: 420px) {
    .ipv6-dot { font-size: 11px; gap: 4px; }
    .ipv6-dot .dot { width: 8px; height: 8px; }
}
</style>
<div style="text-align: center;">
    <span class="ipv6-dot {$statusClass}">
        <span class="dot"></span>
        <span class="ipv6-text">IPv<span class="num6">6</span></span>
    </span>
</div>
HTML;
    }
}
