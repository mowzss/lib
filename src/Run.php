<?php
declare (strict_types=1);

namespace mowzs\lib;

use think\App;

class Run
{
    /**
     * @param string $env 环境变量
     * @return void
     */
    public static function initApp(string $env = ''): void
    {
        // 执行HTTP应用并响应
        $http = (new App());
        if (!empty($env)) {
            $http = $http->setEnvName('admin');
        }
        $http = $http->http;
        $response = $http->run();
        $response->send();
        $http->end($response);
    }
}
