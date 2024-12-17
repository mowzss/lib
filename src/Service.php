<?php
declare (strict_types=1);

namespace Mowzs\Lib;

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
        $this->app->middleware->add(\Mowzs\Lib\middleware\Authentication::class, 'route');
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
}
