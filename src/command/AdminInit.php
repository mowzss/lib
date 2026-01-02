<?php
declare(strict_types=1);

namespace mowzs\lib\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

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
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
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
            try {
                if (empty(sys_config('static_upload')) && sys_config('static_upload') != 'local') {
                    $commands[] = 'cloud:upload-static';
                }
            } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
                $commands[] = [];
            }
        }

        foreach ($commands as $commandName) {
            $output->writeln("Running <info>$commandName</info>...");
            $commandOutput = $this->app->console->call($commandName)->fetch();
            $output->writeln($commandOutput);
        }
        return 0;
    }
}
