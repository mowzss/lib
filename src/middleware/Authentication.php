<?php
declare (strict_types=1);

namespace mowzs\lib\middleware;

use mowzs\lib\helper\AuthHelper;
use think\App;
use think\exception\HttpResponseException;

class Authentication
{
    /**
     * 当前 App 对象
     * @var \think\App
     */
    protected App $app;

    /**
     * Construct
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle($request, \Closure $next)
    {
        if ($this->app->config->get('route.controller_layer') == 'admin') {
            //权限校验 或 忽略控制器
            if (AuthHelper::instance()->cheek()) {
                return $next($request);
            }
            //已登录 无权限
            if (AuthHelper::instance()->isLogin()) {
                throw new HttpResponseException(json(['code' => 0, 'info' => lang('禁用访问！')]));
            }
            //未登录
            $login_url = $this->app->config->get('auth.auth_login') ?: url('login/index');
            if ($request->isAjax()) {
                throw new HttpResponseException(json(['code' => 0, 'info' => lang('请重新登录！'), 'url' => $login_url]));
            } else {
                return redirect($login_url);
            }

        }
        return $next($request);
    }
}
