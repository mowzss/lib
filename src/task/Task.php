<?php

declare (strict_types=1);

namespace happy\admin\libs\task;

use Closure;
use think\App;
use think\Cache;
use think\facade\Db;
use Cron\CronExpression;
use think\db\exception\DbException;

/**
 * 计划任务基础类
 * 子类只需实现 handle() 方法即可
 */
abstract class Task
{
    /**
     * @var string|null 时区
     */
    public ?string $timezone = null;
    
    /**
     * @var string 任务周期
     */
    public string $expression = '* * * * *';
    
    /**
     * @var bool 任务是否可以重叠执行
     */
    public bool $withoutOverlapping = false;
    
    /**
     * @var int 最大执行时间(重叠执行检查用，单位：秒)
     */
    public int $expiresAt = 3600;
    
    /**
     * @var bool 分布式部署 是否仅在一台服务器上运行
     */
    public bool $onOneServer = false;
    
    /**
     * @var array 执行过滤器
     */
    protected array $filters = [];
    
    /**
     * @var array 执行拒绝器
     */
    protected array $rejects = [];
    
    /**
     * @var Cache
     */
    protected Cache $cache;
    
    /**
     * @var App
     */
    protected App $app;
    
    /**
     * 任务信息（从数据库读出的原始记录）
     * @var array
     */
    protected array $task_info = [];
    
    /**
     * 执行开始时间戳
     * @var float
     */
    protected float $startTime = 0.0;
    
    /**
     * 执行输出内容
     * @var string
     */
    protected string $output = '';
    
    /**
     * 执行是否成功
     * @var bool
     */
    protected bool $success = false;
    
    /**
     * 错误信息
     * @var string
     */
    protected string $error = '';
    
    /**
     * 触发类型：cron 自动触发 / manual 手动触发
     * @var string
     */
    protected string $triggerType = 'cron';
    
    /**
     * @param App $app
     * @param array $task 任务配置数组（含 id、exptime、task 等字段）
     */
    public function __construct(App $app, array $task = [])
    {
        $this->app = $app;
        $this->cache = $app->cache;
        $this->task_info = $task;
        if (!empty($task['exptime'])) {
            $this->expression = $task['exptime'];
        }
        $this->configure();
    }
    
    /**
     * 是否到期执行
     * @return bool
     */
    public function isDue(): bool
    {
        $cronExpression = new CronExpression($this->expression);
        return $cronExpression->isDue('now', $this->timezone);
    }
    
    /**
     * 配置任务（子类可重写）
     */
    protected function configure()
    {
    }
    
    /**
     * 执行任务（内部调用）
     * @return void
     */
    protected function execute(): void
    {
        $result = $this->app->invoke([$this, 'handle'], [], true);
        if (is_string($result)) {
            $this->output = $result;
        } elseif (is_array($result) || is_object($result)) {
            $this->output = json_encode($result, JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * 任务执行入口（子类必须实现）
     * @return void
     */
    abstract public function handle(): void;
    
    /**
     * 运行任务（对外入口）
     * @param string $triggerType 触发类型：cron / manual
     * @return array 执行结果
     * @throws \Exception
     */
    final public function run(string $triggerType = 'cron'): array
    {
        $this->triggerType = $triggerType;
        $this->startTime = microtime(true);
        
        // 1. 执行条件过滤（when / skip）
        if (!$this->filtersPass()) {
            return $this->buildResult('skipped', '任务被条件过滤跳过');
        }
        
        // 2. 重叠执行检查
        if ($this->withoutOverlapping && !$this->createMutex()) {
            return $this->buildResult('skipped', '任务正在执行中，已跳过重叠执行');
        }
        
        // 3. 分布式单实例检查
        if ($this->onOneServer && !$this->createServerMutex()) {
            $this->removeMutex();
            return $this->buildResult('skipped', '分布式部署，其他服务器正在执行');
        }
        
        // 4. 注册 shutdown 时自动释放锁（防止异常退出导致死锁）
        register_shutdown_function(function () {
            $this->removeMutex();
            $this->removeServerMutex();
        });
        
        try {
            // 5. 执行任务
            $this->execute();
            $this->success = true;
        } catch (\Throwable $e) {
            $this->success = false;
            $this->error = $e->getMessage();
            // 记录异常日志
            $this->app->log->error('任务执行失败: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        } finally {
            // 6. 更新任务状态（不管成功失败都更新最后执行时间）
            $this->updateTask();
            // 7. 记录执行日志
            $this->logExecution();
            // 8. 释放锁
            $this->removeMutex();
            $this->removeServerMutex();
        }
        
        return $this->buildResult(
            $this->success ? 'success' : 'fail',
            $this->success ? '执行成功' : '执行失败: ' . $this->error
        );
    }
    
    /**
     * 构建返回结果
     * @param string $status
     * @param string $message
     * @return array
     */
    protected function buildResult(string $status, string $message): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'output' => $this->output,
            'cost_time' => round(microtime(true) - $this->startTime, 2),
        ];
    }
    
    /**
     * 更新任务状态（最后执行时间、下次执行时间、计数）
     * @return void
     * @throws DbException
     */
    protected function updateTask(): void
    {
        if (empty($this->task_info['id'])) {
            return;
        }
        $cronExpression = new CronExpression($this->expression);
        $currentTime = new \DateTime('now', new \DateTimeZone($this->timezone ?? date_default_timezone_get()));
        $nextRunDate = $cronExpression->getNextRunDate($currentTime, 0, true);
        
        $updateData = [
            'last_time' => date('Y-m-d H:i:s'),
            'next_time' => $nextRunDate->format('Y-m-d H:i:s'),
        ];
        // 成功才计数
        if ($this->success) {
            $updateData['count'] = Db::raw('count + 1');
        }
        
        Db::name($this->getTaskTable())->where('id', $this->task_info['id'])->update($updateData);
    }
    
    /**
     * 记录执行日志
     * @return void
     */
    protected function logExecution(): void
    {
        if (empty($this->task_info['id'])) {
            return;
        }
        try {
            Db::name($this->getLogTable())->insert([
                'task_id' => $this->task_info['id'],
                'type' => $this->triggerType,
                'status' => $this->success ? 'success' : 'fail',
                'output' => $this->output,
                'error_msg' => $this->error,
                'cost_time' => round(microtime(true) - $this->startTime, 2),
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // 日志写入失败不影响主流程
            $this->app->log->error('任务执行日志写入失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取任务表名（子类可重写以适配不同表）
     * @return string
     */
    protected function getTaskTable(): string
    {
        return 'system_tasks';
    }
    
    /**
     * 获取日志表名（子类可重写以适配不同表）
     * @return string
     */
    protected function getLogTable(): string
    {
        return 'system_tasks_log';
    }
    
    /**
     * 执行条件过滤
     * @return bool
     */
    public function filtersPass(): bool
    {
        foreach ($this->filters as $callback) {
            if (!call_user_func($callback)) {
                return false;
            }
        }
        
        foreach ($this->rejects as $callback) {
            if (call_user_func($callback)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 任务互斥锁名称
     * @return string
     */
    public function mutexName(): string
    {
        $taskId = $this->task_info['id'] ?? md5(static::class);
        return 'task-' . $taskId;
    }
    
    /**
     * 分布式单实例锁名称
     * @return string
     */
    public function serverMutexName(): string
    {
        return 'task-server-' . sha1(static::class);
    }
    
    /**
     * 移除互斥锁
     * @return bool
     */
    protected function removeMutex(): bool
    {
        return $this->cache->delete($this->mutexName());
    }
    
    /**
     * 创建互斥锁
     * @return bool
     */
    protected function createMutex(): bool
    {
        $name = $this->mutexName();
        // 如果已存在锁且未过期，则获取失败
        if ($this->cache->has($name)) {
            return false;
        }
        return $this->cache->set($name, time(), $this->expiresAt);
    }
    
    /**
     * 创建分布式服务器锁
     * @return bool
     */
    protected function createServerMutex(): bool
    {
        $name = $this->serverMutexName();
        if ($this->cache->has($name)) {
            return false;
        }
        return $this->cache->set($name, [
            'server' => gethostname(),
            'time' => time(),
        ], $this->expiresAt);
    }
    
    /**
     * 移除分布式服务器锁
     * @return bool
     */
    protected function removeServerMutex(): bool
    {
        return $this->cache->delete($this->serverMutexName());
    }
    
    /**
     * 检测锁是否存在
     * @return bool
     */
    protected function existsMutex(): bool
    {
        return $this->cache->has($this->mutexName());
    }
    
    /**
     * 执行条件（满足时才执行）
     * @param Closure $callback
     * @return $this
     */
    public function when(Closure $callback): static
    {
        $this->filters[] = $callback;
        return $this;
    }
    
    /**
     * 跳过条件（满足时不执行）
     * @param Closure $callback
     * @return $this
     */
    public function skip(Closure $callback): static
    {
        $this->rejects[] = $callback;
        return $this;
    }
    
    /**
     * 设置不重叠执行
     * @param int $expiresAt 超时时间（秒）
     * @return $this
     */
    public function withoutOverlapping(int $expiresAt = 3600): static
    {
        $this->withoutOverlapping = true;
        $this->expiresAt = $expiresAt;
        return $this;
    }
    
    /**
     * 仅在一台服务器上运行（分布式部署）
     * @return $this
     */
    public function onOneServer(): static
    {
        $this->onOneServer = true;
        return $this;
    }
}
