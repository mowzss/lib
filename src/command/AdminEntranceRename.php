<?php

namespace mowzs\lib\command;

use think\console\Command;
use think\Exception;

class AdminEntranceRename extends Command
{
    protected function configure(): void
    {
        // 设置命令名称、描述和帮助信息
        $this->setName('admin:entrance')
            ->setDescription('重命名管理入口文件名')
            ->setHelp('该命令用于重命名位于public目录下的管理入口文件。默认文件名为admin.php。');
    }

    /**
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @return void
     * @throws Exception
     */
    protected function execute(\think\console\Input $input, \think\console\Output $output): void
    {
        if (!$this->app->config->get('happy.installed', false)) {
            $output->writeln("系统未安装，请先安装系统后再执行此命令。");
            return;
        }
        // 获取配置文件中的管理入口文件名，默认为 'admin.php'
        $newFileName = $this->app->config->get('happy.admin_entrance', 'admin.php');
        $publicPath = $this->app->getRootPath() . 'public' . DIRECTORY_SEPARATOR;

        // 默认文件路径
        $defaultFilePath = $publicPath . 'admin.php';

        if ($newFileName === 'admin.php') {
            $output->writeln("目标文件名与默认文件名相同，无需处理。");
            return;
        }

        $newFilePath = $publicPath . $newFileName;

        // 如果新文件名对应的文件已经存在，则删除它
        if (file_exists($newFilePath)) {
            if (!unlink($newFilePath)) {
                throw new Exception("无法删除已存在的文件：{$newFilePath}");
            }
            $output->writeln("已删除旧的目标文件：{$newFilePath}");
        }

        // 重命名默认文件到新的文件名
        if (!rename($defaultFilePath, $newFilePath)) {
            throw new Exception("无法重命名文件从 {$defaultFilePath} 到 {$newFilePath}");
        }

        $output->writeln("成功将管理入口文件重命名为：{$newFileName}");
    }
}
