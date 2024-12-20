<?php

namespace mowzs\lib\command;

use Composer\Factory;
use Composer\IO\NullIO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use function base_path;
use function config_path;
use function copy;
use function file_exists;
use function is_dir;
use function rmdir;
use function unlink;

class ModuleInit extends Command
{
    protected function configure()
    {
        // 设置命令名称、描述和帮助信息
        $this->setName('admin:moduleInit')
            ->setDescription('Initialize custom module configurations from installed happy-module packages.')
            ->setHelp('This command initializes custom module configurations from the composer.json files of installed packages with type "happy-module".');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('Starting module initialization...');

        // 获取 Composer 的全局配置和已安装的包列表
        $composerFactory = new Factory();
        $composerConfig = $composerFactory->createConfig();
        $composerIo = new NullIO();
        $composer = $composerFactory->createComposer($composerIo, $composerConfig);
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();

        // 遍历所有已安装的包，只处理 type 为 'happy-module' 的包
        foreach ($installedRepo->getPackages() as $package) {
            if ($package->getType() !== 'happy-module') {
                continue; // 跳过非 happy-module 类型的包
            }

            $extra = $package->getExtra();

            // 检查包的 composer.json 是否包含 'module' 配置项
            if (isset($extra['module'])) {
                $packageName = $package->getName();
                $output->writeln("Processing package: <info>$packageName</info>");

                // 处理 make 节点（强制替换）
                if (isset($extra['module']['make'])) {
                    $this->processPaths($extra['module']['make'], true, $output, $packageName);
                }

                // 处理 copy 节点（复制，目标路径已存在则跳过）
                if (isset($extra['module']['copy'])) {
                    $this->processPaths($extra['module']['copy'], false, $output, $packageName);
                }

                // 处理 del 节点（删除包的内容）
                if (isset($extra['module']['del']) && $extra['module']['del'] === true) {
                    $this->deletePackageContent($packageName, $output);
                }
            }
        }

        $output->writeln('Module initialization completed.');
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
            $sourceFullPath = base_path() . '/vendor/' . $packageName . '/' . $sourcePath;
            $targetFullPath = config_path() . $targetKey . '.php';

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

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceFullPath));
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
        $packagePath = base_path() . '/vendor/' . $packageName;

        if (file_exists($packagePath)) {
            $this->recursiveDelete($packagePath, $output);
            $output->writeln("Deleted content of package: <info>$packageName</info>");
        } else {
            $output->writeln("Warning: Package path '$packagePath' does not exist.");
        }
    }

    /**
     * 递归删除目录及其内容
     *
     * @param string $path 目录路径
     * @param Output $output 输出对象
     */
    protected function recursiveDelete(string $path, Output $output)
    {
        if (is_dir($path)) {
            $objects = scandir($path);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($path . DIRECTORY_SEPARATOR . $object)) {
                        $this->recursiveDelete($path . DIRECTORY_SEPARATOR . $object, $output);
                    } else {
                        unlink($path . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}
