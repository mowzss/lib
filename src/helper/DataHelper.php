<?php
declare(strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class DataHelper extends Helper
{
    /**
     * 数组转树形结构
     * @param array $data 数组
     * @param int $pid 父id
     * @param string $field1 主键
     * @param string $field2 父id键
     * @param string $field3 子节点名称
     * @return array
     */
    public function arrToTree(array $data, int $pid = 0, string $field1 = 'id', string $field2 = 'pid', string $field3 = 'children'): array
    {
        $arr = [];
        foreach ($data as $k => $v) {
            if ($v[$field2] == $pid) {
                $v[$field3] = $this->arrToTree($data, $v[$field1], $field1, $field2, $field3);
                $v['isParent'] = false;
                if (!empty($v[$field3])) {
                    $v['isParent'] = true;
                } else {
                    unset($v[$field3]);
                }
                $arr[] = $v;
            }
        }
        return $arr;
    }

    /**
     * @param array $array
     * @param string $childrenKey
     * @return mixed
     */
    public function removeEmptyChildren(array $array, string $childrenKey = 'children'): mixed
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (isset($value[$childrenKey])) {
                    $value[$childrenKey] = $this->removeEmptyChildren($value[$childrenKey]);
                    if (empty($value[$childrenKey])) {
                        unset($array[$key]);
                    } else {
                        $array[$key] = $value;
                    }
                }
            }
        }
        return $array;
    }


    /**
     * 二维数组转数据树表
     * @param array $items
     * @param string $idKey
     * @param string $parentIdKey
     * @param string $titleKey
     * @param int $parentId
     * @param int $level
     * @return array
     */
    public function arrToTable(array $items, string $idKey = 'id', string $parentIdKey = 'pid', string $titleKey = 'title', int $parentId = 0, int $level = 0): array
    {
        $result = [];
        foreach ($items as $item) {
            if ($item[$parentIdKey] == $parentId) {
                // 生成新的格式化的标题
                $prefix = ($parentId == 0 ? '' : str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level) . '└');
                $formattedTitle = $prefix . $item[$titleKey];
                $result[] = [
                    $idKey => $item[$idKey],
                    $titleKey => $formattedTitle
                ];

                // 如果当前元素有子元素，则递归处理子元素
                $children = $this->arrToTable($items, $idKey, $parentIdKey, $titleKey, $item[$idKey], $level + 1);
                if (!empty($children)) {
                    $result = array_merge($result, $children);
                }
            }
        }
        return $result;
    }

    /**
     * 三维数组提取key value
     * @param array $items
     * @param string $keyField
     * @param string $valueField
     * @return array
     */
    public function transformArray(array $items, string $keyField = 'id', string $valueField = 'title'): array
    {
        $result = [];
        foreach ($items as $item) {
            if (isset($item[$keyField]) && isset($item[$valueField])) {
                $result[$item[$keyField]] = $item[$valueField];
            }
        }
        return $result;
    }
}
