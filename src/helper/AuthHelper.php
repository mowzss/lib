<?php

namespace mowzs\lib\helper;

use mowzs\lib\Helper;
use think\Exception;

class AuthHelper extends Helper
{
    /**
     * 校验权限
     * @param string $node 节点信息
     * @return bool
     */
    public function cheek(string $node = ''): bool
    {

        //超管
        if ($this->isAuthAdmin()) {
            return true;
        }
        $node = NodeHelper::instance()->wholeNode($node);
        try {
            $nodes = NodeHelper::instance()->getMethods();
        } catch (Exception $e) {
            return false;
        }
        $check = $nodes[$node];
        //无需校验页面 is_auth 为true时 is_login 永远为 true
        if (empty($check['is_login'])) {
            return true;
        }

        // 仅登录节点
        if (empty($check['is_auth']) && !empty($this->getUser())) {
            return true;
        }
        //用户权限组包含当前节点
        if (in_array($node, $this->getUserNodes())) {
            return true;
        }
        //其它未判定
        return false;
    }

    /**
     * 获取用户权限节点
     * @return array|mixed
     */
    protected function getUserNodes(): mixed
    {
        $user = $this->getUser();
        if (!empty($user)) {
            return $user['nodes'] ?: [];
        }
        return [];
    }

    /**
     * 获取超管账号
     * @return array|mixed
     */
    protected function getAuthAdmin(): mixed
    {
        return $this->app->config->get('auth.auth_admin');
    }

    /**
     * 是否超管
     * @return bool
     */
    public function isAuthAdmin(): bool
    {
        if ($this->getAuthAdmin() == $this->getUserName()) {
            return true;
        }
        return false;
    }

    /**
     * 是否登录
     * @return bool
     */
    public function isLogin(): bool
    {
        return (bool)$this->getUser();
    }

    /**
     * 用户信息
     * @return mixed
     */
    protected function getUser(): mixed
    {
        return $this->app->session->get('user');
    }

    /**
     * 获取用户账号
     * @return mixed
     */
    protected function getUserName()
    {
        return $this->app->session->get('user.username');
    }
}
