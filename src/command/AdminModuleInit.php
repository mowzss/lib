<?php
declare(strict_types=1);

namespace mowzs\lib\command;

use think\console\Command;
use think\console\Input;
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

class AdminModuleInit extends Command
{
    /**
     * 配置命令
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('admin:moduleInit')
            ->setDescription('从已安装的happy-module类型的包中初始化自定义模块配置。')
            ->setHelp('此命令根据带有类型 "happy-module" 的已安装包中的 composer.json 文件来初始化自定义模块配置。');
    }

    /**
     * 执行
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始模块初始化...');
        // 检查并读取 installed.json 文件
        $installedJsonPath = $this->app->getRootPath() . 'vendor/composer/installed.json';
        if (!is_file($installedJsonPath)) {
            $output->writeln('<error>未找到 vendor/composer/installed.json 文件！</error>');
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
                $output->writeln("正在处理包：<info>$packageName</info>");
                // 处理 make 节点（创建目录和文件）
                // Process 'make' node (create directories and files)
                if (isset($extra['module']['make'])) {
                    $output->writeln('<info>开始处理 module[\'make\'] 节点...</info>');
                    $this->processPaths($extra['module']['make'], true, $output, $packageName);
                    $output->writeln('<info>完成处理 module[\'make\'] 节点。</info>');
                }
                // Process 'copy' node (复制文件，如果目标路径存在则跳过)
                if (isset($extra['module']['copy'])) {
                    $output->writeln('<info>开始处理 module[\'copy\'] 节点...</info>');
                    $this->processPaths($extra['module']['copy'], false, $output, $packageName);
                    $output->writeln('<info>完成处理 module[\'copy\'] 节点。</info>');
                }
                // Process 'del' node (删除包的内容)
                if (isset($extra['module']['del']) && $extra['module']['del'] === true) {
                    $output->writeln("开始清理 <info>$packageName</info>");
                    $this->deletePackageContent($packageName, $output);
                    $output->writeln("<info>$packageName</info> 清理完成");
                }
            }
        }
        $output->writeln('模块初始化完成。');
        $this->executeCommands($input, $output);
        return 0; // 返回零值表示命令成功执行
    }

    protected function executeCommands(Input $input, Output $output): int
    {
        // 定义要执行的命令列表
        $commands = [
            'optimize:route',
            'optimize:schema',
        ];
        if (!is_dir($this->app->getRuntimePath())) {
            mkdir($this->app->getRuntimePath(), 0755, true);
        }
        foreach ($commands as $commandName) {
            $output->writeln("运行 <info>$commandName</info>...");
            $commandOutput = $this->app->console->call($commandName)->fetch();
            $output->writeln($commandOutput);
        }
        $output->writeln('<comment>所有初始化步骤已完成。</comment>');
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
    protected function processPaths(array $paths, bool $forceReplace, Output $output, string $packageName)
    {
        foreach ($paths as $targetKey => $sourcePath) {
            $sourceFullPath = $this->app->getRootPath() . 'vendor/' . $packageName . '/' . $sourcePath;
            $targetFullPath = $this->app->getRootPath() . $targetKey;
            if (!file_exists($sourceFullPath)) {
                $output->writeln("错误：源路径 '$sourceFullPath' 不存在。");
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
     * @param string $sourceFullPath
     * @param string $targetFullPath
     * @param bool $forceReplace
     * @param Output $output
     * @return void
     */
    protected function processFile(string $sourceFullPath, string $targetFullPath, bool $forceReplace, Output $output)
    {
        // 确保目标文件所在的目录存在
        $targetDir = dirname($targetFullPath);
        if (!$this->ensureDirectoryExists($targetDir)) {
            $output->writeln("错误：未能创建目录 '$targetDir'。");
            return;
        }
        if (file_exists($targetFullPath)) {
            if ($forceReplace) {
                unlink($targetFullPath); // 删除目标文件
                copy($sourceFullPath, $targetFullPath);
                //                $output->writeln("替换文件至 '$targetFullPath'。");
            } else {
                $output->writeln("警告：文件 '$targetFullPath' 已存在并将被跳过。");
            }
        } else {
            copy($sourceFullPath, $targetFullPath);
            //            $output->writeln("文件复制至 '$targetFullPath'。");
        }
    }

    protected function processDirectory(string $sourceFullPath, string $targetFullPath, bool $forceReplace, Output $output)
    {
        // 确保目标目录存在
        if (!$this->ensureDirectoryExists($targetFullPath)) {
            $output->writeln("错误：未能创建目录 '$targetFullPath'。");
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceFullPath));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $relativePath = substr($file->getPathname(), strlen($sourceFullPath) + 1);
            $targetFile = $targetFullPath . DIRECTORY_SEPARATOR . $relativePath;
            // 确保目标文件所在的目录存在
            $targetDir = dirname($targetFile);
            if (!$this->ensureDirectoryExists($targetDir)) {
                $output->writeln("错误：未能创建目录 '$targetDir'。");
                continue;
            }
            if (file_exists($targetFile)) {
                if ($forceReplace) {
                    unlink($targetFile); // 删除目标文件
                    copy($file->getPathname(), $targetFile);
                    //                    $output->writeln("替换文件至 '$targetFile'。");
                } else {
                    $output->writeln("警告：文件 '$targetFile' 已存在并将被跳过。");
                }
            } else {
                copy($file->getPathname(), $targetFile);
                //                $output->writeln("文件复制至 '$targetFile'。");
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
            $output->writeln("警告：包路径 '$packagePath' 不存在。");
            return;
        }
        if (!is_dir($packagePath)) {
            $output->writeln("错误：路径 '$packagePath' 不是目录。");
            return;
        }
        $output->writeln("删除包的内容：<info>$packageName</info>");
        // 递归删除包的内容
        if ($this->recursiveDelete($packagePath, $output)) {
            $output->writeln("成功删除包的内容：<info>$packageName</info>");
        } else {
            $output->writeln("<error>删除包内容失败：$packageName</error>");
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
            $output->writeln("警告：路径 '$path' 不存在。");
            return true; // 如果路径不存在，认为删除成功
        }
        if (!is_dir($path)) {
            // 如果是文件，直接删除
            if (unlink($path)) {
                return true;
            } else {
                $output->writeln("<error>删除文件失败：$path</error>");
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
                    $output->writeln("<error>删除文件失败：$fullPath</error>");
                    return false;
                }
            }
        }
        // 删除空目录
        if (rmdir($path)) {
            return true;
        } else {
            $output->writeln("<error>删除目录失败：$path</error>");
            return false;
        }
    }

    /**
     * 确保目录路径存在，如果不存在则创建。
     *
     * @param string $path 目标路径
     * @param int $mode 目录权限模式，默认为 0755
     * @return bool 创建是否成功
     */
    public function ensureDirectoryExists(string $path, int $mode = 0755): bool
    {
        if (!file_exists($path)) {
            // mkdir() 的第三个参数设置为 true 以启用递归创建
            return mkdir($path, $mode, true);
        }
        return is_dir($path);
    }
}
