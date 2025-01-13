<?php
declare (strict_types=1);

namespace mowzs\lib\helper;

use app\model\user\UserGroup;
use app\model\user\UserInfo;
use mowzs\lib\Helper;

/**
 * 用户信息助手类
 */
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
     * @param string|int $uid
     * @param string $field
     * @param string $default
     * @return array|mixed
     */
    public function getUserInfo(string|int $uid = '', string $field = '', string $default = ''): mixed
    {
        if (empty($uid)) {
            $user_info = $this->app->session->get('user', []);
        } else {
            $user_info = (new UserInfo())->findOrEmpty($uid)->toArray();
        }

        if (empty($field)) {
            return $user_info;
        }
        return $user_info[$field] ?? $default;

    }

    /**
     * 获取用户组信息
     * @param string|int $uid
     * @param string $field
     * @param string $default
     * @return mixed
     */
    public function getUserGroup(string|int $uid = '', string $field = '', string $default = ''): mixed
    {
        if (empty($uid)) {
            $uid = $this->getUserId();
        }
        $info = (new UserGroup())->findOrEmpty($uid)->toArray();
        if (empty($field)) {
            return $info;
        }
        return $info[$field] ?? $default;
    }

}
