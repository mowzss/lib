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
if (!function_exists('is_within_days')) {
    /**
     * 判断指定时间是否在指定天数之内（含当天）
     *
     * @param int|string $input 时间戳或日期字符串（如 "2025-05-25"）
     * @param int $days 要比较的天数（如 3 表示 3 天内）
     * @param string $baseTime 基准时间（可选，默认为当前时间）
     * @return bool
     */
    function is_within_days(int|string $input, int $days = 3, string $baseTime = 'now'): bool
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
if (!function_exists('highlight_keywords')) {
    /**
     * 为文本中出现的指定关键词添加 HTML 标签
     *
     * @param string $text 原始文本
     * @param array|string $keywords 需要标记的关键词数组
     * @param string $tag HTML 标签名，如 'span'、'mark'、'b'
     * @param array $attrs 附加属性，如 ['class' => 'highlight', 'style' => 'color:red']
     * @return string
     */
    function highlight_keywords(string $text, array|string $keywords, string $tag = 'mark', array $attrs = []): string
    
    {
        if ($text === '') {
            return $text;
        }
        
        // ========== 新增：关键词类型归一化 ==========
        if (is_string($keywords)) {
            // 如果包含逗号，按逗号分割；否则视为单个关键词
            $keywords = str_contains($keywords, ',')
                ? array_map('trim', explode(',', $keywords))
                : [$keywords];
        }
        // ============================================
        
        // 1. 去重 & 过滤空值
        $keywords = array_unique(array_filter($keywords, fn($k) => $k !== ''));
        
        if (empty($keywords)) {
            return $text;
        }
        
        // 2. 按长度降序排列，防止短词优先匹配破坏长词
        usort($keywords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
        
        // 3. 构建属性字符串
        $attrStr = '';
        foreach ($attrs as $key => $val) {
            $attrStr .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
        }
        
        // 4. 逐个替换（使用占位符防止重复标记）
        $placeholders = [];
        foreach ($keywords as $i => $keyword) {
            $escaped = preg_quote($keyword, '/');
            $placeholder = "\x00HL{$i}\x00";
            
            $text = preg_replace('/' . $escaped . '/u', $placeholder, $text);
            
            $placeholders[$placeholder] = "<{$tag}{$attrStr}>"
                . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8')
                . "</{$tag}>";
        }
        
        // 5. 将占位符还原为真正的 HTML 标签
        return strtr($text, $placeholders);
    }
}
