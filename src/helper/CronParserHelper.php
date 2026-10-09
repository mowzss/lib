<?php

namespace happy\admin\libs\helper;

use happy\admin\libs\Exception\LibsException;

class CronParserHelper
{
    private const WEEKDAYS = [
        0 => '周日', 1 => '周一', 2 => '周二', 3 => '周三',
        4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日',
    ];
    
    private const MONTHS = [
        1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月', 5 => '5月', 6 => '6月',
        7 => '7月', 8 => '8月', 9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月',
    ];
    
    /**
     * 解析 Cron 表达式并返回自然语言描述
     * @throws LibsException
     */
    public static function parse(string $expression): string
    {
        $expression = trim($expression);
        
        // 处理特殊宏语法
        if (str_starts_with($expression, '@')) {
            return self::parseMacro($expression);
        }
        
        $parts = preg_split('/\s+/', $expression);
        if (count($parts) !== 5) {
            throw new LibsException("无效的Cron表达式 '{$expression}'，标准格式需要5个字段");
        }
        
        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;
        
        // 兼容部分系统使用 ? 代替 * 的写法
        if ($dayOfMonth === '?') $dayOfMonth = '*';
        if ($dayOfWeek === '?') $dayOfWeek = '*';
        
        $desc = [];
        
        // 1. 解析月份
        $hasMonth = ($month !== '*');
        if ($hasMonth) {
            $desc[] = self::parseField($month, '月', self::MONTHS);
        }
        
        // 2. 解析日期和星期
        $domDesc = ($dayOfMonth !== '*') ? self::parseDayOfMonth($dayOfMonth) : null;
        $dowDesc = ($dayOfWeek !== '*') ? self::parseDayOfWeek($dayOfWeek) : null;
        
        // 3. 解析时间
        $timeDesc = self::parseTime($hour, $minute);
        $isPureFrequency = str_starts_with($timeDesc, '每') || $timeDesc === '每分钟';
        
        // 4. 组装日期和星期描述
        if ($domDesc && $dowDesc) {
            $desc[] = "{$domDesc}或{$dowDesc}";
        } elseif ($domDesc) {
            $desc[] = $domDesc;
        } elseif ($dowDesc) {
            $desc[] = $dowDesc;
        } elseif (!$isPureFrequency) {
            // ✅ 修复：只要不是纯频率，且没有指定具体日期/星期，就补充"每天"
            $desc[] = '每天';
        }
        
        // 5. 加入时间描述 (✅ 修复：删除了重复添加)
        $desc[] = $timeDesc;
        
        return implode('，', $desc) . '执行';
    }
    
    /**
     * 解析 @ 宏语法
     * @throws LibsException
     */
    private static function parseMacro(string $macro): string
    {
        return match (strtolower($macro)) {
            '@yearly', '@annually' => '每年1月1日，00:00执行',
            '@monthly' => '每月1日，00:00执行',
            '@weekly' => '每周日，00:00执行',
            '@daily', '@midnight' => '每天，00:00执行',
            '@hourly' => '每小时整点执行',
            default => throw new LibsException("未知的Cron宏: {$macro}"),
        };
    }
    
    /**
     * 通用字段解析器
     */
    private static function parseField(string $field, string $unit, array $map = []): string
    {
        $val = static function ($v) use ($map) {
            $v = trim($v);
            return $map[$v] ?? $v;
        };
        
        if (preg_match('#^\*/(\d+)$#', $field, $m)) {
            return "每{$m[1]}{$unit}";
        }
        
        if (preg_match('#^(\d+)-(\d+)/(\d+)$#', $field, $m)) {
            return "从{$val($m[1])}{$unit}到{$val($m[2])}{$unit}，每{$m[3]}{$unit}";
        }
        
        if (preg_match('#^(\d+)-(\d+)$#', $field, $m)) {
            return "{$val($m[1])}{$unit}至{$val($m[2])}{$unit}";
        }
        
        if (str_contains($field, ',')) {
            $items = array_map(fn($v) => $val($v), explode(',', $field));
            return implode('、', $items) . $unit;
        }
        
        return $val($field) . $unit;
    }
    
    private static function parseDayOfMonth(string $field): string
    {
        if ($field === 'L') return '每月最后一天';
        if (preg_match('/^(\d+)W$/', $field, $m)) return "每月{$m[1]}日最近的工作日";
        return self::parseField($field, '日');
    }
    
    private static function parseDayOfWeek(string $field): string
    {
        if (preg_match('/^(\d+)#(\d+)$/', $field, $m)) {
            $dayName = self::WEEKDAYS[(int)$m[1]] ?? "周{$m[1]}";
            return "每月第{$m[2]}个{$dayName}";
        }
        if (preg_match('/^(\d+)L$/', $field, $m)) {
            $dayName = self::WEEKDAYS[(int)$m[1]] ?? "周{$m[1]}";
            return "每月最后一个{$dayName}";
        }
        // ✅ 修复：传入 '周' 作为单位，避免解析出 "一至五" 而是 "周一到周五"
        return self::parseField($field, '周', self::WEEKDAYS);
    }
    
    private static function parseTime(string $hour, string $minute): string
    {
        // 1. 纯频率：分钟步长 + 小时通配
        if ($hour === '*' && preg_match('#^\*/(\d+)$#', $minute, $m)) {
            return "每{$m[1]}分钟";
        }
        
        // 2. 纯频率：小时步长 + 分钟为0或*
        if (($minute === '*' || $minute === '0') && preg_match('#^\*/(\d+)$#', $hour, $m)) {
            return "每{$m[1]}小时";
        }
        
        // 3. 小时步长 + 具体分钟
        if (preg_match('#^\*/(\d+)$#', $hour, $hm) && ctype_digit($minute)) {
            return "每{$hm[1]}小时的第{$minute}分";
        }
        
        // 4. 具体小时 + 分钟步长
        if (ctype_digit($hour) && preg_match('#^\*/(\d+)$#', $minute, $mm)) {
            return "{$hour}点起每{$mm[1]}分钟";
        }
        
        $hSimple = ($hour === '*' || ctype_digit($hour));
        $mSimple = ($minute === '*' || ctype_digit($minute));
        
        // 5. 简单数值组合
        if ($hSimple && $mSimple) {
            if ($hour === '*' && $minute === '*') return '每分钟';
            if ($hour === '*') return "每小时的第{$minute}分钟";
            if ($minute === '*') return "{$hour}点整开始，每分钟";
            
            $h = str_pad($hour, 2, '0', STR_PAD_LEFT);
            $m = str_pad($minute, 2, '0', STR_PAD_LEFT);
            return "{$h}:{$m}";
        }
        
        // 6. 复杂表达式分别拼接
        $hDesc = ($hour !== '*') ? self::parseField($hour, '点') : '';
        $mDesc = ($minute !== '*') ? self::parseField($minute, '分') : '';
        
        if ($hour === '*' && $minute !== '*') {
            return "每小时{$mDesc}";
        }
        
        // ✅ 修复：复杂拼接时增加连接词，避免 "9点至17点每15分" 变成 "9点至17点，每15分"
        if ($hDesc && $mDesc) {
            return "{$hDesc}，{$mDesc}";
        }
        
        return $hDesc . $mDesc;
    }
}
