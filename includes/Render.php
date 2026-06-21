<?php

class Render
{
    
    public static function autoRender()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('Ipv6Status');
        if ($options->auto_insert == '1') {
            self::render();
        }
    }

    
    public static function render()
    {
        $siteSupport = Helper::getCachedDetection();
        if (!$siteSupport) {
            return;
        }

        $options = Typecho_Widget::widget('Widget_Options')->plugin('Ipv6Status');
        $style = $options->style ?: 'badge';
        $showText = isset($options->show_text) ? $options->show_text : '1';
        $darkMode = isset($options->dark_mode) ? $options->dark_mode : '1';

        $clientIP = Helper::getClientIP();
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
<div style="text-align: center; margin-top: 8px;">
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
<div style="text-align: center; margin-top: 8px;">
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
<div style="text-align: center; margin-top: 6px;">
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
<div style="text-align: center; margin-top: 6px;">
    <span class="ipv6-dot {$statusClass}">
        <span class="dot"></span>
        <span class="ipv6-text">IPv<span class="num6">6</span></span>
    </span>
</div>
HTML;
    }
}