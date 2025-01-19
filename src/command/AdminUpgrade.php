<?php

namespace mowzs\lib\command;

use app\common\util\SqlExecutor;
use app\model\system\SystemUpgradeLog;
use app\service\system\UpgradeService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class AdminUpgrade extends Command
{
    protected function configure()
    {
        // 设置命令名称、描述和帮助信息
        $this->setName('admin:upgrade')
            ->setDescription('执行管理员相关数据表的升级操作，包括但不限于数据库结构更新、默认数据添加等。')
            ->setHelp(
                "该命令用于对系统中的管理员模块进行升级。\n" .
                "它可以自动检测并应用必要的数据库结构变更，同时可以添加或修改默认数据。\n" .
                "使用方法:\n" .
                "  php think admin:upgrade\n" .
                "选项:\n" .
                "  无特定选项，直接运行即可执行升级过程。"
            );
    }

    /**
     * 执行命令逻辑
     *
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @throws \Exception
     */
    protected function execute(Input $input, Output $output): void
    {
        // 示例输出信息到控制台
        $output->writeln("开始执行管理员模块升级...");
        $files = UpgradeService::instance()->getUpgradeFiles();
        // 执行install_files中的SQL文件及类的run方法
        foreach ($files as $module => $moduleFiles) {
            foreach ($moduleFiles as $fileOrClass) {
                if (is_string($fileOrClass) && !class_exists($fileOrClass)) {
                    // 假设是SQL文件
                    $sqlFilePath = DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $fileOrClass;

                    try {
                        // 创建SqlExecutor实例并执行SQL文件
                        $sqlExecutor = new SqlExecutor();
                        $sqlExecutor->execute($sqlFilePath);
                        $output->writeln("执行SQL文件成功: " . $sqlFilePath);
                    } catch (\Exception $e) {
                        $output->writeln("执行SQL文件失败: " . $e->getMessage());
                        throw new \Exception('执行SQL文件失败: ' . $e->getMessage());
                    }
                } elseif (is_string($fileOrClass) && class_exists($fileOrClass)) {
                    // 假设是类名
                    try {
                        // 实例化类并调用run方法
                        $instance = app($fileOrClass);
                        if (method_exists($instance, 'run')) {
                            $instance->run();
                            $output->writeln("执行类 {$fileOrClass} 成功");
                        } else {
                            $output->writeln("类 {$fileOrClass} 没有 run 方法");
                            throw new \Exception("类 {$fileOrClass} 没有 run 方法");
                        }
                    } catch (\Exception $e) {
                        $output->writeln("运行安装类失败: " . $e->getMessage());
                        throw new \Exception('运行安装类失败: ' . $e->getMessage());
                    }
                }
                SystemUpgradeLog::create(['module' => $module, 'filename' => $fileOrClass,]);
                $output->writeln("升级文件 {$fileOrClass} 成功");
            }
        }


        $output->writeln("管理员模块升级完成！");
    }
}
