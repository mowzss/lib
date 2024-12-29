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
        $summary = [
            'processed_packages' => 0,
            'copied_files' => 0,
            'replaced_files' => 0,
            'deleted_files' => 0,
        ];
        foreach ($packages as $package) {
            if ($package['type'] !== 'happy-module') {
                continue;
            }
            $extra = $package['extra'] ?? [];
            if (isset($extra['module'])) {
                $packageName = $package['name'];
                $output->writeln("Processing package: <info>$packageName</info>");

                // 处理 make 节点（强制替换）
                if (isset($extra['module']['make'])) {
                    $this->processPaths($extra['module']['make'], true, $output, $packageName, $summary);
                }
                // 处理 copy 节点（复制，目标路径已存在则跳过）
                if (isset($extra['module']['copy'])) {
                    $this->processPaths($extra['module']['copy'], $force, $output, $packageName, $summary);
                }
                // 处理 del 节点（删除包的内容）
                if (isset($extra['module']['del']) && $extra['module']['del'] === true) {
                    $this->deletePackageContent($packageName, $output, $summary);
                }

                $summary['processed_packages']++;
                $output->writeln("Finished processing package: <info>$packageName</info>");
            }
        }
        $output->writeln('Module initialization completed.');
        $output->writeln("Summary: Processed <info>{$summary['processed_packages']}</info> packages, copied <info>{$summary['copied_files']}</info> files, replaced <info>{$summary['replaced_files']}</info> files, deleted <info>{$summary['deleted_files']}</info> files.");
        return 0;
    }

    /**
     * 处理路径（复制或替换）
     *
     * @param array $paths 路径配置
     * @param bool $forceReplace 是否强制替换
     * @param Output $output 输出对象
     * @param string $packageName 包名
     */
    protected function processPaths(array $paths, bool $forceReplace, Output $output, string $packageName, array &$summary): void
    {
        foreach ($paths as $targetKey => $sourcePath) {
            $sourceFullPath = $this->app->getRootPath() . 'vendor/' . $packageName . '/' . $sourcePath;
            $targetFullPath = $this->app->getRootPath() . $targetKey;
            // 检查源文件或目录是否存在
            if (!file_exists($sourceFullPath)) {
                $output->writeln("Error: Source path '$sourceFullPath' does not exist.");
                continue;
            }
            // 确保目标目录存在
            $targetDir = dirname($targetFullPath);
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                    $output->writeln("Error: Failed to create target directory: '$targetDir'.");
                    continue;
                }
            }
            if (is_dir($sourceFullPath)) {
                $this->processDirectory($sourceFullPath, $targetFullPath, $forceReplace, $output, $summary);
            } else {
                $this->processFile($sourceFullPath, $targetFullPath, $forceReplace, $output, $summary);
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
    protected function processFile(string $sourceFullPath, string $targetFullPath, bool $forceReplace, Output $output, array &$summary)
    {
        // 确保目标文件夹存在
        $targetDir = dirname($targetFullPath);
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                $output->writeln("Error: Failed to create target directory: '$targetDir'.");
                return;
            }
        }
        try {
            if (file_exists($targetFullPath)) {
                if ($forceReplace) {
                    unlink($targetFullPath); // 删除目标文件
                    if (copy($sourceFullPath, $targetFullPath)) {
                        $summary['replaced_files']++;
                    } else {
                        throw new \Exception("Failed to copy file to '$targetFullPath'.");
                    }
                }
            } else {
                if (copy($sourceFullPath, $targetFullPath)) {
                    $summary['copied_files']++;
                } else {
                    throw new \Exception("Failed to copy file to '$targetFullPath'.");
                }
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
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
    protected function processDirectory(string $sourceFullPath, string $targetFullPath, bool $forceReplace, Output $output, array &$summary)
    {
        // 确保目标目录存在
        if (!is_dir($targetFullPath)) {
            if (!mkdir($targetFullPath, 0755, true) && !is_dir($targetFullPath)) {
                $output->writeln("Error: Failed to create target directory: '$targetFullPath'.");
                return;
            }
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceFullPath));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($sourceFullPath) + 1);
            $targetFile = $targetFullPath . DIRECTORY_SEPARATOR . $relativePath;

            // ... (确保目标文件夹存在的代码保持不变)

            try {
                if (file_exists($targetFile)) {
                    if ($forceReplace) {
                        unlink($targetFile); // 删除目标文件
                        if (copy($file->getPathname(), $targetFile)) {
                            $summary['replaced_files']++;
                        } else {
                            throw new \Exception("Failed to copy file to '$targetFile'.");
                        }
                    }
                } else {
                    if (copy($file->getPathname(), $targetFile)) {
                        $summary['copied_files']++;
                    } else {
                        throw new \Exception("Failed to copy file to '$targetFile'.");
                    }
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            }
        }
    }

    protected function deletePackageContent(string $packageName, Output $output, array &$summary)
    {
        $packagePath = $this->app->getRootPath() . 'vendor/' . $packageName;

        // 检查路径是否存在且是一个目录
        if (!file_exists($packagePath)) {
            $output->writeln("Notice: Path for package <info>'$packageName'</info> does not exist. Skipping deletion.");
            return;
        }
        if (!is_dir($packagePath)) {
            $output->writeln("Error: Path <info>'$packagePath'</info> is not a directory. Skipping deletion.");
            return;
        }

        // 确认目录不为空（可选）
        $iterator = new \FilesystemIterator($packagePath);
        if (empty(iterator_to_array($iterator))) {
            $output->writeln("Notice: Package directory <info>'$packageName'</info> is empty, nothing to delete.");
            return;
        }

        try {
            $output->writeln("Deleting content of package: <info>'$packageName'</info>");

            if ($this->recursiveDelete($packagePath, $output)) {
                // 计算被删除的文件数量
                $deletedFilesCount = iterator_count(new \RecursiveIteratorIterator(new \FilesystemIterator($packagePath)));
                $summary['deleted_files'] += $deletedFilesCount;
                $output->writeln("Deleted <info>$deletedFilesCount</info> files from package: <info>'$packageName'</info>");
            } else {
                $output->writeln("<error>Failed to delete content of package: '$packageName'</error>");
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error deleting content of package <info>'$packageName'</info>: " . $e->getMessage() . "</error>");
        }
    }

    /**
     * 递归删除目录及其内容
     *
     * @param string $path 目录路径
     * @param Output $output 输出对象
     * @return bool 是否删除成功
     */
    protected function recursiveDelete($path, Output $output)
    {
        if (!file_exists($path)) {
            return true;
        }

        if (is_file($path)) {
            return unlink($path);
        }

        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            $fileCount = iterator_count($iterator);
            $progressStep = max(1, (int)($fileCount / 10)); // 每10%打印一次进度
            $currentFile = 0;

            foreach ($iterator as $file) {
                try {
                    if ($file->isDir()) {
                        rmdir($file->getPathname());
                    } else {
                        unlink($file->getPathname());
                    }
                    $currentFile++;
                    if ($currentFile % $progressStep === 0) {
                        $output->write('.'); // 打印进度点号
                    }
                } catch (\Exception $e) {
                    $output->writeln("\n<error>Error deleting <info>'" . $file->getPathname() . "'</info>: " . $e->getMessage() . "</error>");
                }
            }

            try {
                rmdir($path);
            } catch (\Exception $e) {
                $output->writeln("\n<error>Error removing directory <info>'$path'</info>: " . $e->getMessage() . "</error>");
                return false;
            }

            return true;
        }

        $output->writeln("Error: Path <info>'$path'</info> is neither a file nor a directory.");
        return false;
    }
}
