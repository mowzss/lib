<?php

namespace mowzs\lib\helper;

use app\model\system\SystemOperationLog;
use mowzs\lib\Helper;

class OperationLogHelper extends Helper
{

    /**
     * 记录系统操作日志
     * @param string $desc 操作描述
     * @return bool
     */
    public static function log(string $desc): bool
    {
        // 获取当前请求对象
        $request = self::instance()->app->request;

        // 获取管理员ID（假设使用 UserHelper 获取）
        $adminId = self::getAdminId();

        if ($adminId === null) {
            // 如果没有找到管理员ID，返回 false 或者可以选择记录匿名操作
            return false;
        }

        // 获取当前操作节点
        $node = self::getCurrentNode();

        // 获取IP地址
        $ip = $request->ip();

        // 获取User-Agent信息
        $userAgent = $request->header('user-agent', '');
        // 创建并保存日志记录
        $log = new SystemOperationLog();
        $log->uid = $adminId;
        $log->node = $node;
        $log->desc = $desc;
        $log->ip = $ip;
        $log->user_agent = $userAgent;
        return $log->save();
    }

    /**
     * 获取管理员ID
     * @return int|null
     */
    protected static function getAdminId(): ?int
    {
        // 假设使用 UserHelper 获取管理员ID
        return UserHelper::instance()->getUserId();
    }

    /**
     * 获取当前操作节点
     * @return string
     */
    protected static function getCurrentNode(): string
    {
        // 获取当前的控制器和方法名
        $controller = self::instance()->app->request->controller();
        $action = self::instance()->app->request->action();

        // 构建操作节点字符串
        return strtolower("{$controller}/{$action}");
    }
}
