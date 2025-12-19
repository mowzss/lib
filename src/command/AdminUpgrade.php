<?php

namespace mowzs\lib\command;

use app\common\util\SqlExecutor;
use app\logic\system\UpgradeLogic;
use app\model\system\SystemUpgradeLog;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class AdminUpgrade extends Command
{
    protected function configure(): void
    {
        $this->setName('admin:upgrade')
            ->setDescription('执行相关数据表的升级操作，包括但不限于数据库结构更新、默认数据添加等。')
            ->setHelp(
                "该命令用于对系统中的模块进行升级。\n" .
                "它可以自动检测并应用必要的数据库结构变更，同时可以添加或修改默认数据。\n" .
                "使用方法:\n" .
                "  php think admin:upgrade\n" .
                "选项:\n" .
                "  无特定选项，直接运行即可执行升级过程。"
            );
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     * @throws \think\Exception
     */
    protected function execute(Input $input, Output $output): int
    {
        if (!$this->app->config->get('happy.installed', false)) {
            $output->writeln("<error>系统未安装，请先安装系统后再执行此命令。</error>");
            return 0;
        }

        $output->writeln("<info>开始执行管理员模块升级...</info>");

        $files = UpgradeLogic::instance()->getUpgradeFiles(); // 已在内部排序

        foreach ($files as $module => $moduleFiles) {
            foreach ($moduleFiles as $file) {
                if (UpgradeLogic::instance()->isUpgrade($module, $file['filename'])) {
                    $output->writeln("<comment>升级文件 {$file['filename']} 已升级，跳过</comment>");
                    continue;
                }

                $className = str_replace('.php', '', $file['filename']);
                $class = "\\app\common\upgrade\\{$module}\\{$className}";

                if (!class_exists($class)) {
                    // 处理 SQL 文件
                    $sqlFilePath = DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $file['filename'];
                    try {
                        $sqlExecutor = new SqlExecutor();
                        $sqlExecutor->execute($sqlFilePath, 'update');
                        $output->writeln("<info>执行SQL文件成功: {$sqlFilePath}</info>");
                    } catch (\Exception $e) {
                        $output->writeln("<error>执行SQL文件失败: {$e->getMessage()}</error>");
                        throw new \Exception('执行SQL文件失败: ' . $e->getMessage());
                    }
                } else {
                    // 处理 PHP 升级类
                    $output->writeln("类 {$file['filename']} 开始执行");
                    try {
                        $instance = app($class);
                        if (method_exists($instance, 'run')) {
                            $instance->run();
                            $output->writeln("<info>执行类 {$file['filename']} 成功</info>");
                        } else {
                            $output->writeln("<error>类 {$file['filename']} 没有 run 方法</error>");
                            throw new \Exception("类 {$file['filename']} 没有 run 方法");
                        }
                    } catch (\Exception $e) {
                        $output->writeln("<error>运行升级类失败: {$e->getMessage()}</error>");
                        throw new \Exception('运行升级类失败: ' . $e->getMessage());
                    }
                }

                SystemUpgradeLog::create([
                    'module' => $module,
                    'filename' => $file['filename'],
                    'create_time' => time(),
                ]);
                $output->writeln("<info>升级文件 {$file['filename']} 记录完成</info>");
            }
        }

        $output->writeln("<info>✅ 管理员模块升级完成！</info>");
        return 0;
    }
}
