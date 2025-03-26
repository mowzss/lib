<?php

declare (strict_types=1);

namespace mowzs\lib\module\controller\admin;

use app\common\controllers\BaseAdmin;
use app\common\traits\CrudTrait;
use app\common\util\ContentSaveFilterUtil;
use app\common\util\CrudUtil;
use mowzs\lib\Exception\RandomGenerationException;
use mowzs\lib\Forms;
use mowzs\lib\helper\CodeHelper;
use mowzs\lib\helper\EventHelper;
use mowzs\lib\module\service\ColumnBaseService;
use mowzs\lib\module\service\ContentBaseService;
use mowzs\lib\module\service\TagBaseService;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Exception;
use think\facade\View;
use think\Model;
use think\template\exception\TemplateNotFoundException;

/**
 * 内容列表
 */
abstract class ContentAdmin extends BaseAdmin
{
    use CrudTrait;

    /**
     * 当前主模型 默认为空 子类声明
     * @var string
     */
    protected static string $modelClass;
    /**
     * 字段信息模型类
     * @var string
     */
    protected static string $fieldModelClass;
    /**
     * 栏目模型类
     * @var string
     */
    protected static string $columnModelClass;
    /**
     * 模块设计模型 模型类名
     * @var string
     */
    protected static string $modelModelClass;
    /**
     * 字段信息模型
     * @var mixed
     */
    protected mixed $fieldModel;
    /**
     * 栏目模型
     * @var mixed
     */
    protected Model $columnModel;
    /**
     * 栏目模型
     * @var Model
     */
    protected Model $modelModel;
    /**
     * 当前模型id
     * @var int
     */
    protected int $mid = 0;
    /**
     * 当前内容模型服务
     * @var ContentBaseService
     */
    protected ContentBaseService $service;
    /**
     * tag服务
     * @var TagBaseService
     */
    protected TagBaseService $tagService;


    public function __construct(App $app)
    {
        parent::__construct($app);
        if (empty(static::$modelClass)) {
            throw new \InvalidArgumentException('The $modelClass must be set in the subclass.');
        }
        $this->model = new static::$modelClass();
        if (empty(static::$fieldModelClass)) {
            throw new \InvalidArgumentException('The $fieldModelClass must be set in the subclass.');
        }
        $this->fieldModel = new static::$fieldModelClass();
        if (empty(static::$columnModelClass)) {
            throw new \InvalidArgumentException('The $columnModelClass must be set in the subclass.');
        }
        $this->columnModel = new static::$columnModelClass();
        if (empty(static::$modelModelClass)) {
            throw new \InvalidArgumentException('The $modelModelClass must be set in the subclass.');
        }
        $this->modelModel = new static::$modelModelClass();

        $this->service = new ContentBaseService();
        $this->tagService = new TagBaseService();
        $this->mid = $this->request->param('mid/d', 0);
        $this->setParams();
    }

    /**
     * 内容列表
     * @auth true
     * @return string
     * @throws DbException
     * @throws Exception
     */
    public function index(): string
    {
        $params = $this->request->param();
        //  返回数据表格数据
        if ($this->isLayTable()) {
            // 构建查询
            $query = $this->buildWhereConditions($this->model, $params);
            // 处理关联查询
            if (isset($params['with'])) {
                foreach (explode(',', $params['with']) as $relation) {
                    $query->with(trim($relation));
                }
            }

            //设置排序
            $query = $this->setListOrder($query, $params);
            // 分页
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? ($this->limit ?? 20);
            $paginateResult = $query->paginate([
                'page' => $page,
                'list_rows' => $limit
            ]);

            // 转换结果为数组
            $data = $paginateResult->toArray();

            // 回调过滤器
            $this->callback('_list_filter', $data);
            $this->success($data);
        }
        if (!empty($this->search)) {
            $this->assign([
                'search_code' => Forms::instance(['display' => false, 'outputMode' => 'code'])
                    ->setFormHtml(['data-table-id' => get_lay_table_id()])
                    ->setSubmit('搜索')->render($this->getSearchFields(), 'form_search'),
            ]);
        }
        // 分配模板变量
        $this->assign([
            'right_button' => CrudUtil::getButtonHtml($this->tables['right_button'] ?? []),
            'top_button' => CrudUtil::getButtonHtml($this->tables['top_button'] ?? [], 'top'),
            'model_list' => $this->modelModel->where('id', '>', 0)->column('title', 'id'),
            'where' => $this->bulidWhere(),
        ]);

        //渲染页面
        try {
            return $this->fetch();
        } catch (TemplateNotFoundException $exception) {
            //模板不存在时 尝试读取公用模板
            return $this->fetch('common@/module/content_list');
        }
    }

    /**
     * 列表数据处理
     * @param $data
     * @return void
     */
    protected function _index_list_filter(&$data): void
    {
        $models = $this->modelModel->where('id', '>', 0)->column('title', 'id');
        $column = $this->columnModel->where('id', '>', 0)->column('title', 'id');
        foreach ($data['data'] as &$item) {
            $item['model_name'] = $models[$item['mid']] ?? '未知模型';
            $item['column_name'] = $column[$item['cid']] ?? "未知栏目";
        }

    }

    /**
     * 构建where查询条件
     * @param Model|Query $model
     * @param $requestData
     * @return Query|Model
     */
    protected function buildWhereConditions(Model|Query $model, $requestData): Model|Query
    {
        $mid = $this->request->param('mid');
        if (!empty($mid)) {
            $model = $model->suffix("_{$mid}");
        }
        // 使用类的 search 属性作为搜索配置
        foreach ($this->search as $config) {
            // 拆分配置字符串
            list($fields, $operator, $paramKey) = explode('#', $config);

            // 如果是多个字段使用 | 分隔符进行分割
            $fieldList = explode('|', $fields);

            // 检查请求数据中是否存在对应的参数
            if (isset($requestData[$paramKey])) {
                $value = $requestData[$paramKey];

                // 跳过空值
                if (empty($value)) {
                    continue;
                }

                // 对于 like 操作符，防止 SQL 注入攻击，处理用户输入
                if ($operator == 'like') {
                    $value = '%' . addcslashes($value, '_%') . '%';
                }

                // 构建条件
                if (count($fieldList) > 1) {
                    // 多个字段用 OR 连接
                    $orConditions = [];
                    foreach ($fieldList as $field) {
                        $orConditions[] = [$field, $operator, $value];
                    }
                    // 使用模型的 orWhere 方法来构建 or 查询
                    foreach ($orConditions as $condition) {
                        $model = $model->orWhere(...$condition);
                    }
                } else {
                    // 单个字段直接添加条件
                    $model = $model->where($fields, $operator, $value);
                }
            }
        }

        // 返回修改后的模型实例
        return $model;
    }


    /**
     * 列表首页数据
     * @return void
     */
    protected function setParams(): void
    {
        $this->tables = [
            'fields' => [
                [
                    'field' => 'id',
                    'title' => 'ID',
                    'width' => 120,
                    'sort' => true,
                ],
                [
                    'field' => 'title',
                    'title' => '标题'
                ], [
                    'field' => 'model_name',
                    'title' => '所属模型',
                ],
                [
                    'field' => 'column_name',
                    'title' => '所属栏目'
                ],
                [
                    'field' => 'view',
                    'title' => '浏览',
                    'sort' => true,
                ],
            ],
            'top_button' => [
                ['event' => 'del']
            ],
            'right_button' => [
                [
                    'event' => '',
                    'type' => 'data-win-open',
                    'url' => hurl('content/index', ['id' => '__id__']),
                    'name' => '预览',
                    'class' => 'layui-btn-primary layui-border-green',//默认包含 layui-btn layui-btn-xs
                ], [
                    'event' => '',
                    'type' => 'data-open',
                    'url' => urls('edit', ['id' => '__id__']),
                    'name' => '编辑',
                    'class' => '',//默认包含 layui-btn layui-btn-xs
                ],
                ['event' => 'del']

            ]
        ];

        $this->search = [
            'id#=#id', 'title#like#title', 'cid#=#cid', 'mid#=#mid',
        ];
        $this->getFormsField();//数据库获取字段
        //追加状态 时间等搜索字段
        $this->search = array_merge($this->search, ['status#=#status', 'create_time#between#create_time', 'update_time#between#update_time']);
        //状态 时间追加到最后位置
        $this->tables['fields'] = $this->mergeFields($this->tables['fields'], [
            [
                'field' => 'status',
                'title' => '状态',
                'templet' => 'switch'
            ], [
                'field' => 'create_time',
                'title' => '添加时间',
            ],
        ], 'field');
    }

    /**
     * 添加
     * @auth true
     * @param int $cid
     * @param int $mid
     * @return bool|string|void
     * @throws Exception
     */
    public function add(int $cid = 0, int $mid = 0)
    {
        if (empty($cid) && empty($mid) && $this->request->isGet()) {
            $model_all = $this->modelModel->where('id', '>', 0)->select();
            $this->assign('column', $model_all);
            return $this->fetch('common@module/post');
        }
        if (!empty($cid)) {
            $this->mid = $this->columnModel->where('id', $cid)->value('mid');
        }
        if (!empty($mid)) {
            $this->mid = $mid;
        }
        $this->getFormsField($this->mid);
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['mid'] = $this->mid;
            $data['list'] = $data['create_time'] = $data['update_time'] = time();
            if (false === $this->callback('_save_filter', $data)) {
                return false;
            }
//            try {
            $this->checkRequiredFields($data);
            if (empty($data['id'])) {
                $data['id'] = CodeHelper::timestampBasedId();
            }
            EventHelper::instance()->listen('ContentAddBefore', $data);
            if ($this->service->saveContent($data)) {
                // 结果回调处理
                $result = true;
                EventHelper::instance()->triggerNoReturn('ContentAddAfter', $data);
                if (false === $this->callback('_save_result', $result, $model, $data)) {
                    return $result;
                }
                $this->success('添加成功');
            } else {
                $this->error('添加失败');
            }
//            } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
//
//                $this->error('添加失败：' . $e->getMessage());
//            }
        }
        if (empty($this->forms['fields'])) {
            $this->error('未设置 forms 参数');
        }
        $forms = Forms::instance();
        if (!empty($this->forms['trigger'])) {
            $forms = $forms->setTriggers($this->forms['trigger']);
        }
        if (!empty($this->forms['pk'])) {
            $forms = $forms->setPk($this->forms['pk']);
        }
        $forms->setValue(['mid' => $this->mid]);
        $forms->render($this->forms['fields']);
    }

    /**
     * 新增保存前
     * @param $data
     * @return void
     * @throws DbException
     */
    protected function _save_filter(&$data): void
    {
        if ($this->request->isPost()) {
            // 处理数据
            $data = ContentSaveFilterUtil::instance()->setProcessingData($data);
            $data['list'] = !empty($data['list']) ?: time();
        }
    }

    /**
     * 获取指定模型的 字段信息
     * @param $mid
     * @return mixed
     */
    protected function getFieldData($mid): mixed
    {
        return $this->fieldModel->where('mid', $mid)->order('list', 'desc')->select()->toArray();
    }

    /**
     * 修改
     * @auth true
     * @param string $id
     * @return bool|void
     * @throws Exception
     */
    public function edit(string $id = '')
    {
        try {
            $data = $this->request->post();
            $id = $id ?: $data['id'];
            try {
                $record = $this->service->getInfo($id);
            } catch (DataNotFoundException|DbException $e) {
                $this->error('出错了:' . $e->getMessage());
            }
            if (empty($record)) {
                $this->error('记录不存在');
            }
            $this->mid = $record['mid'];
            $data['update_time'] = time();
            $this->getFormsField($this->mid);
            if (false === $this->callback('_save_filter', $data, $record)) {
                return false;
            }
            if ($this->request->isGet()) {
                if (empty($record['tag'])) {
                    $record['tag'] = $this->tagService->getTagListByAid($id);
                }
                if (empty($this->forms['fields'])) {
                    $this->error('未设置 forms 参数');
                }
                $forms = Forms::instance()->setValue($record);
                if (!empty($this->forms['trigger'])) {
                    $forms = $forms->setTriggers($this->forms['trigger']);
                }
                if (!empty($this->forms['pk'])) {
                    $forms = $forms->setPk($this->forms['pk']);
                }
                $forms->render($this->forms['fields']);
            }
            EventHelper::instance()->listen('ContentEditBefore', $data);
            $this->checkRequiredFields($data);
            $this->service->editContent($data);
            EventHelper::instance()->triggerNoReturn('ContentEditAfter', $data);
            // 结果回调处理
            $result = true;
            if (false === $this->callback('_save_result', $result, $record, $data)) {
                return $result;
            }
            $this->success('更新成功');
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            $this->error('记录不存在：' . $e->getMessage());
        }
    }

    /**
     * 无密码发布
     * @return bool|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws RandomGenerationException
     */
    public function publish()
    {

        if (empty(sys_config('no_password_publish'))) {
            $this->error('设置不正确');
        }
        $auth_token = $this->request->param('token');
        if (empty($auth_token)) {
            $auth_token = $this->request->header('publish_token');
        }
        if (empty($auth_token)) {
            $this->error('权限认证失败');
        }
        if (sys_config('no_password_publish') != $auth_token) {
            $this->error('无权限');
        }
        if ($this->request->isGet()) {
            $html = '';
            $column = $this->columnModel->where(['status' => 1])->column('title', 'id');
            foreach ($column as $key => $value) {
                $html .= '<span id="' . $key . '">' . $value . '</span>';
            }
            return View::display($html);
        } else {
            $data = $this->request->post();
            if (empty($data['cid'])) {
                $this->error('栏目不能为空');
            }
            if (empty($data['mid'])) {
                $data['mid'] = ColumnBaseService::instance()->getMidById($data['cid']);
            }
            $data['list'] = $data['create_time'] = $data['update_time'] = time();
            if (false === $this->callback('_save_filter', $data)) {
                return false;
            }
            try {
                $this->checkRequiredFields($data);
                if (empty($data['id'])) {
                    $data['id'] = CodeHelper::timestampBasedId();
                }
                EventHelper::instance()->listen('ContentAddBefore', $data);
                if ($this->service->saveContent($data)) {
                    // 结果回调处理
                    $result = true;
                    EventHelper::instance()->triggerNoReturn('ContentAddAfter', $data);
                    if (false === $this->callback('_save_result', $result, $model, $data)) {
                        return $result;
                    }
                    $this->success('添加成功');
                } else {
                    $this->error('添加失败');
                }
            } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
                $this->error('添加失败：' . $e->getMessage());
            }
        }
    }

    /**
     * 内容新增后&保存后
     * @param $ret
     * @param $model
     * @param $data
     * @return void
     */
    protected function _save_result(&$ret, &$model, &$data): void
    {
        if (!empty($data['tag'])) {
            if (empty($data['id'])) {
                $data['id'] = $model['id'];
            }
            $this->tagService->saveTagList($data);//保存tag记录
        }
        $this->success('保存成功');
    }

    /**
     * 获取指定模型的 添加表单
     * @param string|int $mid
     * @return void
     */
    protected function getFormsField(string|int $mid = ''): void
    {
        //默认字段 列表搜索使用
        $mid = $mid ?: $this->mid;
        $this->forms['fields'] = [[
            'name' => 'cid',
            'type' => 'select',
            'label' => '栏目',
            'options' => $this->columnModel->column('title', 'id'),
            'required' => true,
        ], [
            'name' => 'mid',
            'type' => 'hidden',
            'label' => '模型',
        ], [
            'name' => 'tag',
            'type' => 'xmselect',
            'label' => 'TAG标签',
            'options' => [
                'remoteSearch' => true,//搜索数据接口
                'searchUrl' => urls('tag/getAjaxList'),//搜索数据接口
                'autoAdd' => true,
            ]
        ]];
        if ($this->request->action() == 'index') {
            $this->forms['fields'] = $this->mergeFields($this->forms['fields'], [[
                'name' => 'mid',
                'type' => 'select',
                'label' => '所属模型',
                'options' => $this->modelModel->where('id', '>', 0)->column('title', 'id'),
            ]]);
        }
        // 如果指定了 mid，合并数据库查询到的字段
        if (!empty($mid)) {
            $field_data = $this->getFieldData($mid);
            $new_fields = [];
            $new_search = [];
            $new_tables = [];
            foreach ($field_data as $k => $v) {
                $new_fields[] = [
                    'name' => $v['name'],
                    'type' => $v['type'],
                    'label' => $v['title'],
                    'options' => $v['options'],
                    'required' => (bool)$v['required'],
                    'help' => $v['help'],
                ];
                // 提取局部变量
                $searchExtend = isset($v['extend']['search']) ? $v['extend']['search'] : [];
                $tableExtend = isset($v['extend']['tables']) ? $v['extend']['tables'] : [];
                // 重组模型设置的搜索字段
                if (isset($searchExtend['open']) && $searchExtend['open'] == '1') {
                    $linq = $searchExtend['linq'] ?? '=';
                    $new_search[] = "{$v['name']}#$linq#{$v['name']}";
                }
                // 重组列表显示字段数据
                if (isset($tableExtend['is_show']) && $tableExtend['is_show'] == "1") {
                    $fieldInfo = [
                        'field' => $v['name'],
                        'title' => $v['title'],
                    ];
                    if (!empty($tableExtend['templet'])) {
                        $fieldInfo['templet'] = $tableExtend['templet'];

                        if ($fieldInfo['templet'] == 'switch'
                            && isset($tableExtend['switch'])
                            && !empty($tableExtend['switch']['name'])
                        ) {
                            $fieldInfo['switch'] = ['name' => $tableExtend['switch']['name']];
                        }
                        if (in_array($tableExtend['edit'], ['text', 'textarea'], true)) {
                            $fieldInfo['edit'] = $tableExtend['edit'];
                        }
                    }
                    $new_tables[] = $fieldInfo;
                }
            }
            //追加模型设置信息至表单
            $this->forms['fields'] = $this->mergeFields($this->forms['fields'], $new_fields);
            //            追加模型数据 至搜索
            $this->search = array_merge($this->search, $new_search);
            //    追加模型数据 至列表显示
            $this->tables['fields'] = $this->mergeFields($this->tables['fields'], $new_tables, 'field');
            // 合并指定模型的 栏目的字段
            $manual_fields = [
                [
                    'name' => 'cid',
                    'type' => 'select',
                    'label' => '栏目',
                    'options' => $this->columnModel->where('mid', $mid)->column('title', 'id'),
                    'required' => true,
                ]
            ];
            // 再次调用 mergeFields 来合并手动指定的字段
            $this->forms['fields'] = $this->mergeFields($this->forms['fields'], $manual_fields);
        }
    }

    /**
     * 删除
     * @auth true
     * @return false|mixed|void
     */
    public function delete()
    {
        if ($this->request->isPost()) {
            $ids = $this->request->param('ids');

            if (is_null($ids)) {
                $this->error('id不能为空');
            }
            if (is_array($ids)) {
                // 批量删除
                $records = $this->model->whereIn('id', $ids)->select();

                if (!$records->isEmpty()) {
                    if (false === $this->callback('_delete_filter', $records, $ids)) {
                        return false;
                    }
                    $records->each(function ($record) {
                        return $this->model->del($record);
                    });
                    if (false === $this->callback('_delete_result', $result, $ids)) {
                        return $result;
                    }
                    $this->success('删除成功');
                } else {
                    $this->error('记录不存在');
                }
            } else {
                // 单个删除
                $record = $this->model->findOrEmpty($ids);
                if (!$record->isEmpty()) {
                    if (false === $this->callback('_delete_filter', $record, $ids)) {
                        return false;
                    }
                    $result = $this->model->del($record);
                    if (false === $this->callback('_delete_result', $result, $ids)) {
                        return $result;
                    }
                    $this->success('删除成功');
                } else {
                    $this->error('记录不存在');
                }
            }
        } else {
            $this->error('请求错误');
        }

    }

}
