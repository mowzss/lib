<?php
declare (strict_types=1);

namespace mowzs\lib\event\controller\admin;

use app\common\controllers\BaseAdmin;
use app\model\system\SystemConfig;
use app\model\system\SystemConfigGroup;
use mowzs\lib\Forms;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\template\exception\TemplateNotFoundException;

/**
 * 模块设置
 */
abstract class SettingAdmin extends BaseAdmin
{
    /**
     * 当前模型
     * @var SystemConfig
     */
    protected SystemConfig $model;
    /**
     * 分组模型
     * @var SystemConfigGroup
     */
    protected SystemConfigGroup $groupModel;
    /**
     * @var
     */
    protected mixed $list;

    protected string $config_module;

    public function __construct(App $app, SystemConfig $config, SystemConfigGroup $configGroup)
    {
        parent::__construct($app);
        $this->model = $config;
        $this->groupModel = $configGroup;
    }

    /**
     * 设置
     * @auth true
     * @return string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(): string
    {
        //处理post请求 子类可以重写handlingPost方法
        if ($this->request->isPost()) {
            $this->handlingPost();
        }
        if (empty($this->config_module)) {
            $this->config_module = $this->request->controller(true, true);
        }

        $this->list = $this->groupModel->where([
            'module' => 'event_' . $this->config_module,
            'status' => 1
        ])->select();
        if ($this->list->isEmpty()) {
            $this->groupModel->insert([
                'module' => 'event_' . $this->config_module,
                'title' => $this->title ?: '事件设置'
            ]);
        }
        //渲染页面
        try {
            return $this->fetch();
        } catch (TemplateNotFoundException $exception) {
            //模板不存在时 尝试读取公用模板
            return $this->fetch('common@/setting');
        }
    }

    /**
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function handlingPost(): void
    {
        $data = $this->request->post();
        if (empty($data['group_id'])) {
            $this->error('group_id不能为空');
        }
        if ($this->model->saveConfig($data)) {
            $this->success('保存成功');
        } else {
            $this->error('保存失败');
        }
    }

    /**
     * 获取设置表单
     * @param int $group_id
     * @return string|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws \think\Exception
     */
    public function getForms(int $group_id = 0)
    {
        if (empty($group_id)) {
            $this->error('group_id 不能为空');
        }
        $data = $this->model->getListByGroup($group_id);
        if (!empty($data)) {
            return Forms::instance(['action' => urls('index')])
                ->setInputs([['type' => 'hidden', 'name' => 'group_id', 'value' => $group_id]])
                ->render($data);
        }
        $this->error('暂无设置表单信息');
    }
}
