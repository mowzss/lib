<?php
declare (strict_types=1);

namespace mowzs\lib;

use think\App;

class Run
{
    protected static string $run_env = '.env';

    /**
     * @return string
     */
    protected static function getRunEnv(): string
    {
        return Helper::instance()->app->getRuntimePath() . self::$run_env;
    }

    /**
     * @return App
     */
    protected static function init(): App
    {
        $app = new App();
        return $app->debug(self::isDebug());
    }

    /**
     * 设置运行环境为调试模式或生产模式
     * @param bool $debug
     * @return void
     */
    public static function setRun(bool $debug = false): void
    {
        $currentContent = file_exists(self::getRunEnv()) ? file_get_contents(self::getRunEnv()) : '';
        $newContent = self::updateEnvContent($currentContent, 'APP_DEBUG', $debug ? 'true' : 'false');

        // 写入新的.env文件内容
        if (file_put_contents(self::getRunEnv(), $newContent) === false) {
            throw new \RuntimeException("Failed to write to " . self::getRunEnv());
        }
    }

    /**
     * 更新.env文件的内容
     * @param string $content 当前.env文件的内容
     * @param string $envKey 要更新的环境变量键
     * @param string $envValue 新的环境变量值
     * @return string 更新后的.env文件内容
     */
    private static function updateEnvContent(string $content, string $envKey, string $envValue): string
    {
        // 查找是否存在指定的环境变量
        $pattern = "/^$envKey\s*=.*/m";
        if (preg_match($pattern, $content)) {
            // 如果存在，则替换它
            return preg_replace($pattern, "$envKey=$envValue", $content);
        } else {
            // 如果不存在，则追加到文件末尾
            return $content . PHP_EOL . "$envKey=$envValue";
        }
    }

    /**
     * 获取当前环境是否为debug
     * @return array|false|mixed|null
     */
    public static function isDebug(): mixed
    {
        if (is_file(self::getRunEnv())) {
            Helper::instance()->app->env->load(self::getRunEnv());
            return Helper::instance()->app->env->get('APP_DEBUG');
        }
        return false;
    }

    /**
     * @param string $env 环境变量
     * @return void
     */
    public static function initApp(string $env = 'home'): void
    {
        // 执行HTTP应用并响应
        $http = self::init()->setEnvName($env)->http;
        $response = $http->run();
        $response->send();
        $http->end($response);
    }

}
