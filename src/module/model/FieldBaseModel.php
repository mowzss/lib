<?php
declare (strict_types=1);

namespace mowzs\lib\module\model;

use mowzs\lib\Model;

abstract class FieldBaseModel extends Model
{
    protected function getOptions(): array
    {
        return [
            'type' => [
                // 设置JSON字段的类型
                'extend' => 'json'
            ],
            'jsonAssoc' => true,  // 设置JSON数据返回数组
        ];
    }
}
