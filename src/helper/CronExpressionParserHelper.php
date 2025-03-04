<?php

namespace mowzs\lib\helper;

use InvalidArgumentException;

class CronExpressionParserHelper
{
    /**
     * @var array|string[]
     */
    private array $cronFields = ['分钟', '小时', '日', '月', '星期'];

    /**
     * 解析 Cron 表达式
     * @param string $expression
     * @return string
     */
    public function parse(string $expression): string
    {
        // 拆分 Cron 表达式
        $parts = explode(' ', $expression);
        if (count($parts) !== count($this->cronFields)) {
            throw new InvalidArgumentException('无效的 Cron 表达式');
        }

        // 转换为语义化描述
        $descriptions = [];
        foreach ($parts as $index => $value) {
            $field = $this->cronFields[$index];
            $descriptions[] = $this->getFieldDescription($field, $value);
        }

        // 组合描述
        return implode('，', $descriptions) . '执行任务。';
    }

    /**
     * 获取字段描述
     * @param string $field
     * @param string $value
     * @return string
     */
    private function getFieldDescription(string $field, string $value): string
    {
        switch ($field) {
            case '分钟':
                return $this->describeTimeField($value, '分钟');
            case '小时':
                return $this->describeTimeField($value, '小时');
            case '日':
                return $this->describeDateField($value, '日');
            case '月':
                return $this->describeDateField($value, '月');
            case '星期':
                return $this->describeWeekdayField($value);
            default:
                return '未知字段';
        }
    }

    /**
     * 获取时间字段描述
     * @param string $value
     * @param string $unit
     * @return string
     */
    private function describeTimeField(string $value, string $unit): string
    {
        if ($value === '*') {
            return "每{$unit}";
        } elseif (strpos($value, '/') !== false) {
            [$start, $step] = explode('/', $value);
            return "每隔{$step}{$unit}" . ($start === '*' ? '' : "从{$start}开始");
        } elseif (strpos($value, '-') !== false) {
            [$start, $end] = explode('-', $value);
            return "从{$start}到{$end}{$unit}";
        } elseif (strpos($value, ',') !== false) {
            return "在" . str_replace(',', '、', $value) . "{$unit}";
        } else {
            return "在$value{$unit}";
        }
    }

    /**
     * 获取日期字段描述
     * @param string $value
     * @param string $unit
     * @return string
     */
    private function describeDateField(string $value, string $unit): string
    {
        if ($value === '*') {
            return "每{$unit}";
        } elseif (strpos($value, '/') !== false) {
            [$start, $step] = explode('/', $value);
            return "每隔{$step}{$unit}" . ($start === '*' ? '' : "从{$start}开始");
        } elseif (strpos($value, '-') !== false) {
            [$start, $end] = explode('-', $value);
            return "从{$start}到{$end}{$unit}";
        } elseif (strpos($value, ',') !== false) {
            return "在" . str_replace(',', '、', $value) . "{$unit}";
        } else {
            return "在{$value}{$unit}";
        }
    }

    /**
     * 获取星期字段描述
     * @param string $value
     * @return string
     */
    private function describeWeekdayField(string $value): string
    {
        if ($value === '?') {
            return '不指定具体星期';
        } elseif ($value === '*') {
            return '每天';
        } elseif (strpos($value, '#') !== false) {
            [$day, $occurrence] = explode('#', $value);
            $daysOfWeek = ['SUN' => '周日', 'MON' => '周一', 'TUE' => '周二', 'WED' => '周三', 'THU' => '周四', 'FRI' => '周五', 'SAT' => '周六'];
            $dayName = $daysOfWeek[intval($day)] ?? '未知';
            return "每月第{$occurrence}个{$dayName}";
        } elseif (strpos($value, 'L') !== false) {
            $day = substr($value, 0, -1);
            $daysOfWeek = ['SUN' => '周日', 'MON' => '周一', 'TUE' => '周二', 'WED' => '周三', 'THU' => '周四', 'FRI' => '周五', 'SAT' => '周六'];
            $dayName = $daysOfWeek[intval($day)] ?? '未知';
            return "每月最后一个$dayName";
        } elseif (strpos($value, ',') !== false) {
            return "在" . str_replace(',', '、', $value) . "执行";
        } else {
            $daysOfWeek = ['SUN' => '周日', 'MON' => '周一', 'TUE' => '周二', 'WED' => '周三', 'THU' => '周四', 'FRI' => '周五', 'SAT' => '周六'];
            $dayName = $daysOfWeek[intval($value)] ?? '未知';
            return "在{$dayName}";
        }
    }
}
