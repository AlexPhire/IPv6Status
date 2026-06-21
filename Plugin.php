<?php
/**
 * IPv6访问状态标识
 *
 * @package IPv6Status
 * @author  AlexPhire
 * @version 1.0.0
 * @link    https://github.com/AlexPhire/Ipv6Status
 */

// 加载依赖模块
require_once __DIR__ . '/includes/Helper.php';
require_once __DIR__ . '/includes/Icons.php';
require_once __DIR__ . '/includes/Config.php';
require_once __DIR__ . '/includes/Render.php';

class Ipv6Status_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     */
    public static function activate()
    {
        Helper::activate();
        Typecho_Plugin::factory('Widget_Archive')->footer = array('Ipv6Status_Plugin', 'autoRender');
        return _t('IPv6 状态标识插件已启用。');
    }

    /**
     * 禁用插件（清理缓存）
     */
    public static function deactivate()
    {
        Helper::deactivate();
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        Config::render($form);
    }

   
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    public static function autoRender()
    {
        Render::autoRender();
    }

    public static function render()
    {
        Render::render();
    }
}