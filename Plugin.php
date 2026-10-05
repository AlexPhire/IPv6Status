<?php
/**
 * IPv6 状态标识
 *
 * @package IPv6Status
 * @title   IPv6 状态标识
 * @author  AlexPhire
 * @version 1.0.2
 * @link    https://github.com/AlexPhire/IPv6Status
class IPv6Status_Plugin implements Typecho_Plugin_Interface
{
    const TTL_YES      = 86400; 
    const TTL_NO       = 3600;  
    const TTL_ERR_MIN  = 300;   
    const TTL_ERR_MAX  = 3600; 
    const FAIL_LIMIT   = 3;     
    const LOCK_TTL     = 90;    
    const PROBE_BUDGET = 4.0;   

    const OPT_STATE = 'IPv6Status_state';
    const OPT_LOCK  = 'IPv6Status_lock';

    private static $refreshScheduled = false;
    private static $stateCache       = null;

    public static function activate()
    {
        
        $db = Typecho_Db::get();
        foreach (array('IPv6Status_detected', 'IPv6Status_last_check', self::OPT_LOCK) as $name) {
            $db->query($db->delete('table.options')->where('name = ?', $name));
        }
        self::$stateCache = null;
        self::refresh(true);

        Typecho_Plugin::factory('Widget_Archive')->footer = array('IPv6Status_Plugin', 'autoRender');
        return _t('IPv6 状态标识插件已启用，已完成首次 IPv6 支持检测。');
    }

    public static function deactivate()
    {
        $db = Typecho_Db::get();
        foreach (array(self::OPT_STATE, self::OPT_LOCK, 'IPv6Status_detected', 'IPv6Status_last_check') as $name) {
            $db->query($db->delete('table.options')->where('name = ?', $name));
        }
        self::$stateCache = null;
        return _t('IPv6 状态标识插件已禁用，缓存已清理。');
    }

    private static function loadState()
    {
        if (self::$stateCache !== null) {
            return self::$stateCache;
        }
        $st = array(
            'support' => null, // true / false / null(未探测)
            'ts'      => 0,
            'fail'    => 0,
            'src'     => '',   // dns / doh / mixed
            'domain'  => '',
            'detail'  => '',
        );
        $db  = Typecho_Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', self::OPT_STATE));
        if ($row) {
            $data = @json_decode($row['value'], true);
            if (is_array($data)) {
                $st = array_merge($st, $data);
            }
        }
        self::$stateCache = $st;
        return $st;
    }

    private static function saveState($st)
    {
        $db   = Typecho_Db::get();
        $json = json_encode($st);
        $row  = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', self::OPT_STATE));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $json))->where('name = ?', self::OPT_STATE));
        } else {
            $db->query($db->insert('table.options')->rows(array(
                'name'  => self::OPT_STATE,
                'value' => $json,
                'user'  => 0,
            )));
        }
        self::$stateCache = $st;
    }

    private static function setOption($name, $value)
    {
        $db  = Typecho_Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', $name));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $value))->where('name = ?', $name));
        } else {
            $db->query($db->insert('table.options')->rows(array(
                'name'  => $name,
                'value' => $value,
                'user'  => 0,
            )));
        }
    }

  
    private static function isStale($st)
    {
        if ($st['support'] === null) {
            return true;
        }
        $fail = (int) $st['fail'];
        if ($fail > 0) {
            $ttl = (int) min(self::TTL_ERR_MAX, self::TTL_ERR_MIN * pow(2, $fail - 1));
        } else {
            $ttl = $st['support'] ? self::TTL_YES : self::TTL_NO;
        }
        return (time() - (int) $st['ts']) > $ttl;
    }

    private static function currentDomain()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if (!$host && class_exists('Typecho_Widget')) {
            $host = parse_url(Typecho_Widget::widget('Widget_Options')->siteUrl, PHP_URL_HOST);
        }
        $host = strtolower(trim((string) $host));
        if ($host === '' || $host[0] === '[' || filter_var($host, FILTER_VALIDATE_IP)) {
            return ''; 
        }
        $pos = strpos($host, ':');
        if ($pos !== false) {
            $host = substr($host, 0, $pos);
        }
        
        if (!preg_match('/^[a-z0-9._-]+$/', $host)) {
            return '';
        }
        return trim($host, '.');
    }

   
    private static function domainCandidates()
    {
        $d = self::currentDomain();
        if (!$d) {
            return array();
        }
        $list = array($d);
        if (strpos($d, 'www.') === 0) {
            $list[] = substr($d, 4);
        } else {
            $list[] = 'www.' . $d;
        }
        return array_values(array_unique(array_filter($list)));
    }

   
    private static function probe()
    {
        $candidates = self::domainCandidates();
        if (!$candidates) {
            return array('result' => null, 'src' => '', 'detail' => '无法获取有效域名');
        }

        $deadline   = microtime(true) + self::PROBE_BUDGET;
        $definitive = false; 
        $srcUsed    = array();

        foreach ($candidates as $domain) {
           
            if (function_exists('dns_get_record')) {
                $aaaa = @dns_get_record($domain, DNS_AAAA);
                if (is_array($aaaa)) { 
                    $srcUsed[] = 'dns';
                    if (count($aaaa) > 0) {
                        return array('result' => true, 'src' => 'dns', 'detail' => $domain . ' 有 AAAA 记录');
                    }
                    $a = @dns_get_record($domain, DNS_A);
                    if (is_array($a) && count($a) > 0) {
                        $definitive = true; 
                    }
                }
            }

           
            if (microtime(true) < $deadline) {
                $doh = self::probeDoh($domain, $deadline);
                if ($doh === true) {
                    return array('result' => true, 'src' => 'doh', 'detail' => $domain . ' 有 AAAA 记录(DoH)');
                }
                if ($doh === false) {
                    $srcUsed[] = 'doh';
                    $definitive = true;
                }
            }
        }

        if ($definitive) {
            return array(
                'result' => false,
                'src'    => $srcUsed ? implode('+', array_unique($srcUsed)) : 'mixed',
                'detail' => '权威应答：无 AAAA 记录',
            );
        }
        return array('result' => null, 'src' => '', 'detail' => 'DNS 与 DoH 均未返回可信结果');
    }

    
    private static function probeDoh($domain, $deadline)
    {
        if (function_exists('idn_to_ascii')) {
            $ascii = defined('INTL_IDNA_VARIANT_UTS46')
                ? @idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46)
                : @idn_to_ascii($domain);
            if ($ascii) {
                $domain = $ascii;
            }
        }

        $endpoints = array(
            'https://cloudflare-dns.com/dns-query?name=%s&type=AAAA',
            'https://dns.alidns.com/resolve?name=%s&type=AAAA',
            'https://dns.google/resolve?name=%s&type=AAAA',
        );

        foreach ($endpoints as $tpl) {
            $left = $deadline - microtime(true);
            if ($left <= 0.5) {
                break;
            }
            $body = self::httpGet(sprintf($tpl, rawurlencode($domain)), (int) min(3, floor($left)));
            if ($body === '') {
                continue;
            }
            $data = @json_decode($body, true);
            if (!is_array($data) || !isset($data['Status'])) {
                continue;
            }
            $status = (int) $data['Status'];
            if ($status !== 0 && $status !== 3) {
                continue; 
            }
            if (empty($data['Answer'])) {
                return false;
            }
            foreach ($data['Answer'] as $ans) {
                if (isset($ans['type']) && (int) $ans['type'] === 28) {
                    return true; // 28 = AAAA
                }
            }
            return false;
        }
        return null;
    }

    private static function httpGet($url, $timeout = 3)
    {
        $timeout = max(1, (int) $timeout);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT        => $timeout + 1,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => array('accept: application/dns-json'),
                CURLOPT_USERAGENT      => 'IPv6Status/1.0.2',
            ));
            $body = curl_exec($ch);
            curl_close($ch);
            return is_string($body) ? $body : '';
        }
        $ctx  = stream_context_create(array(
            'http' => array(
                'method'        => 'GET',
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'header'        => "accept: application/dns-json\r\n",
            ),
        ));
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    }

    public static function refresh($force = false)
    {
        $st = self::loadState();
        if (!$force && !self::isStale($st)) {
            return $st['support'] === true;
        }

        if (!$force) {
            $db      = Typecho_Db::get();
            $lockRow = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', self::OPT_LOCK));
            if ($lockRow && (time() - (int) $lockRow['value']) < self::LOCK_TTL) {
                return $st['support'] === true; 
            }
            self::setOption(self::OPT_LOCK, (string) time());
        }

        $p   = self::probe();
        $now = time();
        $st['ts'] = $now;

        if ($p['result'] === null) {
            
            $st['fail']    = (int) $st['fail'] + 1;
            $st['detail']  = $p['detail'];
            if ($st['fail'] >= self::FAIL_LIMIT) {
                $st['support'] = false; 
            }
        } else {
            $st['support'] = (bool) $p['result'];
            $st['fail']    = 0;
            $st['src']     = $p['src'];
            $st['detail']  = $p['detail'];
            $st['domain']  = self::currentDomain();
        }

        self::saveState($st);
        return $st['support'] === true;
    }

   
    public static function scheduleRefresh()
    {
        if (self::$refreshScheduled) {
            return;
        }
        self::$refreshScheduled = true;

        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                @IPv6Status_Plugin::refresh();
            } catch (Exception $e) {
                
            }
        });
    }


    private static function getClientIP()
    {
        $ip = '';
       
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim($_SERVER[$key]);
                break;
            }
        }
        if (strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip    = trim($parts[0]);
        }
        // IPv4 映射地址 ::ffff:1.2.3.4
        if (stripos($ip, '::ffff:') === 0) {
            $ip = substr($ip, 7);
        }
        if (strpos($ip, '%') !== false) { // fe80::1%eth0
            $ip = substr($ip, 0, strpos($ip, '%'));
        }
        return $ip;
    }

   

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        
        if (isset($_GET['action']) && $_GET['action'] == 'recheck') {
            while (ob_get_level()) {
                ob_end_clean();
            }
            error_reporting(0);
            ini_set('display_errors', 0);
            $support = self::refresh(true); // 强制、同步
            $st      = self::loadState();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'support' => $support,
                'detail'  => isset($st['detail']) ? $st['detail'] : '',
                'src'     => isset($st['src']) ? $st['src'] : '',
                'ts'      => isset($st['ts']) ? (int) $st['ts'] : 0,
            ));
            exit;
        }

       
        $st = self::loadState();
        if (self::isStale($st)) {
            self::refresh();
            $st = self::loadState();
        }
        $support = ($st['support'] === true);

        $warningIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4M12 17h.01"/><path d="M12 3C7.03 3 3 7.03 3 12s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z"/></svg>';
        $loadingIcon = '<svg class="ipv6-icon ipv6-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M12 2v4M12 22v-4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M22 12h-4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>';
        $refreshIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
        $timeoutIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';
        $successIcon = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>';
        $errorIcon   = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>';
        $infoIcon    = '<svg class="ipv6-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';

        $iconJson = json_encode(array(
            'warning' => $warningIcon,
            'loading' => $loadingIcon,
            'refresh' => $refreshIcon,
            'timeout' => $timeoutIcon,
            'success' => $successIcon,
            'error'   => $errorIcon,
        ));

        // ----- 样式下拉菜单 -----
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
        $form->addInput($styleSelect);

        // ----- 显示状态文字 -----
        $showText = new Typecho_Widget_Helper_Form_Element_Radio(
            'show_text',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('显示状态文字'),
            _t('是否显示“您正在使用 IPv6 ✓”或“本站支持 IPv6”等文字')
        );
        $form->addInput($showText);

        // ----- 暗色模式适配 -----
        $darkMode = new Typecho_Widget_Helper_Form_Element_Radio(
            'dark_mode',
            array('1' => _t('开启'), '0' => _t('关闭')),
            '1',
            _t('暗色模式适配'),
            _t('是否跟随系统暗色主题自动切换配色')
        );
        $form->addInput($darkMode);

        // ----- 插入方式 -----
        $autoInsert = new Typecho_Widget_Helper_Form_Element_Radio(
            'auto_insert',
            array('1' => _t('自动插入（推荐）'), '0' => _t('手动插入')),
            '1',
            _t('插入方式'),
            _t('选择“自动插入”则无需修改主题文件；选择“手动插入”则需在主题中添加 &lt;?php IPv6Status_Plugin::render(); ?&gt;')
        );
        $form->addInput($autoInsert);

       
        $forceShow = new Typecho_Widget_Helper_Form_Element_Radio(
            'force_show',
            array('0' => _t('关闭'), '1' => _t('开启')),
            '0',
            _t('强制显示标识'),
            _t('服务器 DNS 无法查询 AAAA 记录、但你确信站点已支持 IPv6 时，开启此项可跳过自动检测，始终输出标识')
        );
        $form->addInput($forceShow);

        
        $hostSafe = htmlspecialchars(self::currentDomain() ?: '(未获取到域名)', ENT_QUOTES, 'UTF-8');
        $detailSafe = htmlspecialchars(isset($st['detail']) ? $st['detail'] : '', ENT_QUOTES, 'UTF-8');
        $agoText  = isset($st['ts']) && $st['ts'] ? self::humanAgo((int) $st['ts']) : '从未';
        $failText = (int) $st['fail'];

        if ($support) {
            $notice = '<div id="ipv6-notice" style="padding: 12px 18px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; margin-bottom: 15px;">'
                . $successIcon . ' 检测到您的网站支持 IPv6，插件可正常使用。'
                . '<div style="margin-top:6px;color:#6c757d;font-size:12px;">'
                . '探测域名：<code>' . $hostSafe . '</code> · 上次探测：' . $agoText . '前 · 判定来源：' . htmlspecialchars($st['src'] ?: '-', ENT_QUOTES, 'UTF-8')
                . '</div></div>';
        } else {
            $notice = <<<HTML
<div id="ipv6-notice" style="padding: 12px 18px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 15px; border-radius: 4px;">
    <div id="notice-content">
        <span style="color: #856404;">{$warningIcon} 检测到您的网站 <span style="color: #d39e00; font-weight: bold;">暂不支持 IPv6</span></span><br>
        当前域名 <code>{$hostSafe}</code> 未解析到 IPv6 地址（AAAA 记录）。判定依据：{$detailSafe}<br>
        <span style="color:#6c757d;font-size:12px;">上次探测：{$agoText}前 · 连续探测失败 {$failText} 次（连续失败越多，自动复查间隔越长）</span>
    </div>
    <button id="recheck-btn" style="margin-top: 8px; padding: 4px 14px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">{$refreshIcon} 重新检测</button>
    <span style="margin-left: 10px; color: #6c757d; font-size: 12px;">（点击后自动检测，无需刷新页面）</span>
</div>
HTML;
        }
        echo $notice;

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
    .ipv6-spin { animation: ipv6-spin 1s linear infinite; }
    @keyframes ipv6-spin { 100% { transform: rotate(360deg); } }
    #recheck-btn .ipv6-icon { width: 16px; height: 16px; stroke-width: 2.5; }
</style>
CSS;

       
        echo <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    var recheckBtn = document.getElementById('recheck-btn');
    if (!recheckBtn) return;
    var noticeDiv = document.getElementById('ipv6-notice');
    var noticeContent = document.getElementById('notice-content');
    var icons = {$iconJson};

    function setNotice(html, type) {
        var target = noticeContent || noticeDiv;
        if (!target) return;
        target.innerHTML = html;
        var bg = '#fff3cd', border = '#ffc107';
        if (type === 'success') { bg = '#d4edda'; border = '#28a745'; }
        else if (type === 'error') { bg = '#f8d7da'; border = '#dc3545'; }
        if (noticeDiv) {
            noticeDiv.style.background = bg;
            noticeDiv.style.borderLeftColor = border;
        }
    }

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
        xhr.timeout = 20000;
        xhr.ontimeout = function() {
            setNotice(icons.timeout + ' 检测超时，请检查网络或刷新页面重试。', 'timeout');
            resetBtn();
        };
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.support) {
                        setNotice(icons.success + ' 检测到您的域名已解析到 IPv6 地址，插件可正常使用。（判定来源：' + (data.src || '-') + '）', 'success');
                    } else {
                        setNotice(icons.warning + ' 仍未检测到 IPv6 地址：' + (data.detail || '未知原因') + '。请确认域名 AAAA 记录已正确配置后重试。', 'error');
                    }
                } catch (e) {
                    setNotice(icons.error + ' 服务器返回数据格式错误，请刷新页面重试。', 'error');
                }
            } else {
                setNotice(icons.error + ' 请求失败（HTTP ' + xhr.status + '），请刷新页面重试。', 'error');
            }
            resetBtn();
        };
        xhr.onerror = function() {
            setNotice(icons.error + ' 网络错误，请检查网络连接或刷新页面。', 'error');
            resetBtn();
        };
        function resetBtn() {
            btn.disabled = false;
            btn.innerHTML = icons.refresh + ' 重新检测';
            btn.style.opacity = '1';
        }
        xhr.send();
    });
});
</script>
JS;
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    private static function humanAgo($ts)
    {
        $d = time() - (int) $ts;
        if ($d < 60)    return $d . ' 秒';
        if ($d < 3600)  return floor($d / 60) . ' 分钟';
        if ($d < 86400) return floor($d / 3600) . ' 小时';
        return floor($d / 86400) . ' 天';
    }

    
    public static function autoRender()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('IPv6Status');
        if ($options->auto_insert == '1') {
            self::render();
        }
    }

    
    public static function render()
    {
        $st      = self::loadState(); 
        $options = Typecho_Widget::widget('Widget_Options')->plugin('IPv6Status');
        $force   = isset($options->force_show) && $options->force_show == '1';

        if (self::isStale($st)) {
         
            self::scheduleRefresh();
        }

        if ($st['support'] !== true && !$force) {
            return;
        }

        $style    = $options->style ?: 'badge';
        $showText = isset($options->show_text) ? $options->show_text : '1';
        $darkMode = isset($options->dark_mode) ? $options->dark_mode : '1';

        $clientIP = self::getClientIP();
        $isIPv6   = filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isIPv4   = filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        if (!$isIPv4 && !$isIPv6) {
            $isIPv4 = true;
        }
        $statusClass   = $isIPv6 ? 'on' : 'off';
        $statusDisplay = $showText == '1' ? ($isIPv6 ? '您正在使用 IPv6 ✓' : '本站支持 IPv6') : '';

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
        $darkCss = $darkMode == '1' ? '' : '/* 暗色模式已关闭 */';
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
.ipv6-badge .hex-icon { flex-shrink: 0; width: 24px; height: 24px; display: block; }
.ipv6-badge .badge-text { display: flex; align-items: baseline; gap: 3px 6px; flex-wrap: wrap; }
.ipv6-badge .badge-text .label { font-weight: 700; color: #1a4d6e; letter-spacing: 0.2px; }
.ipv6-badge .badge-text .label .v6 { color: #2d7bb6; }
.ipv6-badge .badge-text .divider { color: rgba(44, 62, 80, 0.25); font-weight: 300; margin: 0 1px; }
.ipv6-badge .badge-text .status { font-weight: 500; color: #5a6c7d; }
.ipv6-badge .badge-text .status.active { color: #1a8a4a; font-weight: 600; }
.ipv6-badge .badge-text .status .check { display: inline-block; margin-left: 1px; font-weight: 700; }
.ipv6-badge .dot-indicator { flex-shrink: 0; width: 6px; height: 6px; border-radius: 50%; background: #b0c4d9; transition: background 0.3s ease; }
.ipv6-badge .dot-indicator.on { background: #22a65e; box-shadow: 0 0 8px rgba(34, 166, 94, 0.35); }
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
    .ipv6-badge { background: rgba(45, 123, 182, 0.12); border-color: rgba(45, 123, 182, 0.30); color: #dce3ea; }
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
        $darkCss = $darkMode == '1' ? '' : '/* 暗色模式已关闭 */';
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
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    background: linear-gradient(145deg, #4a90d9, #1e5f8a);
    border-radius: 50%; font-size: 16px; font-weight: 700; color: #fff;
    text-shadow: 0 1px 3px rgba(0,0,0,0.2);
    box-shadow: 0 2px 8px rgba(30, 95, 138, 0.30);
    flex-shrink: 0;
}
.ipv6-glass .glass-label { font-weight: 600; letter-spacing: 0.3px; color: #1f3a57; margin-right: 2px; }
.ipv6-glass .glass-label .v6 { color: #2d7bb6; font-weight: 700; }
.ipv6-glass .glass-divider { color: rgba(31, 58, 87, 0.20); font-weight: 300; margin: 0 2px; }
.ipv6-glass .glass-status { display: inline-flex; align-items: center; gap: 6px; color: #4a5b6b; font-weight: 450; }
.ipv6-glass .glass-status.active { color: #1a8a4a; font-weight: 500; }
.ipv6-glass .status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #b8c9d9; transition: background 0.3s ease, box-shadow 0.3s ease; }
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
    .ipv6-glass { background: rgba(30, 40, 55, 0.50); border-color: rgba(255, 255, 255, 0.10); box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08); color: #dce3ea; }
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
        $darkCss = $darkMode == '1' ? '' : '/* 暗色模式已关闭 */';
        return <<<HTML
<style>
.ipv6-signal {
    display: inline-flex; align-items: center; gap: 6px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px; font-weight: 500; color: #4a5b6b;
    cursor: default; user-select: none; line-height: 1;
}
.ipv6-signal .signal-icon { display: inline-block; width: 22px; height: 16px; flex-shrink: 0; }
.ipv6-signal .signal-icon svg { display: block; width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
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
        $darkCss = $darkMode == '1' ? '' : '/* 暗色模式已关闭 */';
        return <<<HTML
<style>
.ipv6-dot {
    display: inline-flex; align-items: center; gap: 6px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px; font-weight: 500; color: #6a7b8c;
    cursor: default; user-select: none; line-height: 1;
}
.ipv6-dot .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #b8c9d9; flex-shrink: 0; }
.ipv6-dot.on .dot { background: #22a65e; box-shadow: 0 0 0 2px rgba(34, 166, 94, 0.25); }
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
