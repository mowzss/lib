<?php
declare(strict_types=1);


namespace happy\admin\libs\forms;

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
     * 将字段选项转换为关联数组（兼容旧调用）
     * 具体解析规则见 OptionResolver
     * @param mixed $inputString 输入的字符串
     * @param bool $original 是否保留原始值
     * @return array 返回包含键值对的关联数组
     */
    public static function strToArray(mixed $inputString, $original = false, mixed $value = null): array
    {
        $result = OptionResolver::resolveKeyValue($inputString, $value === null ? [] : ['value' => $value]);
        if ($original && is_string($inputString)) {
            $result['_original'] = trim($inputString);
        }
        return $result;
    }
}
