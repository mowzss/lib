<?php

namespace mowzs\lib\helper;

use mowzs\lib\Exception\LibsException;

/**
 * 日期处理助手类
 */
class DateHelper
{
    /**
     * 获取指定月份的所有日期
     *
     * @param int|string|null $year 年份，例如 2026。如果为 null，则使用当前年份。
     * @param int|string|null $month 月份，例如 4 (代表四月)。如果为 null，则使用当前月份。
     * @param string $format 输出日期的格式，默认为 'Y-m-d'。
     * @return array|null 包含该月每一天日期字符串的数组。
     *               如果输入的年份或月份无效，则返回空数组。
     * @throws LibsException
     */
    public static function getDaysOfMonth(int|string|null $year = null, int|string|null $month = null, string $format = 'Y-m-d'): ?array
    {
        try {
            // 处理默认值
            $year = $year ?: date('Y');
            $month = $month ?: date('m');

            // 验证输入参数的基本有效性
            if (!is_numeric($year) || !is_numeric($month)) {
                return []; // 参数必须是数字
            }

            // 尝试创建指定月份第一天的 DateTime 对象
            // 这样可以利用 DateTime 的内部校验机制
            $firstDayString = sprintf('%04d-%02d-01', (int)$year, (int)$month);
            $firstDay = new \DateTime($firstDayString);

            // 创建该月最后一天的 DateTime 对象
            $lastDay = clone $firstDay; // 克隆以避免修改 $firstDay
            $lastDay->modify('last day of this month');

            // 创建 DatePeriod 来迭代每一天
            $interval = new \DateInterval('P1D'); // 1天间隔
            // DatePeriod 的结束日期是排他的，所以需要将结束日期加一天
            $endDate = clone $lastDay;
            $endDate->add(new \DateInterval('P1D'));

            $dateRange = new \DatePeriod($firstDay, $interval, $endDate);

            // 遍历并格式化日期
            $datesArray = [];
            foreach ($dateRange as $date) {
                $datesArray[] = $date->format($format);
            }

            return $datesArray;

        } catch (\Exception $e) {
            throw new LibsException("Invalid year or month.");
        }
    }

    /**
     * 获取当前月份的所有日期
     *
     * @param string $format 输出日期的格式，默认为 'Y-m-d'。
     * @return array 包含当前月每一天日期字符串的数组。
     * @throws LibsException
     */
    public static function getCurrentMonthDays(string $format = 'Y-m-d'): array
    {
        return self::getDaysOfMonth(null, null, $format);
    }

    /**
     * 获取指定年月的天数
     *
     * @param int $year 年份
     * @param int $month 月份
     * @return int|null 返回天数，如果年月无效则返回 0
     * @throws LibsException
     */
    public static function getDaysInMonth(int $year, int $month): ?int
    {
        try {
            $firstDayString = sprintf('%04d-%02d-01', (int)$year, (int)$month);
            $firstDay = new \DateTime($firstDayString);
            $lastDay = clone $firstDay;
            $lastDay->modify('last day of this month');
            return (int)$lastDay->format('j');
        } catch (\Exception $e) {
            throw new LibsException("Invalid year or month.");
        }
    }
}
