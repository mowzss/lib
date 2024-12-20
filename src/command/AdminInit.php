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
        // 执行 service:discover 命令
        $output->writeln('Running <info>service:discover</info>...');

        $exitCode = $this->app->console->call('service:discover', [], $output);
        if ($exitCode != 0) {
            $output->writeln('<error>Failed to run service:discover.</error>');
            return $exitCode;
        }
        $output->writeln('<info>service:discover completed successfully.</info>');

        // 执行 vendor:publish 命令
        $output->writeln('Running <info>vendor:publish</info>...');
        $exitCode = $this->app->console->call('vendor:publish', [], $output);
        if ($exitCode != 0) {
            $output->writeln('<error>Failed to run vendor:publish.</error>');
            return $exitCode;
        }
        $output->writeln('<info>vendor:publish completed successfully.</info>');

        // 执行 admin:moduleInit 命令
        $output->writeln('Running <info>admin:moduleInit</info>...');
        $exitCode = $this->app->console->call('admin:moduleInit', [], $output);
        if ($exitCode != 0) {
            $output->writeln('<error>Failed to run admin:moduleInit.</error>');
            return $exitCode;
        }
        $output->writeln('<info>admin:moduleInit completed successfully.</info>');

        $output->writeln('<comment>All initialization steps have been completed.</comment>');
        return 0;
    }
}
