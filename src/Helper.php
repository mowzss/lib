<?php

namespace mowzs\lib;

use mowzs\lib\App;
use mowzs\lib\Container;

class Helper
{
    /**
     * 应用实例
     * @var App
     */
    public App $app;

    /**
     * Constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->initialize();
    }

    /**
     * 初始化服务
     */
    protected function initialize()
    {
    }

    /**
     * 静态实例对象
     * @param array $var 实例参数
     * @param boolean $new 创建新实例
     * @return Helper
     */
    public static function instance(array $var = [], bool $new = false): static
    {
        return Container::getInstance()->make(static::class, $var, $new);
    }
}
