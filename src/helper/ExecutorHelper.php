<?php
declare(strict_types=1);

namespace mowzs\lib\helper;

use ReflectionClass;
use think\Exception;

class ExecutorHelper
{
    /**
     * 检查字符串是否符合 "Namespace\ClassName@methodName[@param1,param2]" 的格式
     *
     * @param string $string
     * @return bool
     * @throws Exception
     */
    public static function isValidString(string $string): bool
    {
        // 检查基本格式
        if (!preg_match('/^[\w\\\]+@[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(@.*)?$/', $string)) {
            return false;
        }

        // 进一步解析并检查类和方法是否存在
        list($classMethod, $params) = self::parseString($string);

        [$className, $methodName] = explode('@', $classMethod, 2);

        // 检查类是否存在
        if (!class_exists($className)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($className);

        // 检查方法是否存在
        if (!$reflectionClass->hasMethod($methodName)) {
            return false;
        }

        return true;
    }

    /**
     * 如果字符串符合规则，则执行它
     *
     * @param string $string
     * @return mixed
     * @throws Exception
     */
    public static function runIfValid(string $string): mixed
    {
        if (self::isValidString($string)) {
            return self::execute($string);
        } else {
            throw new Exception("字符串格式不正确，应为 'Namespace\ClassName@methodName[@param1,param2]' 的形式");
        }
    }

    /**
     * 执行指定的类方法
     *
     * @param string $string 格式为 "Namespace\ClassName@methodName@param1,param2" 的字符串
     * @return mixed
     * @throws Exception
     */
    public static function execute(string $string)
    {
        // 解析字符串
        list($classMethod, $params) = self::parseString($string);

        // 分离类名和方法名
        [$className, $methodName] = explode('@', $classMethod, 2);

        // 加载类
        if (!class_exists($className)) {
            throw new Exception("类 {$className} 不存在");
        }

        $reflectionClass = new ReflectionClass($className);
        $instance = $reflectionClass->newInstanceWithoutConstructor();

        // 获取方法反射对象
        if (!$reflectionClass->hasMethod($methodName)) {
            throw new Exception("类 {$className} 中没有方法 {$methodName}");
        }

        $reflectionMethod = $reflectionClass->getMethod($methodName);
        $reflectionMethod->setAccessible(true);

        // 准备参数
        $parameters = [];
        if (!empty($params)) {
            $parameters = array_map('trim', explode(',', $params));
        }

        // 调用方法并返回结果
        return $reflectionMethod->invokeArgs($instance, $parameters);
    }

    /**
     * 解析输入字符串
     *
     * @param string $string
     * @return array
     * @throws Exception
     */
    protected static function parseString(string $string): array
    {
        // 分离类方法和参数部分
        $parts = explode('@', $string, 3);

        if (count($parts) < 2) {
            throw new Exception("字符串格式不正确，应为 'Namespace\ClassName@methodName[@param1,param2]' 的形式");
        }

        $classMethod = $parts[0] . '@' . $parts[1];
        $params = isset($parts[2]) ? $parts[2] : '';

        return [$classMethod, $params];
    }
}
