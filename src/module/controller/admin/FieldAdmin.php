<?php
declare (strict_types=1);

namespace mowzs\lib\module\controller\admin;

use app\common\controllers\BaseAdmin;
use app\common\traits\CrudTrait;
use app\common\util\TableCreatorUtil;
use think\App;
use think\Exception;
use think\Model;

/**
 * 字段管理
 */
abstract class FieldAdmin extends BaseAdmin
{
    use CrudTrait;

    /**
     * 当前主模型 默认为空 子类声明
     * @var string
     */
    protected static string $modelClass;
    /**
     * 模块模型设计 模型类
     * @var string
     */
    protected static string $moduleModelClass;
    protected static string $contentModelClass;
    /**
     * 栏目模型类
     * @var string
     */
    protected static string $columnModelClass;
    /*
     * 保留字段
     */
    protected array $reserve_field = ['id', 'mid', 'cid', 'content', 'status', 'list', 'create_time', 'update_time'];
    /**
     * 栏目模型
     * @var mixed
     */
    protected mixed $columnModel;
    /**
     * 实例化 模块 模型设计
     * @var mixed
     */
    protected mixed $moduleModel;
    /**
     * @var Model
     */
    protected mixed $contentModel;
    protected array $default_order = ['list' => 'desc'];

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
        if (empty(static::$contentModelClass)) {
            throw new \InvalidArgumentException('The $contentModelClass must be set in the subclass.');
        }

        $this->contentModel = new static::$contentModelClass();
        if (empty(static::$columnModelClass)) {
            throw new \InvalidArgumentException('The $columnModelClass must be set in the subclass.');
        }
        $this->columnModel = new static::$columnModelClass();

        $this->setParams();

    }


    /**
     * @return void
     */
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
                    'field' => 'model_name',
                    'title' => '所属模型',
                ], [
                    'field' => 'name',
                    'title' => '字段',
                ], [
                    'field' => 'title',
                    'title' => '名称',
                ], [
                    'field' => 'type_name',
                    'title' => '表单类型',
                ], [
                    'field' => 'list',
                    'title' => '排序',
                    'edit' => "text",
                    'sort' => true,

                ], [
                    'field' => 'status',
                    'title' => '状态',
                    'templet' => 'switch'
                ]
            ],
            //表格行按钮
            'right_button' => [
                ['event' => 'edit'],
                ['event' => 'del'],
            ],
        ];
        $this->search = [
            'id#=#id',
            'name#like#name',
            'title#like#title',
            'mid#=#mid',
            'type#=#type',
            'status#=#status',
            'create_time#between#create_time',
            'update_time#between#update_time'
        ];
        $this->forms = [
            'fields' => [
                [
                    'type' => 'select',
                    'name' => 'mid',
                    'label' => '选择模型',
                    'options' => $this->moduleModel->column('title', 'id'),
                    'required' => true
                ], [
                    'type' => 'text',
                    'name' => 'name',
                    'label' => '字段名称',
                    'required' => true,
                    'help' => '一般建议英文字符 和 _ 下划线, 数字不建议开头',
                ], [
                    'type' => 'text',
                    'name' => 'title',
                    'label' => '字段说明',
                    'help' => '字段中文说明',
                    'required' => true
                ], [
                    'type' => 'select',
                    'name' => 'type',
                    'label' => '表单类型',
                    'options' => $this->app->config->get('form'),
                    'required' => true
                ], [
                    'type' => 'textarea',
                    'name' => 'options',
                    'label' => '表单参数',
                ], [
                    'type' => 'radio',
                    'name' => 'required',
                    'label' => '是否必填',
                    'options' => [
                        0 => '不限',
                        1 => '必填'
                    ],
                    'value' => 0,
                ],
                [
                    'type' => 'select',
                    'name' => 'extend[field][type]',
                    'label' => '字段类型',
                    'options' => [
                        'INT' => '[INT] 4字节整数，常用整数存储',
                        'BIGINT' => '[BIGINT] 8字节整数，大数值存储',
                        'VARCHAR' => '[VARCHAR] 变长字符串，灵活长度存储',
                        'TEXT' => '[TEXT] 大量文本，较大篇幅存储',
                        'LONGTEXT' => '[LONGTEXT] 超大文本，海量内容存储',
                        'TINYINT' => '[TINYINT] 1字节整数，小范围存储',
                        'SMALLINT' => '[SMALLINT] 2字节整数，中等范围存储',
                        'MEDIUMINT' => '[MEDIUMINT] 3字节整数，较大范围存储',
                        'FLOAT' => '[FLOAT] 单精度浮点数，近似值存储',
                        'DOUBLE' => '[DOUBLE] 双精度浮点数，高精度存储',
                        'DECIMAL' => '[DECIMAL] 定点数，精确数值存储',
                        'MEDIUMTEXT' => '[MEDIUMTEXT] 长文本，更长篇幅存储',
                        'BINARY' => '[BINARY] 定长二进制字节串存储',
                        'VARBINARY' => '[VARBINARY] 变长二进制字节串存储',
                        'BLOB' => '[BLOB] 二进制大对象，存文件等',
                        'TINYBLOB' => '[TINYBLOB] 小二进制大对象存储',
                        'MEDIUMBLOB' => '[MEDIUMBLOB] 中二进制大对象存储',
                        'LONGBLOB' => '[LONGBLOB] 大二进制大对象存储',
                    ]
                ],
                [
                    'type' => 'text',
                    'name' => 'extend[field][length]',
                    'label' => '字段长度'
                ],
                [
                    'type' => 'radio',
                    'name' => 'extend[field][unsigned]',
                    'label' => '允许负数',
                    'options' => [
                        0 => '允许',
                        1 => '不允许'
                    ], 'value' => 0,
                ],
                [
                    'type' => 'radio',
                    'name' => 'extend[field][null]',
                    'label' => '允许NULL',
                    'options' => [
                        0 => '允许',
                        1 => '不允许'
                    ], 'value' => 0,
                ],
                [
                    'type' => 'text',
                    'name' => 'extend[field][default]',
                    'label' => '默认值'
                ], [
                    'type' => 'radio',
                    'name' => 'extend[index][is_open]',
                    'label' => '索引', 'value' => 0,
                    'help' => '',
                    'options' => [
                        0 => '不添加',
                        1 => '添加'
                    ]
                ], [
                    'type' => 'radio',
                    'name' => 'extend[search][is_open]',
                    'label' => '搜索',
                    'options' => [
                        0 => '不启用',
                        1 => '启用'
                    ], 'value' => 0,
                ],
                [
                    'type' => 'select',
                    'name' => 'extend[search][linq]',
                    'label' => '搜索表达式',
                    'options' => [
                        '=' => '= 等于',
                        'like' => 'LIKE 模糊查询',
                        'between' => 'between 时间区间',
                    ],
                    'help' => '仅支持常用表达式，更多用法建议使用原生用法'

                ],
                [
                    'type' => 'radio',
                    'name' => 'extend[tables][is_show]',
                    'label' => '列表显示',
                    'options' => [
                        0 => '不显示',
                        1 => '显示'
                    ], 'value' => 0,
                ], [
                    'type' => 'text',
                    'name' => 'extend[tables][templet]',
                    'label' => '使用模版',
                    'help' => '内置模版 switch 开关模板 可自定义 <br> 自定义模版 #xxxxtpl 需自行在前端设置模板,模板用法参考 layui 数据表格 https://layui.dev/docs/2/table/#options.cols'

                ], [
                    'type' => 'text',
                    'name' => 'extend[tables][switch][name]',
                    'label' => 'switch显示文字',
                    'help' => '内容格式: 显示|隐藏 或 已审|待审 等等'

                ], [
                    'type' => 'radio',
                    'name' => 'extend[tables][edit]',
                    'label' => '列表编辑',
                    'options' => [
                        '0' => '不允许编辑',
                        'text' => '单行编辑',
                        'textarea' => '多行编辑',
                    ],
                    'value' => '0',
                ], [
                    'type' => 'radio',
                    'name' => 'extend[add][is_show]',
                    'label' => '投稿显示', 'value' => 0,
                    'options' => [
                        0 => '不显示',
                        1 => '显示'
                    ]
                ],
                [
                    'type' => 'text',
                    'name' => 'list',
                    'label' => '排序值',
                    'value' => '100'
                ],

            ],
            //触发联动操作
            'trigger' => [
                [
                    'name' => 'type',
                    'values' => [
                        ['value' => 'radio', 'field' => ['options']],
                        ['value' => 'checkbox', 'field' => ['options']],
                        ['value' => 'select', 'field' => ['options']]
                    ]
                ],
                [
                    'name' => 'extend[field][type]',
                    'values' => [
                        ['value' => 'TINYINT', 'field' => ['extend[field][unsigned]', 'extend[field][length]',]],
                        ['value' => 'SMALLINT', 'field' => ['extend[field][unsigned]', 'extend[field][length]',]],
                        ['value' => 'MEDIUMINT', 'field' => ['extend[field][unsigned]', 'extend[field][length]',]],
                        ['value' => 'INT', 'field' => ['extend[field][unsigned]', 'extend[field][length]',]],
                        ['value' => 'BIGINT', 'field' => ['extend[field][unsigned]', 'extend[field][length]',]],
                        ['value' => 'VARCHAR', 'field' => ['extend[field][length]',]]
                    ]
                ], [
                    'name' => 'extend[search][is_open]',
                    'values' => [
                        ['value' => '1', 'field' => ['extend[search][linq]',]],
                    ]
                ], [
                    'name' => 'extend[tables][is_show]',
                    'values' => [
                        ['value' => '1', 'field' => ['extend[tables][templet]', 'extend[tables][edit]',]],
                    ]
                ], [
                    'name' => 'extend[tables][templet]',
                    'values' => [
                        ['value' => 'switch', 'field' => ['extend[tables][switch][name]']],
                    ]
                ], [
                    'name' => 'extend[tables][edit]',
                    'values' => [
                        ['value' => '0', 'field' => ['extend[tables][templet]']],
                    ]
                ],
            ]
        ];
    }


    /**
     * 列表数据处理
     * @param $data
     * @return void
     */
    protected function _index_list_filter(&$data): void
    {
        $forms = $this->app->config->get('form');
        foreach ($data['data'] as &$item) {
            $item['model_name'] = $this->moduleModel->where('id', $item['mid'])->value('title');
            $item['type_name'] = $forms[$item['type']] ?? '未定义类型';
        }

    }

    /**
     * 新增&保存前置处理
     * @param $data
     * @return mixed|void
     */
    protected function _save_filter(&$data)
    {
        if ($this->request->isPost()) {
            if (!empty($data['mid'])) {
                if ($data['mid'] == '-1') {//栏目
                    $sql_fields = $this->columnModel->getTableFields();
                }
                if ($data['mid'] >= 1) {//内容模型
                    $sql_fields = $this->contentModel->setSuffix('_' . $data['mid'])->getTableFields();
                }
            }
            if ($this->request->action() == 'add' && in_array($data['name'], $this->reserve_field)) {
                $this->error('系统保留字段，不可添加');
            }
            if ($this->request->action() == 'add' && isset($sql_fields) && in_array($data['name'], $sql_fields)) {
                $this->error('数据库已存在' . $data['name'] . '字段');
            }
            $result = $this->model->where([
                'mid' => $data['mid'],
                'name' => $data['name'],
            ]);
            if (!empty($data['id'])) {
                $result = $result->where('id', '<>', $data['id']);
            }
            $result = $result->findOrEmpty();
            if (!$result->isEmpty()) {
                $this->error('字段已存在！不可添加');
            }
        }
    }

    protected function formatTableName($data = [])
    {
        if (empty($data)) {
            $data = $this->request->post();
        }
        if (empty($data['mid'])) {
            $this->error('模型id不能不能为空');
        }
        $module = strtolower($this->getModuleName());
        if ($data['name'] == 'content') {
            return "{$module}_content_{$data['mid']}s";
        }
        if ($data['mid'] > 0) {
            return "{$module}_content_{$data['mid']}";
        }

        if ($data['mid'] == '-1') {
            return "{$module}_column";
        }
        $this->error('模型id不正确');
    }

    /**
     * 格式化建表信息
     * @param array $data
     * @return array
     */
    protected function formatAddFields(array $data = []): array
    {
        if (empty($data)) {
            $data = $this->request->post();
        }
        $fields['type'] = $data['extend']['field']['type'];
        $fields['null'] = (bool)$data['extend']['field']['null'];
        $fields['comment'] = $data['title'];
        if (in_array($fields['type'], ['VARCHAR', 'CHAR'])) {
            $fields['length'] = $data['extend']['field']['length'];
        }
        if (in_array($fields['type'], ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'])) {
            $fields['unsigned'] = (bool)$data['extend']['field']['unsigned'];
        }
        if (!in_array($fields['type'], ['TEXT', 'BLOB', 'GEOMETRY', 'JSON', 'LONGTEXT'])) {
            $fields['default'] = (bool)$data['extend']['field']['default'];
        }
        p($fields);
        return $fields;
    }

    /**
     * 添加内容回调
     * @param $result
     * @param $model
     * @param $data
     * @return void
     */
    protected function _add_save_result(&$result, &$model, &$data): void
    {
        if ($result === true) {
            if ($this->addFields($data)) {
                $this->success('添加成功');
            }
        }
        $this->error('添加失败');
    }

    /**
     * 添加字段 功能提取后方便使用事务
     * @param $data
     * @return bool
     */
    protected function addFields($data): bool
    {
        $this->app->db->startTrans();
        try {
            $ret = TableCreatorUtil::instance()->addFields($this->formatTableName($data), [$data['name'] => $this->formatAddFields($data)]);
            if (!$ret['success']) {
                throw new Exception('字段创建失败:' . $ret['message']);
            }
            $this->app->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->app->log->error($e->getMessage());
            $this->app->db->rollback();
            if (!empty($data['id'])) {
                $this->model->where('id', $data['id'])->delete();
            }
            return false;
        }
    }

    /**
     * 添加内容回调
     * @param $result
     * @param $model
     * @param $data
     * @return void
     */
    protected function _edit_save_result(&$result, &$model, &$data): void
    {
        if ($result === true) {
            if ($this->editFields($data)) {
                $this->success('修改成功');
            }
        }
        $this->error('修改失败');

    }

    /**
     * @param $data
     * @return bool
     */
    protected function editFields($data): bool
    {
        $this->app->db->startTrans();
        try {
            $ret = TableCreatorUtil::instance()->modifyField($this->formatTableName($data), $data['name'], $this->formatAddFields($data));
            if (!$ret['success']) {
                throw new Exception('字段修改失败:' . $ret['message']);
            }
            $this->app->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->app->db->rollback();
            $this->app->log->error('字段修改失败:' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param $data
     * @return void
     */
    protected function _delete_filter(&$data): void
    {
        $ret = TableCreatorUtil::instance()->removeFields($this->formatTableName($data), [$data['name']]);
        if (!$ret['success']) {
            $this->error('删除字段失败:' . $ret['message']);
        }
    }
}
