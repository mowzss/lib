<?php
declare(strict_types=1);

namespace mowzs\lib\module\controller\home;

use app\common\controllers\BaseHome;
use mowzs\lib\module\service\ColumnBaseService;
use think\App;
use think\Exception;

class ColumnHome extends BaseHome
{
    /**
     * 服务类名称
     * @var string
     */
    protected static string $serviceClass = ColumnBaseService::class;
    /**
     * 服务类
     * @var
     */
    protected ColumnBaseService $service;

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
        return $this->fetch();
    }
}
