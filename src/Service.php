<?php
declare(strict_types=1);

namespace mowzs\lib;

use mowzs\lib\command\Build;
use mowzs\lib\command\Clear;
use mowzs\lib\command\AdminInit;
use think\Service as BaseService;
use mowzs\lib\command\AdminUpgrade;
use mowzs\lib\task\command\TaskRun;
use think\db\exception\DbException;
use mowzs\lib\command\AdminModuleInit;
use mowzs\lib\task\command\TaskSchedule;
use mowzs\lib\command\AdminEntranceRename;
use mowzs\lib\command\AdminFaviconFromConfig;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;

/**
 * 应用服务类
 */
class Service extends BaseService
{
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
        // 注册JWT默认权限
        $this->app->middleware->add(\mowzs\lib\middleware\JWTAuthDefaultScene::class, 'route');
        //注册多模块路由
        $this->app->event->listen('RouteLoaded', function () {
            $this->app->route->auto()->completeMatch(false);
        });
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(MultiApp::class);
        });
        
        $this->app->bind([
            'mowzs\lib\Url' => Url::class,
        ]);
        // 注册命令行
        $this->registerCommand();
        
    }

    /**
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function tplReplaceString(): array
    {
        if (!$this->app->config->get('happy.installed', false)) {
            return array_merge($this->app->config->get('view.tpl_replace_string', []), [
                '__STATIC__' => '/static',
            ]);
        }
        if (function_exists('sys_config')) {
            switch (sys_config('static_upload')) {
                case 'oss':
                    $data = [
                        '__STATIC__' => sys_config('oss_domain') . sys_config('static_prefix'),
                    ];
                    break;
                case 'qiniu':
                    $data = [
                        '__STATIC__' => sys_config('qiniu_domain') . sys_config('static_prefix'),
                    ];
                    break;
                default:
                    $data = [
                        '__STATIC__' => '/static',
                    ];
                    break;
            }
        } else {
            $data = [
                '__STATIC__' => '/static',
            ];
        }
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
            Build::class,
            Clear::class,
        ]);
    }
}
