<?php

namespace mowzs\lib\command;

use League\Flysystem\FilesystemException;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Filesystem;

class ModuleInit extends Command
{
    /**
     * 配置命令
     * @return void
     */
    protected function configure()
    {
        $this->setName('admin:moduleInit')
            ->setDescription('Initialize custom module configurations from installed happy-module packages.')
            ->setHelp('This command initializes custom module configurations from the composer.json files of installed packages with type "happy-module".');
    }

    /**
     * 执行
     * @param Input $input
     * @param Output $output
     * @return int
     * @throws FilesystemException
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('Starting module initialization...');

        // 检查并读取 installed.json 文件
        $installedJsonPath = $this->app->getRootPath() . 'vendor/composer/installed.json';
        if (!is_file($installedJsonPath)) {
            $output->writeln('<error>File vendor/composer/installed.json not found!</error>');
            return 1; // 返回非零值表示命令执行失败
        }

        $packages = json_decode(@file_get_contents($installedJsonPath), true);
        if (isset($packages['packages'])) {
            $packages = $packages['packages']; // 兼容 Composer 2.0
        } else {
            $packages = [$packages]; // 确保我们总是处理一个数组
        }

        foreach ($packages as $package) {
            if ($package['type'] !== 'happy-module') {
                continue; // 跳过非 happy-module 类型的包
            }

            $extra = $package['extra'] ?? [];

            // 检查包的 composer.json 是否包含 'module' 配置项
            if (isset($extra['module'])) {
                $packageName = $package['name'];
                $output->writeln("Processing package: <info>$packageName</info>");
                // 处理 make 节点（创建目录和文件）
                // Process 'make' node (create directories and files)
                if (isset($extra['module']['make'])) {
                    $output->writeln('<info>Starting to process module[\'make\'] node...</info>');
                    $this->processPaths($extra['module']['make'], true, $output, $packageName);
                    $output->writeln('<info>Completed processing of module[\'make\'] node.</info>');
                }

                // Process 'copy' node (copy files, skip if destination path exists)
                if (isset($extra['module']['copy'])) {
                    $output->writeln('<info>Starting to process module[\'copy\'] node...</info>');
                    $this->processPaths($extra['module']['copy'], false, $output, $packageName);
                    $output->writeln('<info>Completed processing of module[\'copy\'] node.</info>');
                }

                // Process 'del' node (delete package contents)
                if (isset($extra['module']['del']) && $extra['module']['del'] === true) {
                    $output->writeln("Starting cleanup of <info>$packageName</info>");
                    $this->deletePackageContent($packageName, $output);
                    $output->writeln("<info>$packageName</info> cleanup completed");
                }

            }
        }

        $output->writeln('Module initialization completed.');
        return 0; // 返回零值表示命令成功执行
    }

    /**
     * 处理路径（复制或替换）
     *
     * @param array $paths 路径配置
     * @param bool $forceReplace 是否强制替换
     * @param Output $output 输出对象
     * @param string $packageName 包名
     * @throws FilesystemException
     */
    protected function processPaths(array $paths, bool $forceReplace, Output $output, string $packageName)
    {
        foreach ($paths as $targetKey => $sourcePath) {
            $sourceFullPath = $this->app->getRootPath() . 'vendor/' . $packageName . '/' . $sourcePath;
            $targetFullPath = $this->app->getRootPath() . $targetKey;

            if (!file_exists($sourceFullPath)) {
                $output->writeln("Error: Source path '$sourceFullPath' does not exist.");
                continue;
            }
            if ($forceReplace) {
                Filesystem::deleteDirectory($targetFullPath);

            }
            Filesystem::copyDirectory($sourceFullPath, $targetFullPath);
            $output->writeln("Marked and copied from {$sourceFullPath} to {$targetFullPath}");
        }
    }

    /**
     * 删除包的内容
     *
     * @param string $packageName 包名
     * @param Output $output 输出对象
     * @throws FilesystemException
     */
    protected function deletePackageContent(string $packageName, Output $output): void
    {
        $packagePath = $this->app->getRootPath() . 'vendor/' . $packageName;
        Filesystem::deleteDirectory($packagePath);
        $output->writeln('Deleted package files.');
    }
}
