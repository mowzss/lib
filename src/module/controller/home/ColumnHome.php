<?php
declare(strict_types=1);

namespace mowzs\lib\module\controller\home;

use app\common\controllers\BaseHome;
use mowzs\lib\helper\SystemHelper;
use mowzs\lib\module\logic\ColumnBaseLogic;
use think\App;
use think\Exception;
use think\template\exception\TemplateNotFoundException;

class ColumnHome extends BaseHome
{
    /**
     * 服务类名称
     * @var string
     */
    protected static string $serviceClass = ColumnBaseLogic::class;
    /**
     * 服务类
     * @var
     */
    protected ColumnBaseLogic $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        if (empty(static::$serviceClass)) {
            throw new \InvalidArgumentException('The $serviceClass must be set in the subclass.');
        }
        $this->service = new static::$serviceClass();
    }


    /**
     * 栏目列表
     * @param int $id
     * @return string
     * @throws Exception
     */
    public function index(int $id = 0): string
    {
        try {
            $info = $this->service->getInfo($id);
        } catch (Exception $e) {
            $this->error('出错了:' . $e->getMessage());
        }
        $this->callback('_info_result', $info);
        $this->assign([
            'info' => $info,
            'cid' => $info['id'],
            'id' => $info['id'],
        ]);
        if (SystemHelper::instance()->isMobile()) {
            $view_file = $info['view_file']['wap'] ?: 'index_' . $info['mid'];
        } else {
            $view_file = $info['view_file']['pc'] ?: 'index_' . $info['mid'];
        }

        //渲染页面
        try {
            return $this->fetch($view_file);
        } catch (TemplateNotFoundException $exception) {
            //模板不存在时 尝试读取公用模板
            return $this->fetch();
        }
    }
}
