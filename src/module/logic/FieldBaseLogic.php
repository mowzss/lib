<?php

namespace mowzs\lib\module\logic;

use mowzs\lib\BaseLogic;
use think\Exception;

class FieldBaseLogic extends BaseLogic
{
    protected ?string $modelName;
    protected string $table;

    /**
     * @return void
     * @throws Exception
     */
    protected function initialize(): void
    {
        $this->modelName = $this->getModule();
        $this->table = $this->modelName . '_field';
    }

    /**
     * @return \think\Model
     * @throws Exception
     */
    protected function model(): \think\Model
    {
        return $this->getModel($this->table);
    }

    /**
     * 获取栏目字段扩展
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getColumnFields()
    {
        $data = $this->model()->where('mid', '-1')->field('name,type,title,options,help,required')->select()->each(function ($rs) {
            $rs['label'] = $rs['title'];
            return $rs;
        });
        return $data->toArray();
    }
}
