<?php
declare(strict_types=1);

namespace mowzs\lib\module\model;

use mowzs\lib\helper\DataHelper;
use mowzs\lib\Model;

abstract class ColumnBaseModel extends Model
{
    protected function getOptions(): array
    {
        return [
            'type' => [
                // 设置JSON字段的类型
                'view_file' => 'json'
            ],
            'jsonAssoc' => true,  // 设置JSON数据返回数组
        ];
    }

    /**
     * @param array $where
     * @return array
     */
    public function getColumnForm(array $where = ['status' => 1]): array
    {
        return ['0' => '[顶级分类]'] + DataHelper::instance()->transformArray(DataHelper::instance()->arrToTable($this->where($where)->column('title,pid,id', 'id')));
    }
}
