<?php
declare (strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class UserHelper extends Helper
{
    /**
     * @param null $default
     * @return mixed
     */
    public function getUserId($default = null): mixed
    {
        return $this->app->session->get('user.id', $default);
    }

    /**
     * 获取登录信息
     * @param $uid
     * @return array|false
     */
    public function getUserInfo(): array
    {
        return $this->app->session->get('user', []);
    }
}
