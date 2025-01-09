<?php

namespace mowzs\lib\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class AdminInit extends Command
{
    protected function configure()
    {
        // 设置命令名称、描述和帮助信息
        $this->setName('admin:init')
            ->setDescription('Initialize the application with custom module configurations.')
            ->setHelp('This command runs a series of initialization steps including service discovery, vendor publishing, and module configuration.');
    }


    protected function execute(Input $input, Output $output)
    {
        // 定义要执行的命令列表
        $commands = [
            'service:discover',
            'vendor:publish',
        ];

        foreach ($commands as $commandName) {
            $output->writeln("Running <info>$commandName</info>...");
            $commandOutput = $this->app->console->call($commandName)->fetch();
            $output->writeln($commandOutput);
        }

        $output->writeln('<comment>All initialization steps have been completed.</comment>');
        return 0;
    }
}
