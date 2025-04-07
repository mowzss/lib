<?php

namespace mowzs\lib\middleware;

use think\App;

class HttpResponse
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

    /**
     * @param $request
     * @param \Closure $next
     * @return mixed|\think\response\Redirect
     */
    public function handle($request, \Closure $next)
    {
        // 获取查询字符串
        $getArray = $this->app->request->get();

        // 匹配 in=wap、in=pc 或 in=xx
        if (!empty($this->app->request->get('in')) && isset($getArray['in'])) {
            unset($getArray['in']);
            //组合新地址
            $url = $this->app->request->baseUrl();
            if (!empty($getArray)) {
                $url .= '?' . http_build_query($getArray);
            }
            return redirect($url, 301);
        }
        //检测请求地址是否包含index.php 如果包含则跳转为无index.php
        if (strpos($this->app->request->url(), 'index.php') !== false) {
            $url = str_replace('index.php', '', $this->app->request->url());
            return redirect($url, 301);
        }

        return $next($request);
    }
}
