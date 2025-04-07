<?php
declare (strict_types=1);

namespace mowzs\lib\module\controller\admin;

use app\common\controllers\BaseAdmin;
use app\common\traits\CrudTrait;
use mowzs\lib\helper\DataHelper;
use mowzs\lib\helper\ViewFileHelper;
use mowzs\lib\module\logic\FieldBaseLogic;
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
    protected string $title = "栏目管理";

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
        $data['data'] = DataHelper::instance()->arrToTree($data['data']);
        $models = $this->moduleModel->column('title', 'id');
        foreach ($data['data'] as &$item) {
            $item['mid_name'] = $models[$item['mid']];
        }
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
                    'field' => 'mid_name',
                    'title' => '所属模型',
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
            'right_button' => [
                [
                    'event' => '',
                    'type' => 'data-win-open',
                    'url' => hurl('column/index') . '?id=__id__',
                    'name' => '预览',
                    'class' => 'layui-btn-primary layui-border-green',//默认包含 layui-btn layui-btn-xs
                ], ['event' => 'edit'], ['event' => 'del']
            ]
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
                ], [
                    'type' => 'select',
                    'name' => 'view_file[pc]',
                    'label' => '栏目PC模板',
                    'options' => ViewFileHelper::instance()->getThemeView($this->request->layer(), 'column', false),
                ], [
                    'type' => 'select',
                    'name' => 'view_file[wap]',
                    'label' => '栏目wap模板',
                    'options' => ViewFileHelper::instance()->getThemeView($this->request->layer(), 'column', true)
                ]
            ]
        ];
        $this->forms['fields'] = array_merge($this->forms['fields'], FieldBaseLogic::instance()->getColumnFields());
    }


}
