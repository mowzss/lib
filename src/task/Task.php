<?php

namespace mowzs\lib\task;

use app\model\system\SystemTasks;
use Closure;
use Cron\CronExpression;
use think\App;
use think\Cache;

abstract class Task
{

    use ManagesFrequencies;

    /**
     * @var SystemTasks
     */
    protected SystemTasks $model;
    /**
     * @var string|null 时区
     */
    public $timezone = null;

    /**
     * @var string 任务周期
     */
    public $expression = '* * * * *';

    /**
     * @var bool 任务是否可以重叠执行
     */
    public bool $withoutOverlapping = false;

    /**
     * @var int 最大执行时间(重叠执行检查用)
     */
    public int $expiresAt = 1440;

    /**
     * @var bool 分布式部署 是否仅在一台服务器上运行
     */
    public bool $onOneServer = false;
    /**
     * @var array
     */
    protected array $filters = [];
    /**
     * @var array
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
     * 任务信息
     * @var array
     */
    protected array $task_info = [];

    /**
     * @param App $app
     * @param array $task
     */
    public function __construct(App $app, array $task = [])
    {
        $this->app = $app;
        $this->cache = $app->cache;
        $this->model = new SystemTasks();
        if (!empty($task)) {
            $this->task_info = $task;
            $this->expression = $this->task_info['exptime'];
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
     * 配置任务
     */
    protected function configure()
    {
    }

    /**
     * 执行任务
     * @return void
     */
    protected function execute(): void
    {
        $this->app->invoke([$this, 'handle'], [], true);
    }

    /**
     * 运行任务
     * @return void
     * @throws \Exception
     */
    final public function run(): void
    {
        if ($this->withoutOverlapping &&
            !$this->createMutex()) {
            return;
        }

        register_shutdown_function(function () {
            $this->removeMutex();
        });

        try {
            $this->execute();
            $this->updateTask();
        } finally {
            $this->removeMutex();
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function updateTask(): void
    {
        $cronExpression = new CronExpression($this->expression);
        // 获取当前时间并设置时区
        $currentTime = new \DateTime('now', new \DateTimeZone($this->timezone ?? date_default_timezone_get()));
        $update = $this->task_info;
        $update['last_time'] = date('Y-m-d H:i:s');
        // 获取下次执行时间
        $nextRunDate = $cronExpression->getNextRunDate($currentTime, 1, true);
        $update['next_time'] = $nextRunDate->format('Y-m-d H:i:s');
        $update['count'] = $this->app->db->raw('count+1');
        $this->model->update($update);
    }

    /**
     * 过滤
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
     * 任务标识
     */
    public function mutexName(): string
    {
        $name = 'task-' . sha1(static::class);
        $this->app->log->write('任务标识：' . $name, 'task');
        return $name;
    }

    /**
     * 移除锁
     * @return bool
     */
    protected function removeMutex(): bool
    {
        return $this->cache->delete($this->mutexName());
    }

    /**
     * 创建锁
     * @return bool
     */
    protected function createMutex(): bool
    {
        $name = $this->mutexName();

        return $this->cache->set($name, time(), $this->expiresAt);
    }

    /**
     * 检测是否正在执行
     * @return bool
     */
    protected function existsMutex(): bool
    {
        if ($this->cache->has($this->mutexName())) {
            $mutex = $this->cache->get($this->mutexName());
            return $mutex + $this->expiresAt > time();
        }
        return false;
    }

    /**
     * 跳过执行
     * @param Closure $callback
     * @return $this
     */
    public function when(Closure $callback): static
    {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * 跳过执行
     * @param Closure $callback
     * @return $this
     */
    public function skip(Closure $callback): static
    {
        $this->rejects[] = $callback;

        return $this;
    }

    /**
     * 不重叠执行
     * @param $expiresAt
     * @return $this
     */
    public function withoutOverlapping($expiresAt = 1440): static
    {
        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this->skip(function () {
            return $this->existsMutex();
        });
    }

    /**
     * 仅在一台服务器上运行
     * @return $this
     */
    public function onOneServer(): static
    {
        $this->onOneServer = true;

        return $this;
    }
}
