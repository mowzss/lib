<?php

namespace Mowzs\Lib\helper;

use Mowzs\Lib\Helper;

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
}
