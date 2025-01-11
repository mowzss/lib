<?php
declare (strict_types=1);

namespace mowzs\lib\module\controller\admin;

use app\common\controllers\BaseAdmin;
use app\common\traits\CrudTrait;
use app\common\util\TableCreatorUtil;
use think\App;
use think\Model;

/**
 * 模型管理
 */
abstract class ModelAdmin extends BaseAdmin
{
    use CrudTrait;

    /**
     * 当前主模型 默认为空 子类声明
     * @var string
     */
    protected static string $modelClass;

    /**
     * 字段设计模型类
     * @var string
     */
    protected static string $fieldClass;
    /**
     * 实例字段设计模型
     * @var Model
     */
    protected mixed $fieldModel;

    public function __construct(App $app)
    {
        parent::__construct($app);
        if (empty(static::$modelClass)) {
            throw new \InvalidArgumentException('The $modelClass must be set in the subclass.');
        }
        $this->model = new static::$modelClass(); // 动态实例化子类指定的模型
        if (empty(static::$fieldClass)) {
            throw new \InvalidArgumentException('The $fieldClass must be set in the subclass.');
        }
        $this->fieldModel = new static::$fieldClass(); // 动态实例化子类指定的模型
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
                    'field' => 'title',
                    'title' => '模型名称',
                    'edit' => "text"
                ], [
                    'field' => 'info',
                    'title' => '介绍',
                ], [
                    'field' => 'list',
                    'title' => '排序',
                    'edit' => "text"
                ]
            ],
            //表格行按钮
            'right_button' => [
                [
                    'event' => '',
                    'type' => 'data-open',
                    'url' => urls('field/index', ['mid' => '__id__']),
                    'name' => '字段设计',
                    'class' => '',//默认包含 layui-btn layui-btn-xs
                ],
                ['event' => 'edit'],
                ['event' => 'del'],
            ],
        ];
        $this->forms = [
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'title',
                    'label' => '模型名称',
                    'required' => true
                ], [
                    'type' => 'textarea',
                    'name' => 'info',
                    'label' => '介绍',
                    'help' => '功能详细说明',
                ]
            ]
        ];
    }

    /**
     * 列表数据处理
     * @param $data
     * @return void
     */
//    protected function _index_list_filter(&$data): void
//    {
//        foreach ($data['data'] as &$item) {
//            $item['type_name'] = TableCreatorUtil::instance()->type[$item['type']];
//        }
//    }

    /**
     * 添加内容回调
     * @param $result
     * @param $data
     * @return void
     */
    protected function _add_save_result(&$result, &$data): void
    {
        if ($result !== true) {
            $this->error('添加失败');
        }
        if ($this->createTable($data)) {
            $this->success('添加成功');
        } else {
            $this->error('添加失败:数据表创建失败');
        }
    }

    /**
     * 建表
     * @param $data
     * @return bool
     */
    protected function createTable($data): bool
    {
        try {// 开启事务
            $this->app->db->startTrans();// 获取模块名称并构建表名
            $module = strtolower($this->getModuleName());
            $contentTable = "{$module}_content_{$data['id']}";
            $contentsTable = "{$module}_content_{$data['id']}s";// 创建 content 表
            $retContent = TableCreatorUtil::instance()->createTable($contentTable, 3);
            if (!$retContent['success']) {
                throw new \Exception("Failed to create table '{$contentTable}': " . $retContent['message']);
            }// 创建 contents 表
            $retContents = TableCreatorUtil::instance()->createTable($contentsTable, 4);
            if (!$retContents['success']) {
                throw new \Exception("Failed to create table '{$contentsTable}': " . $retContents['message']);
            }// 删除旧的字段记录
            $this->fieldModel->where('mid', $data['id'])->delete();// 准备新的字段数据
            $fieldData = [
                ['mid' => $data['id'], 'name' => 'title', 'type' => 'text', 'title' => '标题', 'options' => '', 'help' => null, 'required' => '1', 'list' => '1000', 'edit' => '1', 'extend' => '{"field":{"type":"VARCHAR","length":"256","unsigned":"0","null":"0","default":"\'\'"},"search":{"is_open":"1","linq":"like"},"tables":{"is_show":"1","templet":"","switch":{"name":""},"edit":"0"},"add":{"is_show":"1"}}', 'status' => '1', 'create_time' => time(), 'update_time' => time(), 'is_search' => null],
                ['mid' => $data['id'], 'name' => 'keywords', 'type' => 'text', 'title' => '关键词', 'options' => '', 'help' => null, 'required' => '0', 'list' => '100', 'edit' => '1', 'extend' => '{"field":{"type":"VARCHAR","length":"2000","unsigned":"0","null":"0","default":"\'\'"},"search":{"is_open":"0","linq":""},"tables":{"is_show":"0","templet":"","switch":{"name":""},"edit":"0"},"add":{"is_show":"0"}}', 'status' => '1', 'create_time' => time(), 'update_time' => time(), 'is_search' => null],
                ['mid' => $data['id'], 'name' => 'description', 'type' => 'textarea', 'title' => '简介', 'options' => '', 'help' => null, 'required' => '0', 'list' => '100', 'edit' => '1', 'extend' => '{"field":{"type":"TEXT","length":"","unsigned":"0","null":"0","default":""},"search":{"is_open":"0","linq":""},"tables":{"is_show":"0","templet":"","switch":{"name":""},"edit":"0"},"add":{"is_show":"1"}}', 'status' => '1', 'create_time' => time(), 'update_time' => time(), 'is_search' => null],
                ['mid' => $data['id'], 'name' => 'content', 'type' => 'editor', 'title' => '内容', 'options' => '', 'help' => null, 'required' => '1', 'list' => '1', 'edit' => '1', 'extend' => '{"field":{"type":"LONGTEXT","length":"","unsigned":"0","null":"0","default":""},"search":{"is_open":"0","linq":""},"tables":{"is_show":"0","templet":"","switch":{"name":""},"edit":"0"},"add":{"is_show":"1"}}', 'status' => '1', 'create_time' => time(), 'update_time' => time(), 'is_search' => null],
                ['mid' => $data['id'], 'name' => 'images', 'type' => 'images', 'title' => '组图', 'options' => '', 'help' => null, 'required' => '0', 'list' => '80', 'edit' => '1', 'extend' => '{"field":{"type":"TEXT","length":"","unsigned":"0","null":"0","default":""},"search":{"is_open":"0","linq":""},"tables":{"is_show":"0","templet":"","switch":{"name":""},"edit":"0"},"add":{"is_show":"1"}}', 'status' => '1', 'create_time' => time(), 'update_time' => time(), 'is_search' => null]
            ];// 插入字段数据
            $this->fieldModel->saveAll($fieldData);// 提交事务
            $this->app->db->commit();// 返回成功信息
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            $this->app->db->rollback();
            //建表失败 删除记录
            $this->model->where('id', $data['id'])->delete();
            // 记录错误日志
            $this->app->log->error("Error in _add_save_result: " . $e->getMessage());
            // 返回详细的错误信息
            return false;
        }
    }

    /**
     * @param $data
     * @param $ids
     * @return void
     */
    protected function _delete_filter(&$data, &$ids): void
    {
        if (is_array($ids)) {
            $this->error('模型禁止批量删除');
        }
        if ($data['is_del'] == 0 || $data['id'] <= 1) {
            $this->error('当前模型禁止删除');
        }
    }

    /**
     * @param $ret
     * @param $ids
     * @return void
     */
    protected function _delete_result(&$ret, &$ids): void
    {
        if (!empty($ids)) {
            if (!is_array($ids)) {
                if ($this->delTable($ids)) {
                    $this->fieldModel->where('mid', $ids)->delete();
                    $this->success('删除成功');
                }
            }
        }
        $this->error('数据表删除失败');
    }

    /**
     * 删除数据表
     * @param $mid 模块 ID
     * @return bool
     */
    protected function delTable($mid): bool
    {
        $this->app->db->startTrans();
        try {
            $module = strtolower($this->getModuleName());
            $contentTable = "{$module}_content_{$mid}";
            $contentsTable = "{$module}_content_{$mid}s";

            // 删除 content 表
            $retContent = TableCreatorUtil::instance()->dropTable($contentTable);
            if (!$retContent['success']) {
                throw new \Exception("Failed to drop table '{$contentTable}': " . $retContent['message']);
            }
            // 删除 contents 表
            $retContents = TableCreatorUtil::instance()->dropTable($contentsTable);
            if (!$retContents['success']) {
                throw new \Exception("Failed to drop table '{$contentsTable}': " . $retContents['message']);
            }

            $this->app->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->app->log->error("Error in delTable: " . $e->getMessage());
            $this->app->db->rollback();
            return false;
        }
    }


}
