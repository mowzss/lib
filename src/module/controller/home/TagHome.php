<?php
declare(strict_types=1);

namespace mowzs\lib\module\controller\home;

use app\common\controllers\BaseHome;
use mowzs\lib\module\logic\TagBaseLogic;
use think\App;
use think\Exception;

abstract class TagHome extends BaseHome
{


    public function __construct(App $app)
    {
        parent::__construct($app);
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
     * @param int $mid
     * @return string|void
     */
    public function show(int $id = 0, $mid = 0)
    {
        try {
            $info = TagBaseLogic::instance()->getInfo($id);
            //内容回调
            $this->callback('_info_result', $info);
            $list = TagBaseLogic::instance()->getList($id, $mid);
            $this->assign([
                'info' => $info,
                'id' => $id,
                'tid' => $id,
                'list' => $list
            ]);
            return $this->fetch();
        } catch (Exception $e) {
            $this->error('出错了:' . $e->getMessage());
        }
    }
}
