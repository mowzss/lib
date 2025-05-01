<?php

namespace mowzs\lib\extend;

use think\facade\Console;

class RuntimeExtend
{
    /**
     * 检测路由缓存是否存在
     * 适用于命令行使用url()函数 未生成缓存文件时，url()函数生成的网址路径不正确
     * @param bool $build 是否直接生成路由缓存文件
     * @return bool
     */
    public static function checkRoute(bool $build = true): bool
    {
        $runtimePath = app()->getRuntimePath();
        $file_path = $runtimePath . DIRECTORY_SEPARATOR . 'route.php';
        if (!file_exists($file_path) && $build) {
            // 尝试生成路由缓存
            self::runOptimizeRoute();
            // 再次检查文件是否存在
            return file_exists($file_path);
        }
        return file_exists($file_path);
    }

    /**
     * 运行路由缓存命令
     * @return \think\console\Output|\think\console\output\driver\Buffer
     */
    public static function runOptimizeRoute(): \think\console\output\driver\Buffer|\think\console\Output
    {
        return Console::call('optimize:route');
    }
}
