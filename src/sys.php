<?php
// 应用公共文件
if (!function_exists('format_url')) {
    /**
     * 格式化网址
     * @param string $url
     * @param bool|string $is_array false 返回完整网址 反之返回分割数组
     * @return null|array|false|int|mixed|string|void
     */

    function format_url(string $url, bool|string $is_array = false)
    {
        if (empty($url)) {
            return false;
        }
        $url = trim($url);
        if (!str_contains($url, 'http://') && !str_contains($url, 'https://')) {
            $url = 'http://' . $url;
        }
        $url = str_replace('////', '//', $url);
        if ($is_array) {
            $url = parse_url($url);
            return $url[$is_array];
        }
        return $url;
    }
}
if (!function_exists('isWithinDays')) {
    /**
     * 判断指定时间是否在指定天数之内（含当天）
     *
     * @param int|string $input 时间戳或日期字符串（如 "2025-05-25"）
     * @param int $days 要比较的天数（如 3 表示 3 天内）
     * @param string $baseTime 基准时间（可选，默认为当前时间）
     * @return bool
     */
    function isWithinDays(int|string $input, int $days = 3, string $baseTime = 'now'): bool
    {
        // 处理输入时间
        if (is_numeric($input)) {
            $inputTime = (int)$input;
        } else {
            $inputTime = strtotime($input);
        }

        // 处理基准时间（默认为现在）
        if ($baseTime === 'now' || empty($baseTime)) {
            $baseTime = time();
        } elseif (is_numeric($baseTime)) {
            $baseTime = (int)$baseTime;
        } else {
            $baseTime = strtotime($baseTime);
        }

        // 计算 N 天前的时间戳
        $nDaysAgo = strtotime("-{$days} days", $baseTime);

        // 判断 inputTime 是否在 [n天前, 当前时间] 这个区间内
        return $inputTime >= $nDaysAgo && $inputTime <= $baseTime;
    }
}
