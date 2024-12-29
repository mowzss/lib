<?php

namespace mowzs\lib\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use function copy;
use function file_exists;
use function is_dir;
use function is_file;
use function json_decode;
use function mkdir;
use function rmdir;
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

        foreach ($packages as $package) {
            if ($package['type'] !== 'happy-module') {
                continue; // 跳过非 happy-module 类型的包
            }

            $extra = $package['extra'] ?? [];

            // 检查包的 composer.json 是否包含 'module' 配置项
            if (isset($extra['module'])) {
                $packageName = $package['name'];
                $output->writeln("Processing package: <info>$packageName</info>");
                // 处理 make 节点（强制替换）
                if (isset($extra['module']['make'])) {
                    $this->processPaths($extra['module']['make'], true, $output, $packageName);
                }
                // 处理 copy 节点（复制，目标路径已存在则跳过）
                if (isset($extra['module']['copy'])) {
                    $this->processPaths($extra['module']['copy'], $force, $output, $packageName);
                }
                // 处理 del 节点（删除包的内容）
                if (isset($extra['module']['del']) && $extra['module']['del'] === true) {
                    $output->writeln("Detected 'del' node in package: <info>$packageName</info>");
                    $this->deletePackageContent($packageName, $output);
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

            if (is_dir($sourceFullPath)) {
                // 处理目录
                $this->processDirectory($sourceFullPath, $targetFullPath, $forceReplace, $output);
            } else {
                // 处理文件
                $this->processFile($sourceFullPath, $targetFullPath, $forceReplace, $output);
            }
        }
    }

    /**
     * 处理文件（复制或替换）
     *
     * @param string $sourceFullPath 源文件路径
     * @param string $targetFullPath 目标文件路径
     * @param bool $forceReplace 是否强制替换
     * @param Output $output 输出对象
     */
    protected function processFile(string $sourceFullPath, string $targetFullPath, bool $forceReplace, Output $output)
    {
        if (file_exists($targetFullPath)) {
            if ($forceReplace) {
                unlink($targetFullPath); // 删除目标文件
                copy($sourceFullPath, $targetFullPath);
                $output->writeln("Replaced file at '$targetFullPath'.");
            } else {
                $output->writeln("Warning: The file '$targetFullPath' already exists and will be skipped.");
            }
        } else {
            copy($sourceFullPath, $targetFullPath);
            $output->writeln("Copied file to '$targetFullPath'.");
        }
    }

    /**
     * 处理目录（递归复制或替换）
     *
     * @param string $sourceFullPath 源目录路径
     * @param string $targetFullPath 目标目录路径
     * @param bool $forceReplace 是否强制替换
     * @param Output $output 输出对象
     */
    protected function processDirectory(string $sourceFullPath, string $targetFullPath, bool $forceReplace, Output $output)
    {
        if (!file_exists($targetFullPath)) {
            mkdir($targetFullPath, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceFullPath));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($sourceFullPath) + 1);
            $targetFile = $targetFullPath . DIRECTORY_SEPARATOR . $relativePath;

            if (file_exists($targetFile)) {
                if ($forceReplace) {
                    unlink($targetFile); // 删除目标文件
                    copy($file->getPathname(), $targetFile);
                    $output->writeln("Replaced file at '$targetFile'.");
                } else {
                    $output->writeln("Warning: The file '$targetFile' already exists and will be skipped.");
                }
            } else {
                copy($file->getPathname(), $targetFile);
                $output->writeln("Copied file to '$targetFile'.");
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
            $output->writeln("Warning: Package path '$packagePath' does not exist.");
            return;
        }

        if (!is_dir($packagePath)) {
            $output->writeln("Error: Path '$packagePath' is not a directory.");
            return;
        }

        $output->writeln("Deleting content of package: <info>$packageName</info>");

        // 递归删除包的内容
        if ($this->recursiveDelete($packagePath, $output)) {
            $output->writeln("Successfully deleted content of package: <info>$packageName</info>");
        } else {
            $output->writeln("<error>Failed to delete content of package: $packageName</error>");
        }
    }

    /**
     * 递归删除目录及其内容
     *
     * @param string $path 目录路径
     * @param Output $output 输出对象
     * @return bool 是否删除成功
     */
    protected function recursiveDelete(string $path, Output $output): bool
    {
        if (!file_exists($path)) {
            $output->writeln("Warning: Path '$path' does not exist.");
            return true; // 如果路径不存在，认为删除成功
        }

        if (!is_dir($path)) {
            // 如果是文件，直接删除
            if (unlink($path)) {
                $output->writeln("Deleted file: '$path'");
                return true;
            } else {
                $output->writeln("<error>Failed to delete file: $path</error>");
                return false;
            }
        }

        // 获取目录中的所有文件和子目录
        $objects = scandir($path);
        foreach ($objects as $object) {
            if ($object == '.' || $object == '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $object;

            // 递归删除子目录或文件
            if (is_dir($fullPath)) {
                if (!$this->recursiveDelete($fullPath, $output)) {
                    return false; // 如果删除子目录失败，返回 false
                }
            } else {
                if (!unlink($fullPath)) {
                    $output->writeln("<error>Failed to delete file: $fullPath</error>");
                    return false;
                }
                $output->writeln("Deleted file: '$fullPath'");
            }
        }

        // 删除空目录
        if (rmdir($path)) {
            $output->writeln("Deleted directory: '$path'");
            return true;
        } else {
            $output->writeln("<error>Failed to delete directory: $path</error>");
            return false;
        }
    }
}
