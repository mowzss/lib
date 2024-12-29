<?php

namespace mowzs\lib\command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Filesystem\Filesystem;
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

        // 创建进度条
        $progress = new ProgressBar($output, count($processedPackages));
        $progress->start();

        foreach ($processedPackages as $package) {
            $extra = $package['extra'] ?? [];
            $packageName = $package['name'];
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
                $this->deletePackageContent($packageName, $output);
            }
            // 更新进度条
            $progress->advance();
        }

        $progress->finish();
        $output->writeln('');
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
        // 计算总路径数
        $totalPaths = count($paths);
        $progress = new ProgressBar($output, $totalPaths);
        $progress->start();

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
                // 处理目录
                $this->processDirectory($sourceFullPath, $targetFullPath, $forceReplace, $output);
            } else {
                // 处理文件
                $this->processFile($sourceFullPath, $targetFullPath, $forceReplace, $output);
            }

            // 处理完成后更新进度条
            $progress->advance();
        }

        $progress->finish();
        $output->writeln('');
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
        try {
            if (file_exists($targetFullPath)) {
                if ($forceReplace) {
                    unlink($targetFullPath); // 删除目标文件
                    if (copy($sourceFullPath, $targetFullPath)) {
                        $output->writeln("Replaced file at '$targetFullPath'.");
                    } else {
                        throw new \Exception("Failed to copy file to '$targetFullPath'.");
                    }
                } else {
                    $output->writeln("Warning: The file '$targetFullPath' already exists and will be skipped.");
                }
            } else {
                if (copy($sourceFullPath, $targetFullPath)) {
                    $output->writeln("Copied file to '$targetFullPath'.");
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
    protected function processDirectory(string $sourceFullPath, string $targetFullPath, bool $forceReplace, Output $output)
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

            // 确保目标文件夹存在
            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                    $output->writeln("Error: Failed to create target directory: '$targetDir'.");
                    continue;
                }
            }

            try {
                if (file_exists($targetFile)) {
                    if ($forceReplace) {
                        unlink($targetFile); // 删除目标文件
                        if (copy($file->getPathname(), $targetFile)) {
                            $output->writeln("Replaced file at '$targetFile'.");
                        } else {
                            throw new \Exception("Failed to copy file to '$targetFile'.");
                        }
                    } else {
                        $output->writeln("Warning: The file '$targetFile' already exists and will be skipped.");
                    }
                } else {
                    if (copy($file->getPathname(), $targetFile)) {
                        $output->writeln("Copied file to '$targetFile'.");
                    } else {
                        throw new \Exception("Failed to copy file to '$targetFile'.");
                    }
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
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

        $fs = new Filesystem();
        try {
            $fs->remove($packagePath);
            $output->writeln("Successfully deleted content of package: <info>$packageName</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to delete content of package: $packageName</error>");
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
        }
    }
}
