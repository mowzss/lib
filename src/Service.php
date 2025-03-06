<?php
declare(strict_types=1);

namespace mowzs\lib;

use mowzs\lib\command\AdminEntranceRename;
use mowzs\lib\command\AdminFaviconFromConfig;
use mowzs\lib\command\AdminInit;
use mowzs\lib\command\AdminModuleInit;
use mowzs\lib\command\AdminUpgrade;
use mowzs\lib\task\command\TaskRun;
use mowzs\lib\task\command\TaskSchedule;
use think\Service as BaseService;

/**
 * 应用服务类
 */
class Service extends BaseService
{
    public function register()
    {
        $this->loadSysFiles();
    }

    public function boot(): void
    {
        // 服务启动
        $this->app->config->set(['tpl_replace_string' => $this->tplReplaceString()], 'view');
        // 注册session
        $this->app->middleware->add(\think\middleware\SessionInit::class);
        // 注册权限中间件
        $this->app->middleware->add(\mowzs\lib\middleware\Authentication::class, 'route');
        // 注册命令行
        $this->registerCommand();
    }

    /**
     * @return array
     */
    protected function tplReplaceString(): array
    {
        $data = [
            '__STATIC__' => '/static',
        ];
        return array_merge($this->app->config->get('view.tpl_replace_string', []), $data);
    }

    /**
     * 注册命令行
     * @return void
     */
    protected function registerCommand(): void
    {
        $this->commands([
            AdminInit::class,
            AdminModuleInit::class,
            AdminUpgrade::class,
            AdminEntranceRename::class,
            AdminFaviconFromConfig::class,
            TaskRun::class,
            TaskSchedule::class,
        ]);
        if (is_file($this->app->getBasePath() . 'commands.php')) {
            $command = include $this->app->getBasePath() . 'commands.php';
            if (is_array($command)) {
                $this->commands($command);
            }
        }
    }

    /**
     * 加载 sys.php 文件
     * @return void
     */
    protected function loadSysFiles(): void
    {
        $baseDir = $this->app->getBasePath() . DIRECTORY_SEPARATOR;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'sys.php') {
                $this->includeSysFile($file->getPathname());
            }
        }
    }

    /**
     * 包含 sys.php 文件
     * @param string $filePath
     */
    protected function includeSysFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
}
