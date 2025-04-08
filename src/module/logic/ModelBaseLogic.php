<?php

namespace mowzs\lib\module\logic;

use mowzs\lib\BaseLogic;
use think\Exception;

/**
 * 模块下模型服务基类
 */
class ModelBaseLogic extends BaseLogic
{
    /**
     * 调用模块名称
     * @var string|null
     */
    protected ?string $modelName;
    /**
     * 数据表
     * @var string
     */
    protected string $table;
    /**
     * 模型
     * @var \think\Model
     */
    protected \think\Model $model;

    /**
     * @return void
     * @throws Exception
     */
    protected function initialize(): void
    {
        $this->modelName = $this->getModule();
        $this->table = $this->modelName . '_model';
        $this->model = $this->getModel($this->table);
    }

    /**
     * 获取模块下所有模型id
     * @return array
     */
    public function getContentModelId(): array
    {
        return $this->model->where('id', '>', '0')->column('id');
    }
}
