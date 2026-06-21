<?php
class Helper
{
   
    public static function activate()
    {
        self::detectAndCacheIpv6Support();
    }

    /**
     * 禁用时清理缓存
     */
    public static function deactivate()
    {
        $db = Typecho_Db::get();
        $db->query($db->delete('table.options')->where('name = ?', 'Ipv6Status_detected'));
        $db->query($db->delete('table.options')->where('name = ?', 'Ipv6Status_last_check'));
    }

    
    public static function detectAndCacheIpv6Support()
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

        // 更新 detected 值
        $row = $db->fetchRow($db->select('*')->from('table.options')->where('name = ?', 'Ipv6Status_detected'));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $support ? '1' : '0'))->where('name = ?', 'Ipv6Status_detected'));
        } else {
            $db->query($db->insert('table.options')->rows(array('name' => 'Ipv6Status_detected', 'value' => $support ? '1' : '0', 'user' => 0)));
        }

        // 更新 last_check 时间戳
        $row = $db->fetchRow($db->select('*')->from('table.options')->where('name = ?', 'Ipv6Status_last_check'));
        if ($row) {
            $db->query($db->update('table.options')->rows(array('value' => $time))->where('name = ?', 'Ipv6Status_last_check'));
        } else {
            $db->query($db->insert('table.options')->rows(array('name' => 'Ipv6Status_last_check', 'value' => $time, 'user' => 0)));
        }

        return $support;
    }

    
    public static function getCachedDetection()
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'Ipv6Status_detected'));
        $lastRow = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'Ipv6Status_last_check'));

        $lastCheck = $lastRow ? (int)$lastRow['value'] : 0;
        if (time() - $lastCheck > 21600) {
            return self::detectAndCacheIpv6Support();
        }

        if ($row) {
            return $row['value'] == '1';
        }
        return self::detectAndCacheIpv6Support();
    }

    /**
     * 获取客户端真实 IP
     */
    public static function getClientIP()
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
}
