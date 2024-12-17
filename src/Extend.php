<?php

namespace Mowzs\Lib;

abstract class Extend
{
    // 静态变量保存类的唯一实例
    private static $_instances = [];

    // 默认构造函数，接受可选参数
    protected function __construct(...$args)
    {
        // 默认构造函数可以进行一些通用的初始化操作
        // 如果没有参数传递，$args 将为空数组
        if (!empty($args)) {
            $this->initialize(...$args);
        }
    }

    /**
     * @param ...$args
     * @return static
     */
    public static function getInstance(...$args): static
    {
        $class = get_called_class(); // 获取调用者的类名
        if (!isset(self::$_instances[$class])) {
            self::$_instances[$class] = new $class(...$args);
        }
        return self::$_instances[$class];
    }

    // 防止克隆对象
    private function __clone()
    {
    }

    // 防止反序列化对象
    private function __wakeup()
    {
    }

    // 可选的初始化方法，子类可以覆盖
    protected function initialize(...$args)
    {
    }
}
