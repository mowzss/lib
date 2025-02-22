<?php

namespace mowzs\lib\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;

class AdminTasks extends Command
{
    protected $pidFile = '/tmp/admin-tasks.pid'; // PID 文件路径

    protected function configure()
    {
        $this->setName('admin:tasks')
            ->setDescription('用于管理定时任务和周期性任务')
            ->addOption('start', null, Argument::OPTIONAL, '启动任务管理器')
            ->addOption('stop', null, Argument::OPTIONAL, '停止任务管理器')
            ->addOption('restart', null, Argument::OPTIONAL, '重启任务管理器')
            ->addOption('status', null, Argument::OPTIONAL, '查看任务管理器状态');
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->getOption('start')) {
            return $this->start($output);
        }

        if ($input->getOption('stop')) {
            return $this->stop($output);
        }

        if ($input->getOption('restart')) {
            return $this->restart($output);
        }

        if ($input->getOption('status')) {
            return $this->status($output);
        }

        // 默认行为：持续运行任务管理器
        $this->runForever($output);
    }

    /**
     * 启动任务管理器
     */
    protected function start(Output $output)
    {
        if (file_exists($this->pidFile)) {
            $output->writeln("任务管理器已经在运行！");
            return;
        }

        // 创建子进程以保持后台运行
        if (pcntl_fork()) {
            // 父进程退出
            exit(0);
        }

        // 子进程继续运行
        $pid = getmypid();
        file_put_contents($this->pidFile, $pid);
        $output->writeln("任务管理器已启动，PID: $pid");

        // 开始任务调度
        $this->runForever($output);
    }

    /**
     * 停止任务管理器
     */
    protected function stop(Output $output): void
    {
        if (!file_exists($this->pidFile)) {
            $output->writeln("任务管理器未运行！");
            return;
        }

        $pid = (int)file_get_contents($this->pidFile);

        if ($pid && posix_kill($pid, SIGTERM)) {
            unlink($this->pidFile);
            $output->writeln("任务管理器已成功停止！");
        } else {
            $output->writeln("停止任务管理器失败，可能进程已不存在！");
        }
    }

    /**
     * 重启任务管理器
     */
    protected function restart(Output $output)
    {
        $this->stop($output);
        $this->start($output);
    }

    /**
     * 查看任务管理器状态
     */
    protected function status(Output $output)
    {
        if (!file_exists($this->pidFile)) {
            $output->writeln("任务管理器未运行！");
            return;
        }

        $pid = (int)file_get_contents($this->pidFile);

        if ($pid && posix_kill($pid, 0)) {
            $output->writeln("任务管理器正在运行，PID: $pid");
        } else {
            $output->writeln("任务管理器状态未知，可能已崩溃或终止！");
        }
    }

    /**
     * 持续运行任务管理器
     */
    protected function runForever(Output $output)
    {
        $output->writeln("任务管理器正在持续运行...");

        while (true) {
            try {
                $this->processTasks();
                sleep(1); // 每隔1秒检查一次任务
            } catch (\Exception $e) {
                $output->writeln("任务管理器发生错误：" . $e->getMessage());
            }
        }
    }

    /**
     * 处理任务队列
     */
    protected function processTasks(): void
    {
        // 获取当前时间的时间戳
        $currentTime = time();

        // 查询待执行的任务
        $tasks = Db::name('system_tasks')
            ->where('status', 'pending')
            ->where(function ($query) use ($currentTime) {
                $query->where('next_run_time', '<=', $currentTime)
                    ->orWhereNull('next_run_time');
            })
            ->select();
        foreach ($tasks as $task) {
            $this->executeTask($task);
        }
    }

    /**
     * 执行单个任务
     */
    protected function executeTask($task)
    {
        // 更新任务状态为运行中
        Db::name('system_tasks')->where('id', $task['id'])->update([
            'status' => 'running',
            'last_run_time' => time(),
            'update_time' => time(),
        ]);

        try {
            // 根据任务类型执行命令或类
            if (strpos($task['command'], ':') !== false) {
                // 如果是 ThinkPHP 命令行任务
                system("php think {$task['command']}");
            } else {
                // 如果是任务类
                $class = $task['command'];
                (new $class())->run();
            }

            // 更新任务状态为完成
            Db::name('system_tasks')->where('id', $task['id'])->update([
                'status' => 'completed',
                'update_time' => time(),
            ]);

            // 计算下次执行时间
            $this->calculateNextRunTime($task);
        } catch (\Exception $e) {
            // 更新任务状态为失败
            Db::name('system_tasks')->where('id', $task['id'])->update([
                'status' => 'failed',
                'update_time' => time(),
            ]);
        }
    }

    /**
     * 计算下次执行时间
     */
    protected function calculateNextRunTime($task)
    {
        if ($task['type'] === 'interval' && $task['interval_seconds']) {
            // 间隔任务：根据间隔时间计算下次执行时间
            $nextRunTime = time() + $task['interval_seconds'];
            Db::name('system_tasks')->where('id', $task['id'])->update([
                'next_run_time' => $nextRunTime,
                'status' => 'pending',
                'update_time' => time(),
            ]);
        } elseif ($task['type'] === 'cron' && $task['cron_expression']) {
            // Cron任务：解析Cron表达式并计算下次执行时间
            $cron = new \Cron\CronExpression($task['cron_expression']);
            $nextRunTime = $cron->getNextRunDate()->getTimestamp();
            Db::name('system_tasks')->where('id', $task['id'])->update([
                'next_run_time' => $nextRunTime,
                'status' => 'pending',
                'update_time' => time(),
            ]);
        }
    }
}
