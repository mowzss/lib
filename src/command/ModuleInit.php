<?php

namespace mowzs\lib\command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use function copy;
use function file_exists;
use function is_dir;
use function is_file;
use function json_decode;
use function md5_file;
use function mkdir;
use function rtrim;
use function scandir;
use function unlink;

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
            ->setHelp('This command initializes custom module configurations from the composer.json files of installed packages with type "happy-module".')
            ->addOption('force', null, Option::VALUE_NONE, 'Force replace existing files');
    }

    /**
     * 执行
     * @param Input $input
     * @param Output $output
     * @return int
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

        $force = $input->getOption('force');
        $processedPackages = array_filter($packages, function ($package) {
            return isset($package['type']) && $package['type'] === 'happy-module' && isset($package['extra']['module']);
        });

        foreach ($processedPackages as $package) {
            $extra = $package['extra'] ?? [];
            $packageName = $package['name'];
            $installPath = $this->app->getRootPath() . 'vendor/' . $packageName;

            // 输出开始信息
            $output->writeln("Starting processing package: <info>$packageName</info>");

            // 初始化（若文件存在不进行操作）
            if (isset($extra['module']['init'])) {
                $this->processPaths($extra['module']['init'], false, $output, $installPath, $output);
            }

            // 复制替换（无论是否存在都进行替换）
            if (isset($extra['module']['copy'])) {
                $this->processPaths($extra['module']['copy'], $force, $output, $installPath, $output);
            }

            // 清理当前库的所有文件及信息
            if (isset($extra['module']['clear']) && $extra['module']['clear'] === true) {
                $this->deletePackageContent($packageName, $output);
            }

            // 输出结束信息
            $output->writeln("Finished processing package: <info>$packageName</info>");
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
     * @param string $installPath 安装路径
     * @param Output $io 输出接口
     */
    protected function processPaths(array $paths, bool $forceReplace, Output $output, string $installPath, Output $io)
    {
        foreach ($paths as $source => $target) {
            // 是否为绝对复制模式
            $absoluteCopy = $target[0] === '!';
            if ($absoluteCopy) {
                $target = substr($target, 1);
            }

            // 源文件位置，若不存在直接跳过
            $sourceFullPath = $installPath . DIRECTORY_SEPARATOR . $source;
            if (!file_exists($sourceFullPath)) {
                $output->writeln("Warning: Source path '$sourceFullPath' does not exist.");
                continue;
            }

            // 检查目标文件，若已经存在并且内容相同则跳过
            if (is_file($sourceFullPath) && is_file($target) && !$forceReplace && md5_file($sourceFullPath) === md5_file($target)) {
                $output->writeln("Skipped copying <info>{$source}</info> to <info>{$target}</info> (files are identical).");
                continue;
            }

            // 如果目标目录或其上级目录下存在 ignore 文件则跳过复制
            if (file_exists(dirname($target) . '/lock') || file_exists(rtrim($target, '\\/') . "/lock")) {
                $output->writeln("Skipped copying <info>{$source}</info> to <info>{$target}</info> (ignore file exists).");
                continue;
            }

            // 绝对复制时需要先删再写入
            if ($absoluteCopy && file_exists($target)) {
                if (is_file($target)) {
                    unlink($target);
                } elseif (is_dir($target)) {
                    $this->removeDirectoryPhp($target);
                }
            }

            // 确保目标目录存在
            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                    $output->writeln("Error: Failed to create target directory: <info>$targetDir</info>.");
                    continue;
                }
            }

            // 执行复制操作，将原文件或目录复制到目标位置
            if (is_dir($sourceFullPath)) {
                $this->copyDirectory($sourceFullPath, $target, $forceReplace, $output);
            } elseif (is_file($sourceFullPath)) {
                if (copy($sourceFullPath, $target)) {
                    $output->writeln("Copied <info>{$source}</info> to <info>{$target}</info>.");
                } else {
                    $output->writeln("<error>Error: Failed to copy file to <info>{$target}</info>.</error>");
                }
            }
        }
    }

    /**
     * 复制目录及其内容
     *
     * @param string $source 源目录路径
     * @param string $target 目标目录路径
     * @param bool $forceReplace 是否强制替换
     * @param Output $output 输出对象
     */
    protected function copyDirectory(string $source, string $target, bool $forceReplace, Output $output)
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $relativePath = substr($file->getPathname(), strlen($source) + 1);
            $targetFile = $target . DIRECTORY_SEPARATOR . $relativePath;

            // 确保目标文件夹存在
            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                    $output->writeln("Error: Failed to create target directory: <info>$targetDir</info>.");
                    continue;
                }
            }

            // 检查是否跳过复制
            if (file_exists($targetFile) && !$forceReplace && md5_file($file->getPathname()) === md5_file($targetFile)) {
                $output->writeln("Skipped copying <info>{$relativePath}</info> to <info>{$targetFile}</info> (files are identical).");
                continue;
            }

            if (copy($file->getPathname(), $targetFile)) {
                $output->writeln("Copied <info>{$relativePath}</info> to <info>{$targetFile}</info>.");
            } else {
                $output->writeln("<error>Error: Failed to copy file to <info>{$targetFile}</info>.</error>");
            }
        }
    }

    /**
     * 删除包的内容
     *
     * @param string $packageName 包名
     * @param Output $output 输出对象
     */
    protected function deletePackageContent(string $packageName, Output $output)
    {
        $packagePath = $this->app->getRootPath() . 'vendor/' . $packageName;
        if (!file_exists($packagePath)) {
            $output->writeln("Warning: Package path <info>'$packagePath'</info> does not exist.");
            return;
        }
        if (!is_dir($packagePath)) {
            $output->writeln("Error: Path <info>'$packagePath'</info> is not a directory.");
            return;
        }

        try {
            $this->removeDirectoryPhp($packagePath);
            $output->writeln("Successfully deleted content of package: <info>$packageName</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to delete content of package: $packageName</error>");
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
        }
    }

    /**
     * 递归删除目录
     *
     * @param string $directory 目录路径
     */
    protected function removeDirectoryPhp(string $directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectoryPhp($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
