<?php

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class SystemHelper extends Helper
{
    /**
     * 检测是否使用手机访问
     * @return bool 可重写判断 自定义全局使用wap检测规则
     */
    public function isMobile(): bool
    {
        return $this->app->request->isMobile();
    }
}
