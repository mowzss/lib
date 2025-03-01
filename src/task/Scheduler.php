<?php

namespace mowzs\lib\task;

use app\model\system\SystemTasks;
use Cron\CronExpression;
use Exception;
use mowzs\lib\task\event\TaskFailed;
use mowzs\lib\task\event\TaskProcessed;
use mowzs\lib\task\event\TaskSkipped;
use think\App;

class Scheduler
{
    /** @var App */
    protected $app;

    protected $tasks = [];

    /**
     * @var SystemTasks
     */
    protected SystemTasks $model;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->model = new SystemTasks();
        $this->getTasks();
    }

    public function run(): void
    {
        if (empty($this->tasks)) {
            $this->app->log->write('数据库无任务数据', 'task');
            return;
        }
        foreach ($this->tasks as $task_info) {
            $task_class = $task_info['task'];
            // 检查如果是任务类 则实例化
            if (is_subclass_of($task_class, Task::class)) {
                /** @var Task $task */
                $task = $this->app->invokeClass($task_class, [$task_info]);
                if ($this->app->isDebug()) {
                    $this->app->log->write('task class info: ' . $task_class, 'task');
                    $this->app->log->write('task info: ' . json_encode($task_info), 'task');
                }
                if (!$task->filtersPass()) {
                    continue;
                }
                if ($task->onOneServer) {
                    $this->runSingleServerTask($task);
                } else {
                    $this->runTask($task);
                }
                $this->app->event->trigger(new TaskProcessed($task));
            } else {
                $this->runCommandWithLock($task_info);
            }
        }
    }

    /**
     * @return void
     */
    protected function getTasks(): void
    {
        $this->tasks = $this->model->getTaskList();
        $this->app->log->write(json_encode($this->tasks), 'task');
    }

    /**
     * @param $task Task
     * @return bool
     */
    protected function serverShouldRun($task): bool
    {
        $key = $task->mutexName() . md5(date('y-m-d H:i'));
        if ($this->app->cache->has($key)) {
            return false;
        }
        $this->app->cache->set($key, true, 60);
        return true;
    }

    protected function runSingleServerTask($task): void
    {
        if ($this->serverShouldRun($task)) {
            $this->runTask($task);
        } else {
            $this->app->event->trigger(new TaskSkipped($task));
        }
    }

    /**
     * @param $task Task
     */
    protected function runTask($task)
    {
        try {
            $task->run();
        } catch (Exception $e) {
            $this->app->event->trigger(new TaskFailed($task, $e));
        }
    }

    /**
     *
     * @param mixed $task_info
     * @param string $outputContent
     * @return void
     * @throws Exception
     */
    protected function handleCommandOutput(mixed $task_info, string $outputContent): void
    {
        if ($this->app->isDebug()) {
            $this->app->log->write('任务返回信息：' . $outputContent, 'task');
        }
        // 分割为行数组
        $lines = explode(PHP_EOL, $outputContent);
        // 判断行数是否小于等于 10
        if (count($lines) <= 10) {
            // 如果行数小于等于 10，返回全部内容
            $finalContent = $outputContent;
        } else {
            // 获取开始 5 行
            $first5Lines = array_slice($lines, 0, 5);
            $first5LinesContent = implode(PHP_EOL, $first5Lines);
            // 获取最后 5 行
            $last5Lines = array_slice($lines, -5);
            $last5LinesContent = implode(PHP_EOL, $last5Lines);
            // 合并开始 5 行、中间的 `.....` 和最后 5 行
            $finalContent = $first5LinesContent . PHP_EOL . '.....' . PHP_EOL . $last5LinesContent;
        }

        // 保存本次运行记录
        $cronExpression = new CronExpression($task_info['exptime']);
        $currentTime = new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
        $update = $task_info;
        $update['last_time'] = date('Y-m-d H:i:s');
        $nextRunDate = $cronExpression->getNextRunDate($currentTime, 1, true);
        $update['next_time'] = $nextRunDate->format('Y-m-d H:i:s');
        $update['count'] = $this->app->db->raw('count+1');
        $update['output_msg'] = $finalContent; // 使用最终处理后的内容
        $this->model->update($update);
    }

    /**
     * 运行命令行
     * @param array $task_info
     * @return void
     */
    protected function runCommandWithLock(array $task_info): void
    {
        // 生成锁的唯一键
        $lockKey = 'task_lock_' . md5($task_info['task'] . $task_info['exptime'] . date('Y-m-d H:i'));
        $lockTTL = 60; // 锁的有效期（秒）

        // 尝试获取锁
        if ($this->app->cache->has($lockKey)) {
            // 如果锁已存在，触发任务跳过事件
            $this->app->event->trigger(new TaskSkipped($task_info['task'], 'Task is already running'));
            return;
        }

        try {
            // 设置锁
            $this->app->cache->set($lockKey, true, $lockTTL);

            // 执行命令
            $command = explode(' ', $task_info['task']);
            $outputContent = $this->app->console->call($command[0], array_slice($command, 1))->fetch();

            // 处理输出内容
            $this->handleCommandOutput($task_info, $outputContent);

            // 触发任务完成事件
            $this->app->event->trigger(new TaskProcessed($task_info['task'], $outputContent));
        } catch (\Exception $e) {
            // 如果命令执行失败，触发任务失败事件
            $this->app->event->trigger(new TaskFailed($task_info['task'], $e));
        } finally {
            // 确保释放锁
            $this->app->cache->delete($lockKey);
        }
    }
}
