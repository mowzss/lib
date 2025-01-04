<?php
declare (strict_types=1);

namespace mowzs\lib\module\controller\admin;

use app\common\controllers\BaseAdmin;
use app\common\traits\CrudTrait;
use mowzs\lib\extend\DataExtend;
use think\App;
use think\Model;

/**
 * 分类管理
 */
abstract class ColumnAdmin extends BaseAdmin
{
    use CrudTrait;

    protected static string $modelClass;
    protected static string $moduleModelClass;
    protected Model $moduleModel;
    /**
     * 开启树形表格
     * @var bool
     */
    protected bool $is_tree = true;
    /**
     * 默认排序
     * @var array
     */
    protected array $default_order = [
        'list' => 'desc'
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
        if (empty(static::$modelClass)) {
            throw new \InvalidArgumentException('The $modelClass must be set in the subclass.');
        }
        $this->model = new static::$modelClass();
        if (empty(static::$moduleModelClass)) {
            throw new \InvalidArgumentException('The $modelClass must be set in the subclass.');
        }
        $this->moduleModel = new static::$moduleModelClass();
        $this->setParams();
    }

    /**
     * 处理列表数据
     * @param $data
     * @return void
     */
    protected function _index_list_filter(&$data): void
    {
        $data['data'] = DataExtend::getInstance()->arrToTree($data['data']);

    }

    protected function setParams(): void
    {
        $this->tables = [
            'fields' => [
                [
                    'field' => 'id',
                    'title' => 'ID',
                    'width' => 80,
                    'sort' => true,

                ], [
                    'field' => 'title',
                    'title' => '名称',
                    'edit' => "text",
                    'align' => 'left'
                ], [
                    'field' => 'seo_description',
                    'title' => '简介',
                    'edit' => "textarea"
                ], [
                    'field' => 'list',
                    'title' => '排序',
                    'edit' => "text"
                ], [
                    'field' => 'status',
                    'title' => '状态',
                    'templet' => 'switch'
                ], [
                    'field' => 'create_time',
                    'title' => '创建时间',
                ],
            ],
        ];
        $this->forms = [
            'fields' => [
                [
                    'type' => 'select',
                    'name' => 'pid',
                    'label' => '上级栏目',
                    'options' => $this->model->getColumnForm(),
                    'help' => '为空则是顶级栏目'
                ], [
                    'type' => 'text',
                    'name' => 'title',
                    'label' => '栏目名称',
                    'required' => true
                ], [
                    'type' => 'select',
                    'name' => 'mid',
                    'label' => '所属模型',
                    'required' => true,
                    'options' => $this->moduleModel->where('id', '>', 0)->column('title', 'id'),
                ], [
                    'type' => 'icon',
                    'name' => 'icon',
                    'label' => '栏目图标'
                ], [
                    'type' => 'image',
                    'name' => 'image',
                    'label' => '栏目图片'
                ], [
                    'type' => 'text',
                    'name' => 'seo_title',
                    'label' => 'SEO标题'
                ], [
                    'type' => 'text',
                    'name' => 'seo_keywords',
                    'label' => 'SEO关键词',
                ], [
                    'type' => 'textarea',
                    'name' => 'seo_description',
                    'label' => 'SEO介绍',
                ]
            ]
        ];
    }
}
