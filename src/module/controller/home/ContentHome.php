<?php

namespace mowzs\lib\module\controller\home;

use app\common\controllers\BaseHome;
use mowzs\lib\module\service\ContentBaseService;
use think\App;

class ContentHome extends BaseHome
{
    /**
     * 服务类名称
     * @var string
     */
    protected static string $serviceClass;
    /**
     * 服务类
     * @var ContentBaseService|mixed
     */
    protected ContentBaseService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        if (empty(static::$serviceClass)) {
            throw new \InvalidArgumentException('The $serviceClass must be set in the subclass.');
        }
        $this->service = new static::$serviceClass($app);
    }

    /**
     * 内容详情
     * @param int $id
     * @return string
     */
    public function index(int $id = 0): string
    {
        if (empty($id)) {
            $this->error('内容ID不能为空');
        }
        try {
            $info = $this->service->getInfo($id);
        } catch (\Exception $e) {
            $this->error('出错了!');
        }
        $this->assign([
            'info' => $info,
            'id' => $id,
            'cid' => $info['cid'],
        ]);
        return $this->fetch();
    }

    protected function addView($id, $mid = 0)
    {

    }
}
