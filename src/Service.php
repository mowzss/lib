<?php
declare(strict_types=1);


namespace happy\admin\libs;

use happy\admin\libs\command\AdminEntranceRename;
use happy\admin\libs\command\AdminFaviconFromConfig;
use happy\admin\libs\command\AdminInit;
use happy\admin\libs\command\AdminUpgrade;
use happy\admin\libs\task\command\TaskRun;
use happy\admin\libs\task\command\TaskSchedule;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;
use think\Paginator;
use think\Service as BaseService;

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
        $this->app->middleware->add(\happy\admin\libs\middleware\Authentication::class, 'route');
        //注册请求响应过滤中间件
        $this->app->middleware->add(\happy\admin\libs\middleware\HttpResponse::class, 'route');
        // 注册JWT默认权限
        $this->app->middleware->add(\happy\admin\libs\middleware\JWTAuthDefaultScene::class, 'route');
        // 注册多模块路由自动加载
        $this->app->event->listen('RouteLoaded', function () {
            Route::auto()->completeMatch(false);
        });
        // 注册命令行
        $this->registerCommand();

    }

    /**
     * @return array
     */
    protected function tplReplaceString(): array
    {
        if (!$this->app->config->get('happy.installed', false)) {
            return array_merge($this->app->config->get('view.tpl_replace_string', []), [
                '__STATIC__' => '/static',
            ]);
        }
        $data = $this->getSysConfigTpl();
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
            AdminUpgrade::class,
            AdminEntranceRename::class,
            AdminFaviconFromConfig::class,
            TaskRun::class,
            TaskSchedule::class,
        ]);
    }

    /**
     * @return string[]
     */
    private function getSysConfigTpl(): array
    {

        if (function_exists('sys_config')) {
            try {
                return match (sys_config('static_upload')) {
                    'oss' => [
                        '__STATIC__' => sys_config('oss_domain') . sys_config('static_prefix'),
                    ],
                    'qiniu' => [
                        '__STATIC__' => sys_config('qiniu_domain') . sys_config('static_prefix'),
                    ],
                    default => [
                        '__STATIC__' => sys_config('site_domain') . '/static',
                    ],
                };
            } catch (DataNotFoundException|ModelNotFoundException|DbException|\Throwable $e) {
                $this->app->log->error($e->getMessage());
            }
        }
        try {
            return ['__STATIC__' => sys_config('site_domain') . '/static',];
        } catch (DataNotFoundException|ModelNotFoundException|DbException|\Throwable $e) {
            return ['__STATIC__' => '/static',];
        }

    }

    /**
     * 注册服务
     * @return void
     */
    public function register(): void
    {
        // 绑定类
        $this->app->bind([
            Paginator::class => Page::class,
            \think\Request::class => Request::class,
            //Handle::class => ExceptionHandle::class,
        ]);
    }
}
