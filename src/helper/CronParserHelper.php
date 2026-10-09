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
        
        $desc = [];
        
        // 1. 解析月份
        if ($month !== '*') {
            $desc[] = self::parseField($month, '月', self::MONTHS);
        }
        
        // 2. 解析日期和星期（两者有联动关系）
        $domDesc = ($dayOfMonth !== '*') ? self::parseDayOfMonth($dayOfMonth) : null;
        $dowDesc = ($dayOfWeek !== '*') ? self::parseDayOfWeek($dayOfWeek) : null;
        
        if ($domDesc && $dowDesc) {
            // 当日和周同时指定时，cron 是 OR 关系
            $desc[] = "{$domDesc}或{$dowDesc}";
        } elseif ($domDesc) {
            $desc[] = $domDesc;
        } elseif ($dowDesc) {
            $desc[] = $dowDesc;
        } else {
            // 都为 * 时才说"每天"
            if ($month === '*') {
                $desc[] = '每天';
            }
        }
        
        // 3. 解析时间
        $timeDesc = self::parseTime($hour, $minute);
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
     * 通用字段解析器，支持: *, *\/n, a-b, a-b/n, a,b,c, 具体值
     */
    private static function parseField(string $field, string $unit, array $map = []): string
    {
        // 带映射的值转换
        $val = static function ($v) use ($map) {
            $v = trim($v);
            return $map[$v] ?? $v;
        };
        
        // */n 步长
        if (preg_match('#^\*/(\d+)$#', $field, $m)) {
            return "每{$m[1]}{$unit}";
        }
        
        // a-b/n 范围步长
        if (preg_match('#^(\d+)-(\d+)/(\d+)$#', $field, $m)) {
            return "从{$val($m[1])}{$unit}到{$val($m[2])}{$unit}，每{$m[3]}{$unit}";
        }
        
        // a-b 范围
        if (preg_match('#^(\d+)-(\d+)$#', $field, $m)) {
            return "{$val($m[1])}{$unit}至{$val($m[2])}{$unit}";
        }
        
        // 逗号分隔列表
        if (str_contains($field, ',')) {
            $items = array_map(fn($v) => $val($v), explode(',', $field));
            return implode('、', $items) . $unit;
        }
        
        // 单个值
        return $val($field) . $unit;
    }
    
    /**
     * 解析日期字段，支持 L, W, # 等特殊字符
     */
    private static function parseDayOfMonth(string $field): string
    {
        if ($field === 'L') {
            return '每月最后一天';
        }
        if (preg_match('/^(\d+)W$/', $field, $m)) {
            return "每月{$m[1]}日最近的工作日";
        }
        return self::parseField($field, '日');
    }
    
    /**
     * 解析星期字段，支持 # 和 L
     */
    private static function parseDayOfWeek(string $field): string
    {
        // 第N个周X (如 5#3 = 第三个周五)
        if (preg_match('/^(\d+)#(\d+)$/', $field, $m)) {
            $dayName = self::WEEKDAYS[(int)$m[1]] ?? "周{$m[1]}";
            return "每月第{$m[2]}个{$dayName}";
        }
        // 最后一个周X (如 5L = 最后一个周五)
        if (preg_match('/^(\d+)L$/', $field, $m)) {
            $dayName = self::WEEKDAYS[(int)$m[1]] ?? "周{$m[1]}";
            return "每月最后一个{$dayName}";
        }
        return self::parseField($field, '', self::WEEKDAYS);
    }
    
    /**
     * 解析小时和分钟组合，生成自然语言时间描述
     */
    private static function parseTime(string $hour, string $minute): string
    {
        $hSimple = ($hour === '*' || ctype_digit($hour));
        $mSimple = ($minute === '*' || ctype_digit($minute));
        
        // 最简单情况：具体时分
        if ($hSimple && $mSimple) {
            if ($hour === '*' && $minute === '*') {
                return '每分钟';
            }
            if ($hour === '*') {
                return "每小时的第{$minute}分钟";
            }
            if ($minute === '*') {
                return "{$hour}点整开始，每分钟";
            }
            $h = str_pad($hour, 2, '0', STR_PAD_LEFT);
            $m = str_pad($minute, 2, '0', STR_PAD_LEFT);
            return "{$h}:{$m}";
        }
        
        // 复杂情况分别拼接
        $parts = [];
        if ($hour !== '*') {
            $parts[] = self::parseField($hour, '点');
        }
        if ($minute !== '*') {
            $parts[] = self::parseField($minute, '分');
        }
        
        if ($hour === '*' && $minute !== '*') {
            return "每小时" . self::parseField($minute, '分');
        }
        
        return implode('', $parts);
    }
}
