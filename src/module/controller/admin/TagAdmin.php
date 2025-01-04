<?php
declare (strict_types=1);

namespace mowzs\lib\module\controller\admin;

use app\common\controllers\BaseAdmin;
use app\common\traits\CrudTrait;
use mowzs\lib\helper\UserHelper;
use think\App;

/**
 * TAG管理
 */
abstract class TagAdmin extends BaseAdmin
{
    use CrudTrait;

    /**
     * 当前主模型
     * @var string
     */
    protected static string $modelClass;

    public function __construct(App $app)
    {
        parent::__construct($app);
        if (empty(static::$modelClass)) {
            throw new \InvalidArgumentException('The $modelClass must be set in the subclass.');
        }
        $this->model = new static::$modelClass();

        $this->setParams();
    }

    protected function setParams()
    {
        $this->tables = [
            'fields' => [
                [
                    'field' => 'id',
                    'title' => 'ID',
                    'width' => 80,
                    'sort' => true,
                ], [
                    'field' => 'image',
                    'title' => '封面图',
                    'templet' => 'image'
                ], [
                    'field' => 'title',
                    'title' => '名称',
                    'edit' => 'text'
                ], [
                    'field' => 'count',
                    'title' => '关联内容数',
                    'sort' => true,
                ], [
                    'field' => 'view',
                    'title' => '浏览',
                    'sort' => true,
                ], [
                    'field' => 'list',
                    'title' => '排序',
                    'edit' => "text",
                    'sort' => true,
                ], [
                    'field' => 'status',
                    'title' => '状态',
                    'templet' => 'switch'
                ], [
                    'field' => 'create_time',
                    'title' => '创建时间',
                ],

            ]
        ];
        $this->forms['fields'] = [
            [
                'type' => 'text',
                'name' => 'title',
                'label' => 'TAG名称',
                'required' => true
            ], [
                'type' => 'image',
                'name' => 'image',
                'label' => '封面图片'
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
                'label' => 'SEO描述',
            ]
        ];
        $this->search = [
            'id#=#id', 'title#=#title', 'status#=#status', 'create_time#between#create_time', 'update_time#between#update_time'
        ];
    }

    /**
     * 异步搜索接口
     * @auth true
     * @return void
     */
    public function getAjaxList(): void
    {
        $page = $this->request->get('page', 1);
        $keyword = $this->request->post('keyword', '');
        $add = $this->request->post('add', '');
        $model = $this->model->where(['status' => 1])->field('id,title');
        $pages = ['page' => $page, 'list_rows' => '20'];
        $where_like = [['title', 'like', "%{$keyword}%"]];
        if (!empty($keyword)) {
            $model = $model->where($where_like);
        }
        $list = $model->paginate($pages);
        if (!empty($add) && $list->isEmpty() && !empty($keyword)) {
            $this->model->create(['title' => $keyword, 'status' => 0, 'list' => time(), 'uid' => UserHelper::instance()->getUserId('0')]);
            $list = $this->model->field('id,title')->where($where_like)->paginate($pages);
        }
        $this->success($list);
    }
}
