<?php
declare(strict_types=1);

namespace mowzs\lib\module\model;

use mowzs\lib\helper\DataHelper;
use mowzs\lib\Model;

abstract class ColumnBaseModel extends Model
{
    protected $json = ['view_file'];

    protected $jsonAssoc = true;

    /**
     * @param array $where
     * @return array
     */
    public function getColumnForm(array $where = ['status' => 1]): array
    {
        return ['0' => '[顶级分类]'] + DataHelper::instance()->transformArray(DataHelper::instance()->arrToTable($this->where($where)->column('title,pid,id', 'id')));
    }
}
