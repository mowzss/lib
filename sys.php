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
            $newBaseTime = time();
        } elseif (is_numeric($baseTime)) {
            $newBaseTime = (int)$baseTime;
        } else {
            $newBaseTime = strtotime($baseTime);
        }
        
        // 计算 N 天前的时间戳
        $nDaysAgo = strtotime("-{$days} days", $newBaseTime);
        
        // 判断 inputTime 是否在 [n天前, 当前时间] 这个区间内
        return $inputTime >= $nDaysAgo && $inputTime <= $newBaseTime;
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
if (!function_exists('vite_asset')) {
    
    /**
     * 获取 Vite 构建数据
     * @param string $entry
     * @param string $type 默认为 'file'，可选值有 'file'、'css'、'name'
     * @return mixed|string
     * @throws JsonException
     */
    function vite_asset(string $entry, string $type = 'file'): mixed
    {
        static $manifest = null;
        
        // 开发环境：直接指向 Vite Dev Server
        if (env('APP_DEV', false)) {
            return "http://localhost:5173/src/{$entry}";
        }
        
        if ($manifest === null) {
            $manifestPath = public_path('.vite/') . 'manifest.json';
            $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
        }
        if (isset($manifest[$entry])) {
            return $manifest[$entry];
        }
        
        throw new \RuntimeException("Vite asset not found in manifest: {$entry}");
    }
    
}
if (!function_exists('vite_js')) {
    
    /**
     * 输出 Vite JS 入口标签
     * 开发环境输出 dev server 地址，生产环境输出带哈希的 module script
     * @param string $entry
     * @return string
     */
    function vite_js(string $entry): string
    {
        if (env('APP_DEV', false)) {
            return '<script type="module" src="http://localhost:5173/' . htmlspecialchars($entry) . '"></script>';
        }
        
        try {
            $url = vite_asset($entry);
        } catch (JsonException $e) {
            return '';
        }
        return '<script type="module" src="/' . htmlspecialchars($url['file']) . '"></script>';
    }
    
}
if (!function_exists('vite_css')) {
    
    /**
     * 输出 Vite CSS 标签
     * @param string $jsEntry
     * @return string
     */
    function vite_css(string $jsEntry): string
    {
        if (env('APP_DEV', false)) {
            // 开发环境下 Vite Dev Server 会通过 JS 自动注入样式，无需手动 link
            // 如果确实需要独立 CSS 入口，可在此处补充逻辑
            return '';
        }
        try {
            $manifest = vite_asset($jsEntry, 'css');
        } catch (JsonException) {
            return ''; // 该入口没有关联 CSS，静默返回
        }
        if (empty($manifest['css'])) {
            return ''; // 该入口没有关联 CSS，静默返回
        }
        
        $tags = '';
        foreach ($manifest['css'] as $cssFile) {
            $tags .= '<link rel="stylesheet" href="/' . htmlspecialchars($cssFile) . '">' . "\n";
        }
        
        return trim($tags);
    }
}
if (!function_exists('pinyin_to_zhuyin')) {
    /**
     * 带声调拼音音节转注音符号
     *
     * overtrue/pinyin 不提供注音输出，按声母/韵母/声调转换；
     * 无声调或轻声沿用常见注音习惯，不加声调符号。
     *
     * @param string $syllable 如 yī / bù / shí
     * @return string 如 ㄧ / ㄅㄨˋ / ㄕˊ
     */
    function pinyin_to_zhuyin(string $syllable): string
    {
        static $toneChars = [
            'ā' => 'a1', 'á' => 'a2', 'ǎ' => 'a3', 'à' => 'a4',
            'ē' => 'e1', 'é' => 'e2', 'ě' => 'e3', 'è' => 'e4',
            'ī' => 'i1', 'í' => 'i2', 'ǐ' => 'i3', 'ì' => 'i4',
            'ō' => 'o1', 'ó' => 'o2', 'ǒ' => 'o3', 'ò' => 'o4',
            'ū' => 'u1', 'ú' => 'u2', 'ǔ' => 'u3', 'ù' => 'u4',
            'ǖ' => 'v1', 'ǘ' => 'v2', 'ǚ' => 'v3', 'ǜ' => 'v4', 'ü' => 'v5',
        ];
        static $initialMap = [
            'zh' => 'ㄓ', 'ch' => 'ㄔ', 'sh' => 'ㄕ',
            'b' => 'ㄅ', 'p' => 'ㄆ', 'm' => 'ㄇ', 'f' => 'ㄈ',
            'd' => 'ㄉ', 't' => 'ㄊ', 'n' => 'ㄋ', 'l' => 'ㄌ',
            'g' => 'ㄍ', 'k' => 'ㄎ', 'h' => 'ㄏ',
            'j' => 'ㄐ', 'q' => 'ㄑ', 'x' => 'ㄒ', 'r' => 'ㄖ',
            'z' => 'ㄗ', 'c' => 'ㄘ', 's' => 'ㄙ',
        ];
        static $finalMap = [
            'a' => 'ㄚ', 'o' => 'ㄛ', 'e' => 'ㄜ', 'ai' => 'ㄞ', 'ei' => 'ㄟ',
            'ao' => 'ㄠ', 'ou' => 'ㄡ', 'an' => 'ㄢ', 'en' => 'ㄣ',
            'ang' => 'ㄤ', 'eng' => 'ㄥ', 'er' => 'ㄦ',
            'i' => 'ㄧ', 'ia' => 'ㄧㄚ', 'ie' => 'ㄧㄝ', 'iao' => 'ㄧㄠ', 'iu' => 'ㄧㄡ',
            'ian' => 'ㄧㄢ', 'in' => 'ㄧㄣ', 'iang' => 'ㄧㄤ', 'ing' => 'ㄧㄥ',
            'u' => 'ㄨ', 'ua' => 'ㄨㄚ', 'uo' => 'ㄨㄛ', 'uai' => 'ㄨㄞ', 'ui' => 'ㄨㄟ',
            'uan' => 'ㄨㄢ', 'un' => 'ㄨㄣ', 'uang' => 'ㄨㄤ', 'ueng' => 'ㄨㄥ', 'ong' => 'ㄨㄥ',
            'v' => 'ㄩ', 've' => 'ㄩㄝ', 'van' => 'ㄩㄢ', 'vn' => 'ㄩㄣ', 'vong' => 'ㄩㄥ',
        ];
        static $toneMarks = [1 => '', 2 => 'ˊ', 3 => 'ˇ', 4 => 'ˋ', 5 => ''];
        // y/w 为零声母，映射需按完整音节处理，不能简单替换为 i/u
        static $zeroInitialMap = [
            'yi' => 'i', 'you' => 'iu', 'yan' => 'ian', 'yin' => 'in',
            'yang' => 'iang', 'ying' => 'ing', 'ya' => 'ia', 'ye' => 'ie', 'yao' => 'iao',
            'yu' => 'v', 'yue' => 've', 'yuan' => 'van', 'yun' => 'vn', 'yong' => 'vong',
            'wu' => 'u', 'wa' => 'ua', 'wo' => 'uo', 'wai' => 'uai', 'wei' => 'ui',
            'wan' => 'uan', 'wen' => 'un', 'wang' => 'uang', 'weng' => 'ueng',
        ];

        $base = '';
        $tone = 1;
        foreach (preg_split('//u', mb_strtolower($syllable, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (isset($toneChars[$char])) {
                $base .= $toneChars[$char][0];
                $tone = (int)$toneChars[$char][1];
                continue;
            }
            $base .= $char;
        }

        $initial = '';
        foreach (['zh', 'ch', 'sh', 'b', 'p', 'm', 'f', 'd', 't', 'n', 'l', 'g', 'k', 'h', 'j', 'q', 'x', 'r', 'z', 'c', 's'] as $item) {
            if (str_starts_with($base, $item)) {
                $initial = $item;
                $base = substr($base, strlen($item));
                break;
            }
        }

        // y/w 作为零声母：按完整音节查表，避免 yi→ii、wei→ue 之类的错误
        if ($initial === '' && $base !== '') {
            if (isset($zeroInitialMap[$base])) {
                $base = $zeroInitialMap[$base];
            } elseif (str_starts_with($base, 'y')) {
                $base = 'i' . substr($base, 1);
            } elseif (str_starts_with($base, 'w')) {
                $base = 'u' . substr($base, 1);
            }
        }
        // j/q/x 后的 u 实际为 ü
        if (in_array($initial, ['j', 'q', 'x'], true) && str_starts_with($base, 'u')) {
            $base = 'v' . substr($base, 1);
        }
        // zh/ch/sh/r/z/c/s 后的 i 为音节化声母，注音不写出 ㄧ
        if (in_array($initial, ['zh', 'ch', 'sh', 'r', 'z', 'c', 's'], true) && $base === 'i') {
            $base = '';
        }

        return ($initialMap[$initial] ?? '') . ($finalMap[$base] ?? '') . ($toneMarks[$tone] ?? '');
    }
}
