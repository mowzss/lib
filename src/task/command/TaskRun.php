<?php

namespace mowzs\lib\task\command;

use Carbon\Carbon;
use mowzs\lib\task\event\TaskFailed;
use mowzs\lib\task\event\TaskProcessed;
use mowzs\lib\task\event\TaskSkipped;
use mowzs\lib\task\Scheduler;
use think\console\Command;
use think\exception\Handle;

class TaskRun extends Command
{
    /** @var Carbon */
    protected Carbon $startedAt;

    protected function configure()
    {
        $this->startedAt = Carbon::now();
        $this->setName('task:run');
    }

    /**
     * @param Scheduler $scheduler
     * @return void
     */
    public function handle(Scheduler $scheduler): void
    {
        $this->listenForEvents();
        $this->output->info('task run');
        $scheduler->run();
    }

    /**
     * 注册事件
     */
    protected function listenForEvents(): void
    {
        // 任务开始
        $this->app->event->listen(TaskProcessed::class, function (TaskProcessed $event) {
            $this->output->info("任务 {$event->getName()} 已于 " . Carbon::now()->toDateTimeString() . " 执行完毕");
            $this->app->log->channel('task')->info("任务 {$event->getName()} 已于 " . Carbon::now()->toDateTimeString() . " 执行完毕");
        });

        // 任务跳过
        $this->app->event->listen(TaskSkipped::class, function (TaskSkipped $event) {
            $this->output->info("任务 {$event->getName()} 已于 " . Carbon::now()->toDateTimeString() . " 跳过");
            $this->app->log->channel('task')->error("任务 {$event->getName()} 已于 " . Carbon::now()->toDateTimeString() . " 跳过");
        });

        // 任务失败
        $this->app->event->listen(TaskFailed::class, function (TaskFailed $event) {
            $this->output->error("任务 {$event->getName()} 于 " . Carbon::now()->toDateTimeString() . " 执行失败");
            $this->app->log->channel('task')->error("任务 {$event->getName()} 于 " . Carbon::now()->toDateTimeString() . " 执行失败");

            /** @var Handle $handle */
            $handle = $this->app->make(Handle::class);

            // 输出异常信息到控制台
            $handle->renderForConsole($this->output, $event->exception);

            // 上报异常
            $handle->report($event->exception);
        });
    }

}
