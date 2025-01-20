<?php
declare (strict_types=1);

namespace mowzs\lib\helper;

use app\model\user\UserAuth;
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
            $this->app->log->error($e->getMessage());
            return false;
        }
        //权限节点未记录 则表示无需校验权限
        if (!isset($nodes[$node])) {
            return true;
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
        if (!empty($user['auth_id'])) {
            return UserAuth::where('id', $user['auth_id'])->value('nodes');
        }
        return [];
    }

    /**
     * @return array|string[]|\string[][]
     */
    public function getUserNodesModule(): array
    {
        $nodes = $this->getUserNodes();
        return array_map(function ($item) {
            return str_replace('.', '/', $item);
        }, $nodes);
    }

    /**
     * 获取超管账号
     * @return array|mixed
     */
    protected function getAuthAdmin(): mixed
    {
        return $this->app->config->get('happy.auth_admin');
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
