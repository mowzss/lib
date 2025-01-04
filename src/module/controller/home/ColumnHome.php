<?php

namespace mowzs\lib\module\controller\home;

use app\common\controllers\BaseHome;

class ColumnHome extends BaseHome
{

    /**
     * 栏目列表
     * @param int $cid
     * @return string
     */
    public function index(int $cid = 0): string
    {
        if (empty($cid)) {
            $this->error('栏目ID不能为空');
        }
        $info = $this->columnModel->findOrEmpty($cid);
        if ($info->isEmpty()) {
            $this->error('栏目信息不存在');
        }
        $this->assign([
            'info' => $info,
            'cid' => $info['cid'],
        ]);
        return $this->fetch();
    }
}
