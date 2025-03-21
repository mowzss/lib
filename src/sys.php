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
