<?php
declare(strict_types=1);

namespace mowzs\lib\forms;

use mowzs\lib\helper\ExecutorHelper;
use think\Exception;

class FormatFieldOption
{
    /**
     * @param mixed $options 参数
     * @return void
     */
    public static function getOptions(mixed $options = [])
    {

    }

    /**
     * 将特定格式的字符串转换为数组，提取每行的第一个键值对。
     * @param mixed $inputString 输入的字符串
     * @return array 返回包含键值对的关联数组
     */
    public static function strToArray(mixed $inputString): array
    {
        try {
            // 检测并执行方法
            return ExecutorHelper::runIfValid($inputString);
        } catch (Exception $exception) {
            // 去除字符串两端的空白字符
            $inputString = trim($inputString);
            // 使用换行符分割字符串，得到每一行
            $lines = explode("\n", $inputString);
            // 初始化结果数组
            $result = [];
            foreach ($lines as $line) {
                // 去除每行两端的空白字符
                $line = trim($line);
                // 如果行为空，跳过
                if (empty($line)) {
                    continue;
                }
                // 使用竖线（|）分割行，获取第一个键值对
                $parts = explode('|', $line); // 限制分割次数为2，以确保只获取第一个键值对
                list($key, $value) = $parts;
                // 添加到结果数组
                $result[$key] = $value;
            }

            return $result;
        }

    }

}
