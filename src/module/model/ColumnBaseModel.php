<?php

namespace mowzs\lib\module\model;

use mowzs\lib\extend\DataExtend;
use mowzs\lib\Model;

abstract class ColumnBaseModel extends Model
{
    /**
     * @param array $where
     * @return array
     */
    public function getColumnForm(array $where = ['status' => 1]): array
    {
        return ['0' => '[顶级分类]'] + DataExtend::getInstance()->transformArray(DataExtend::getInstance()->arrToTable($this->where($where)->column('title,pid,id', 'id')));
    }
}
