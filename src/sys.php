<?php
// 应用公共文件
if (!function_exists('format_url')) {
    /**
     * 格式化网址
     * @param string $url 待格式化的URL
     * @param bool|string $is_array 控制返回类型: false 返回完整网址, true 返回分割数组, string (如 'host') 返回对应组件
     * @return false|mixed|string|array<string, mixed>|null
     */
    function format_url(string $url, bool|string $is_array = false): mixed
    {
        if (empty($url)) {
            return false;
        }
        
        $url = trim($url);
        if (!str_contains($url, 'http://') && !str_contains($url, 'https://')) {
            $url = 'http://' . $url;
        }
        $url = str_replace('////', '//', $url);
        
        // 检查 $is_array 的类型
        if ($is_array === true) { // 明确为 true 时返回数组
            $parsed_url = parse_url($url);
            if ($parsed_url === false) {
                return false; // 解析失败
            }
            return $parsed_url;
        }
        
        if (is_string($is_array)) { // 如果是字符串，则返回指定组件
            $parsed_url = parse_url($url);
            if ($parsed_url === false || !isset($parsed_url[$is_array])) {
                return null; // 组件不存在或解析失败
            }
            return $parsed_url[$is_array];
        }
        // $is_array 为 false 或其他假值，返回格式化后的 URL 字符串
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
