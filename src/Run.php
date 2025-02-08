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
    public static function initApp(string $env = 'home'): void
    {
        // 执行HTTP应用并响应
        $http = (new App())->setBaseEnvName('base')->setEnvName($env);
        $http = $http->http;
        $response = $http->run();
        $response->send();
        $http->end($response);
    }
}
