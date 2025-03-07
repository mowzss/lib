<?php

namespace mowzs\lib\task\command;

use Symfony\Component\Process\Process;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class TaskSchedule extends Command
{

    protected function configure()
    {
        $this->setName('task:schedule');
    }

    protected function execute(Input $input, Output $output)
    {

        if ('\\' == DIRECTORY_SEPARATOR) {
            $command = 'start /B "' . PHP_BINARY . '" think task:run';
        } else {
            $command = 'nohup "' . PHP_BINARY . '" think task:run >> /dev/null 2>&1 &';
        }

        $process = Process::fromShellCommandline($command);
        $output->info('守护任务启动成功');
        while (true) {
            $process->run();
            $this->output->writeln("{$process->getOutput()}");//输出任务内的打印信息
            sleep(60);
        }
    }
}
