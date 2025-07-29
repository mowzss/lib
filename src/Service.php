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
//        $this->loadSysFiles();
    }

    public function boot(): void
    {
        // 服务启动
        $this->app->config->set(['tpl_replace_string' => $this->tplReplaceString()], 'view');
        // 注册session
        $this->app->middleware->add(\think\middleware\SessionInit::class);
        // 注册权限中间件
        $this->app->middleware->add(\mowzs\lib\middleware\Authentication::class, 'route');
        //注册请求响应过滤中间件
        $this->app->middleware->add(\mowzs\lib\middleware\HttpResponse::class, 'route');
        // 注册命令行
        $this->registerCommand();
        //注册多模块路由
        $this->app->event->listen('RouteLoaded', function () {
            $this->app->route->auto();
        });
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
    }

    /**
     * 加载 sys.php 文件
     * @return void
     */
    protected function loadSysFiles(): void
    {
        $baseDir = $this->app->getAppPath() . DIRECTORY_SEPARATOR;
        $paths = [
            $baseDir . 'sys.php',
            $baseDir,
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                // 如果是目录，则遍历子目录寻找 sys.php 文件
                $this->scanDirectoryForSysFiles($path);
            } elseif (is_file($path)) {
                // 如果是文件，则直接加载
                $this->includeSysFile($path);
            }
        }
    }

    /**
     * 遍历目录寻找 sys.php 文件
     * @param string $directory
     */
    protected function scanDirectoryForSysFiles(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
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
