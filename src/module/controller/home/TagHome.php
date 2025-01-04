<?php

namespace mowzs\lib\module\controller\home;

use app\common\controllers\BaseHome;
use mowzs\lib\module\service\ContentBaseService;
use mowzs\lib\module\service\TagBaseService;
use think\App;
use think\Exception;

class TagHome extends BaseHome
{
    /**
     * 服务类名称
     * @var string
     */
    protected static string $serviceClass = TagBaseService::class;
    /**
     * 服务类
     * @var ContentBaseService|mixed
     */
    protected TagBaseService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        if (empty(static::$serviceClass)) {
            throw new \InvalidArgumentException('The $serviceClass must be set in the subclass.');
        }
        $this->service = new static::$serviceClass();
    }

    /**
     * tag首页
     * @return string
     */
    public function index(): string
    {
        return $this->fetch();
    }

    /**
     * tag详情
     * @param int $id
     * @return string|void
     */
    public function show(int $id = 0)
    {
        try {
            $info = $this->service->getInfo($id);
            //内容回调
            $this->callback('_info_result', $info);

            $this->assign([
                'info' => $info,
                'id' => $id,
                'tid' => $id
            ]);
            return $this->fetch();
        } catch (Exception $e) {
            $this->error('出错了:' . $e->getMessage());
        }
    }
}
