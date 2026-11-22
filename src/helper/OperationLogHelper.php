<?php
declare(strict_types=1);


namespace happy\admin\libs\helper;

use happy\admin\libs\Helper;
use app\model\system\SystemOperationLog;

/**
 * 系统操作日志助手
 *
 * 写入目标：ha_{prefix}_system_operation_log（表结构见 happy-system 安装 SQL）。
 * 设计约束：
 *  1) 审计日志写入失败绝不能阻断业务，所有异常在此层静默并落框架日志；
 *  2) 表 create_time 为 int 且模型未开启自动时间戳，必须显式写入；
 *  3) 未登录（无法归属到 uid）时不记录，避免产生脏数据；
 *  4) 所有入库字段做长度收敛，避免超长 User-Agent / desc 触发 SQL 报错。
 */
class OperationLogHelper extends Helper
{
    private const MAX_NODE = 255;
    private const MAX_IP   = 45;
    private const MAX_UA   = 1000;
    private const MAX_DESC = 2000;

    /**
     * 记录系统操作日志
     *
     * @param string $desc    操作描述；为空时按当前节点生成兜底描述
     * @param array  $context 可选覆盖项：uid / node / ip / user_agent / create_time
     * @return bool
     */
    public static function log(string $desc = '', array $context = []): bool
    {
        try {
            $app     = self::instance()->app;
            $request = $app->request;

            $uid = (int)($context['uid'] ?? self::getAdminId());
            if ($uid <= 0) {
                return false;
            }

            $node      = (string)($context['node'] ?? self::getCurrentNode());
            $ip        = (string)($context['ip'] ?? $request->ip());
            $userAgent = (string)($context['user_agent'] ?? $request->header('user-agent', ''));
            $createdAt = (int)($context['create_time'] ?? time());

            $data = [
                'uid'         => $uid,
                'node'        => self::cut($node, self::MAX_NODE),
                'desc'        => self::cut($desc !== '' ? $desc : self::defaultDesc($node), self::MAX_DESC),
                'ip'          => self::cut($ip, self::MAX_IP),
                'user_agent'  => self::cut($userAgent, self::MAX_UA),
                'create_time' => $createdAt > 0 ? $createdAt : time(),
            ];

            return (new SystemOperationLog())->save($data);
        } catch (\Throwable $e) {
            self::reportFailure($e);
            return false;
        }
    }

    /**
     * 获取管理员 ID（后台登录态写在 session.user 中）
     * @return int 未登录返回 0
     */
    protected static function getAdminId(): int
    {
        return (int)UserHelper::instance()->getUserId();
    }

    /**
     * 获取当前操作节点，与后台权限节点保持同一格式（snake(controller)/action）
     * @return string
     */
    protected static function getCurrentNode(): string
    {
        try {
            return NodeHelper::instance()->getThisNode();
        } catch (\Throwable $e) {
            $request = self::instance()->app->request;
            return strtolower(str_replace('.', '/', (string)$request->controller())
                . '/' . (string)$request->action());
        }
    }

    /**
     * 兜底描述
     * @param string $node
     * @return string
     */
    protected static function defaultDesc(string $node): string
    {
        return '执行操作：' . $node;
    }

    /**
     * 按字符数收敛字符串
     * @param string $value
     * @param int    $length
     * @return string
     */
    private static function cut(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    /**
     * 写入失败只记框架日志，不向上抛出
     * @param \Throwable $e
     * @return void
     */
    private static function reportFailure(\Throwable $e): void
    {
        try {
            self::instance()->app->log->error('[操作日志] 写入失败：' . $e->getMessage());
        } catch (\Throwable $ignored) {
            // 日志组件本身异常时彻底静默
        }
    }
}
