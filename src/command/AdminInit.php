<?php
declare(strict_types=1);

namespace mowzs\lib\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 系统初始化命令
 */
class AdminInit extends Command
{
    /**
     * 配置命令
     * @return void
     */
    protected function configure(): void
    {
        // 设置命令名称、描述和帮助信息
        $this->setName('admin:init')
            ->setDescription('Initialize the application with custom module configurations.')
            ->setHelp('This command runs a series of initialization steps including service discovery, vendor publishing, and module configuration.');
    }

    /**
     * 执行命令
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        // 定义要执行的命令列表
        $commands = [
            'vendor:publish',
            'admin:moduleInit',
            'admin:favicon',
            'optimize:route',
            'optimize:schema',
        ];
        if ($this->app->config->get('happy.installed', false)) {
            $commands[] = 'admin:upgrade';
            $commands[] = 'admin:entrance';
        }
        foreach ($commands as $commandName) {
            $output->writeln("Running <info>$commandName</info>...");
            $commandOutput = $this->app->console->call($commandName)->fetch();
            $output->writeln($commandOutput);
        }

        $output->writeln('<comment>All initialization steps have been completed.</comment>');
        return 0;
    }
}
