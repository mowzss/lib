<?php
declare(strict_types=1);

namespace mowzs\lib\module\controller\home;

use app\common\controllers\BaseHome;

abstract class IndexHome extends BaseHome
{
    public function index(): string
    {
        return $this->fetch();
    }
}
