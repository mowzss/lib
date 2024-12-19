<?php

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class ArrayHelper extends Helper
{
    /**
     * 数组合并
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public function customArrayMerge(array $array1, array $array2): array
    {
        $result = [];

        foreach ($array1 as $key => $value) {
            if (array_key_exists($key, $array2)) {
                if (is_array($value) && is_array($array2[$key])) {
                    $result[$key] = $this->customArrayMerge($value, $array2[$key]);
                } else {
                    // 这里可以决定如何处理非数组值的冲突，例如保留第一个、第二个或进行某种合并
                    $result[$key] = $array2[$key]; // 这里简单地选择了后面的值覆盖前面的值
                }
            } else {
                $result[$key] = $value;
            }
        }

        // 添加$array2中$array1中没有的键和值
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key, $result)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
