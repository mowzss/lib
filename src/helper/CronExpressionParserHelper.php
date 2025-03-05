<?php

namespace mowzs\lib\helper;

use InvalidArgumentException;

class CronExpressionParserHelper
{
    private const MONTH_NAMES = [
        1 => '一月', 2 => '二月', 3 => '三月', 4 => '四月',
        5 => '五月', 6 => '六月', 7 => '七月', 8 => '八月',
        9 => '九月', 10 => '十月', 11 => '十一月', 12 => '十二月'
    ];

    private const WEEKDAY_NAMES = [
        0 => '周日', 1 => '周一', 2 => '周二', 3 => '周三',
        4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日'
    ];

    public function parse(string $expression): string
    {
        $fields = explode(' ', trim($expression));
        if (count($fields) !== 5) {
            throw new InvalidArgumentException('无效的cron表达式');
        }

        [$minute, $hour, $day, $month, $weekday] = $fields;

        return implode(' ', array_filter([
            $this->parseMinute($minute),
            $this->parseHour($hour),
            $this->parseDay($day),
            $this->parseMonth($month),
            $this->parseWeekday($weekday),
            '运行'
        ]));
    }

    private function parseMinute(string $field): string
    {
        if ($field === '*') {
            return '每分钟';
        }

        if ($field === '0') {
            return '每小时整点';
        }

        return $this->parseField($field, 0, 59, '分钟', function ($value) {
            return sprintf('%02d', $value);
        });
    }

    private function parseHour(string $field): string
    {
        $result = $this->parseField($field, 0, 23, '小时', function ($value) {
            return sprintf('%02d', $value);
        });

        if ($field === '*' && strpos($result, '每') === 0) {
            return '每天';
        }

        return $result;
    }

    private function parseDay(string $field): string
    {
        return $this->parseField($field, 1, 31, '日');
    }

    private function parseMonth(string $field): string
    {
        return $this->parseField($field, 1, 12, '月', function ($value) {
            return self::MONTH_NAMES[$value] ?? $value;
        });
    }

    private function parseWeekday(string $field): string
    {
        $parsed = $this->parseField($field, 0, 7, '周', function ($value) {
            return self::WEEKDAY_NAMES[$value] ?? $value;
        });

        // 处理周日可能的两种表示方式（0和7）
        if (strpos($field, '0') !== false || strpos($field, '7') !== false) {
            $parsed = str_replace(['0', '7'], '周日', $parsed);
        }

        return $parsed;
    }

    private function parseField(
        string    $field,
        int       $min,
        int       $max,
        string    $unit,
        ?callable $valueFormatter = null
    ): string
    {
        $valueFormatter ??= fn($v) => $v;

        // 处理特殊字符
        if ($field === '*') {
            return "每{$unit}";
        }

        // 处理步长值（*/n 或 m-n/n）
        if (preg_match('/^(\*|\d+-\d+)\/(\d+)$/', $field, $matches)) {
            $range = $matches[1] === '*' ? "{$min}-{$max}" : $matches[1];
            $step = (int)$matches[2];
            return $this->parseStep($range, $step, $unit, $valueFormatter);
        }

        // 处理逗号分隔的列表
        if (strpos($field, ',') !== false) {
            $values = array_map('trim', explode(',', $field));
            $parsedValues = [];
            foreach ($values as $value) {
                $parsedValues[] = $this->parseSingleValue($value, $min, $max, $valueFormatter);
            }
            return "{$unit}的" . implode('、', $parsedValues);
        }

        // 处理单个值
        return "每{$unit}的" . $this->parseSingleValue($field, $min, $max, $valueFormatter);
    }

    private function parseStep(string $range, int $step, string $unit, callable $formatter): string
    {
        if ($range === '0-23' && $unit === '小时') {
            return "每隔{$step}小时";
        }

        if ($range === '*') {
            return "每隔{$step}{$unit}";
        }

        [$start, $end] = explode('-', $range);
        return "从{$formatter($start)}到{$formatter($end)}每隔{$step}{$unit}";
    }

    private function parseSingleValue(string $value, int $min, int $max, callable $formatter): string
    {
        // 处理范围值
        if (strpos($value, '-') !== false) {
            [$start, $end] = explode('-', $value);
            $this->validateRange($start, $end, $min, $max);
            return "{$formatter($start)}到{$formatter($end)}";
        }

        // 验证数值范围
        $numericValue = (int)$value;
        if ($numericValue < $min || $numericValue > $max) {
            throw new InvalidArgumentException("无效的数值范围: {$value}");
        }

        return $formatter($numericValue);
    }

    private function validateRange($start, $end, $min, $max): void
    {
        $start = (int)$start;
        $end = (int)$end;

        if ($start < $min || $end > $max || $start > $end) {
            throw new InvalidArgumentException("无效的数值范围: {$start}-{$end}");
        }
    }
}
