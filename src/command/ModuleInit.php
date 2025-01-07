<?php

namespace mowzs\lib\command;

use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use think\console\Command;
use think\console\Input;
use think\console\Output;

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

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('Starting module initialization...');

        try {
            $packages = $this->getInstalledPackages();
        } catch (\Exception $e) {
            $output->writeln("<error>An error occurred while reading installed packages: {$e->getMessage()}</error>");
            return 1; // 返回非零值表示命令执行失败
        }

        foreach ($packages as $package) {
            // 检查 package 是否是数组，并且包含 'name' 和 'type' 键
            if (!is_array($package) || !isset($package['name']) || !isset($package['type'])) {
                $output->writeln("<warning>Skipping invalid package: Missing required keys.</warning>");
                continue;
            }

            // 只处理类型为 'happy-module' 的包
            if ($package['type'] !== 'happy-module') {
                continue;
            }

            // 检查 extra 中是否包含 'module' 配置项
            $extra = $package['extra'] ?? [];

            if (!isset($extra['module'])) {
                $output->writeln("Package <info>{$package['name']}</info> does not contain a 'module' configuration in its extra section. Skipping.");
                continue;
            }

            $packageName = $package['name'];
            $output->writeln("Processing package: <info>$packageName</info>");

            // 处理 make 节点（创建目录和文件）
            if (isset($extra['module']['make'])) {
                $output->writeln('<info>Starting to process module[\'make\'] node...</info>');
                $this->processPaths($extra['module']['make'], true, $output, $packageName);
                $output->writeln('<info>Completed processing of module[\'make\'] node.</info>');
            }

            // 处理 copy 节点（复制文件，跳过已存在的目标路径）
            if (isset($extra['module']['copy'])) {
                $output->writeln('<info>Starting to process module[\'copy\'] node...</info>');
                $this->processPaths($extra['module']['copy'], false, $output, $packageName);
                $output->writeln('<info>Completed processing of module[\'copy\'] node.</info>');
            }

            // 处理 del 节点（删除包内容）
            if (isset($extra['module']['del']) && $extra['module']['del'] === true) {
                $output->writeln("Starting cleanup of <info>$packageName</info>");
                $this->deletePackageContent($packageName, $output);
                $output->writeln("<info>$packageName</info> cleanup completed");
            }
        }

        $output->writeln('Module initialization completed.');
        return 0; // 返回零值表示命令成功执行
    }

    /**
     * 获取已安装的 Composer 包
     *
     * @return array
     * @throws \RuntimeException
     */
    protected function getInstalledPackages()
    {
        $installedJsonPath = $this->app->getRootPath() . 'vendor/composer/installed.json';

        if (!is_file($installedJsonPath)) {
            throw new \RuntimeException('The file vendor/composer/installed.json was not found.');
        }

        // 读取并解析 installed.json 文件
        $jsonContent = @file_get_contents($installedJsonPath);
        if ($jsonContent === false) {
            throw new \RuntimeException('Failed to read the contents of vendor/composer/installed.json.');
        }

        $packagesData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to decode vendor/composer/installed.json: ' . json_last_error_msg());
        }

        // 检查是否是 Composer 2.x 格式的 installed.json
        if (isset($packagesData['packages'])) {
            return $packagesData['packages']; // Composer 2.x 格式
        } elseif (is_array($packagesData)) {
            return $packagesData; // Composer 1.x 格式
        } else {
            throw new \RuntimeException('Unexpected format in vendor/composer/installed.json. Expected an array or a "packages" key.');
        }
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
        $filesystem = new SymfonyFilesystem();

        foreach ($paths as $targetKey => $sourcePath) {
            $sourceFullPath = $this->app->getRootPath() . 'vendor/' . $packageName . '/' . $sourcePath;
            $targetFullPath = $this->app->getRootPath() . $targetKey;

            if (!file_exists($sourceFullPath)) {
                $output->writeln("Error: Source path '$sourceFullPath' does not exist.");
                continue;
            }

            // 如果强制替换，则先删除目标目录
            if ($forceReplace && is_dir($targetFullPath)) {
                $filesystem->remove($targetFullPath);
            }

            // 复制目录或文件
            if (is_dir($sourceFullPath)) {
                $filesystem->mirror($sourceFullPath, $targetFullPath);
                $output->writeln("Mirrored directory from <info>{$sourceFullPath}</info> to <info>{$targetFullPath}</info>");
            } else {
                $filesystem->copy($sourceFullPath, $targetFullPath, true);
                $output->writeln("Copied file from <info>{$sourceFullPath}</info> to <info>{$targetFullPath}</info>");
            }
        }
    }

    /**
     * 删除包的内容
     *
     * @param string $packageName 包名
     * @param Output $output 输出对象
     */
    protected function deletePackageContent(string $packageName, Output $output): void
    {
        $packagePath = $this->app->getRootPath() . 'vendor/' . $packageName;

        if (is_dir($packagePath)) {
            $filesystem = new SymfonyFilesystem();
            $filesystem->remove($packagePath);
            $output->writeln("Deleted package files from <info>$packagePath</info>.");
        } else {
            $output->writeln("Package path <info>$packagePath</info> does not exist.");
        }
    }
}
