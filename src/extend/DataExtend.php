<?php

namespace Mowzs\Lib\extend;

use Mowzs\Lib\Extend;

class DataExtend extends Extend
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
                $v[$field3] = $this->arrToTree($data, $v[$field1]);
                $v['isParent'] = false;
                if (!empty($v[$field3])) {
                    $v['isParent'] = true;
                }
                $arr[] = $v;
            }
        }
        return $arr;
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
