<?php
declare (strict_types=1);

namespace mowzs\lib;

use mowzs\lib\command\ModuleInit;
use think\Service as BaseService;

/**
 * 应用服务类
 */
class Service extends BaseService
{
    public function register()
    {

    }

    public function boot(): void
    {
        // 服务启动
        $this->app->config->set(['tpl_replace_string' => $this->tplReplaceString()], 'view');
        //注册session
        $this->app->middleware->add(\think\middleware\SessionInit::class);
        //注册权限中间件
        $this->app->middleware->add(\mowzs\lib\middleware\Authentication::class, 'route');
        //注册命令行
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
            ModuleInit::class
        ]);
    }
}
