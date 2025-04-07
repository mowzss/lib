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
            $login_url = $this->app->config->get('happy.auth_login') ?: 'index/login/index';
            $login_url = aurl($login_url);
            if ($request->isAjax()) {
                throw new HttpResponseException(json(['code' => 0, 'info' => lang('请重新登录！'), 'url' => $login_url]));
            } else {
                return redirect($login_url);
            }
        }
        // 获取查询字符串
        $queryString = $this->app->request->query();

        // 匹配 in=wap、in=pc 或 in=xx
        if (!empty($this->app->request->get('in'))) {
            // 移除 in=xx 参数
            $newQueryString = preg_replace('/(^|&)in=(wap|pc|[^&]+)/', '', $queryString);
            $newQueryString = trim($newQueryString, '&'); // 去掉多余的 &

            // 构造新的 URL
            $newUrl = $request->baseUrl();
            if ($newQueryString) {
                $newUrl .= '?' . $newQueryString;
            }
            return redirect($newUrl, 301);
        }
        return $next($request);
    }
}
