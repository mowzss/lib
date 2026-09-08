<?php
declare(strict_types=1);

namespace happy\admin\libs\helper;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use happy\admin\libs\Exception\LibsException;

class ExecutorHelper
{
    /**
     * 检查字符串是否符合 "Namespace\ClassName@methodName@param1,param2" 的格式
     */
    public static function isValidString(string $string): bool
    {
        try {
            // 严格匹配: 类名(含命名空间) @ 方法名 @ 参数(可选)
            if (!preg_match('/^\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*@[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:@.*)?$/', $string)) {
                return false;
            }
            
            [$className, $methodName] = self::parseString($string);
            
            if (!class_exists($className)) {
                return false;
            }
            
            $reflectionClass = new ReflectionClass($className);
            
            // PHP 8.1+ 枚举不支持反射实例化
            if (method_exists($reflectionClass, 'isEnum') && $reflectionClass->isEnum()) {
                return false;
            }
            
            return $reflectionClass->hasMethod($methodName);
        } catch (\Throwable) {
            return false;
        }
    }
    
    /**
     * 如果字符串符合规则，则执行它
     */
    public static function runIfValid(string $string): mixed
    {
        if (!self::isValidString($string)) {
            throw new LibsException("字符串格式不正确或目标不可执行，应为 'Namespace\\ClassName@methodName@param1,param2' 的形式");
        }
        
        return self::execute($string);
    }
    
    /**
     * 执行指定的类方法
     */
    public static function execute(string $string): mixed
    {
        [$className, $methodName, $rawParams] = self::parseString($string);
        
        if (!class_exists($className)) {
            throw new LibsException("类 {$className} 不存在");
        }
        
        $reflectionClass = new ReflectionClass($className);
        
        if (!$reflectionClass->hasMethod($methodName)) {
            throw new LibsException("类 {$className} 中没有方法 {$methodName}");
        }
        
        $instance = self::createInstance($reflectionClass);
        $reflectionMethod = $reflectionClass->getMethod($methodName);
        
        // 【核心修复】彻底移除 setAccessible(true)，PHP 8.1+ 默认无视可见性，8.5 已弃用该方法
        
        // 根据方法签名自动转换参数类型，避免 strict_types=1 下的 TypeError
        $parameters = self::resolveParameters($reflectionMethod, $rawParams);
        
        try {
            return $reflectionMethod->invokeArgs($instance, $parameters);
        } catch (\ReflectionException|\ArgumentCountError|\TypeError $e) {
            throw new LibsException("执行 {$className}@{$methodName} 失败: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * 严格按 @ 分割解析输入字符串
     * 格式: Namespace\ClassName@methodName@param1,param2
     *
     * @return array{0: string, 1: string, 2: string} [类名, 方法名, 参数字符串]
     */
    protected static function parseString(string $string): array
    {
        // 限制最多分割为3段，确保参数中的特殊字符不会被误切
        $parts = explode('@', $string, 3);
        
        // 至少需要 类@方法 两段
        if (count($parts) < 2 || empty($parts[0]) || empty($parts[1])) {
            throw new LibsException("字符串格式不正确，必须包含 'Namespace\\ClassName@methodName'");
        }
        
        return [
            $parts[0],              // Namespace\ClassName
            $parts[1],              // methodName
            $parts[2] ?? '',        // param1,param2 (可能为空)
        ];
    }
    
    /**
     * 安全创建类实例，兼容 PHP 8.0-8.5
     */
    private static function createInstance(ReflectionClass $reflectionClass): object
    {
        // 优先尝试正常实例化（支持 readonly 类等）
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            try {
                return $reflectionClass->newInstance();
            } catch (\Throwable) {
                // fallback
            }
        }
        
        // 回退到无构造实例化
        try {
            return $reflectionClass->newInstanceWithoutConstructor();
        } catch (\ReflectionException $e) {
            throw new LibsException(
                "无法实例化类 {$reflectionClass->getName()}，可能是 readonly/internal/enum 类型",
                0,
                $e
            );
        }
    }
    
    /**
     * 根据反射类型将逗号分隔的字符串参数转换为对应类型
     */
    private static function resolveParameters(ReflectionMethod $method, string $rawParams): array
    {
        if ($rawParams === '') {
            return [];
        }
        
        $rawValues = array_map('trim', explode(',', $rawParams));
        $reflectionParams = $method->getParameters();
        $resolved = [];
        
        foreach ($rawValues as $index => $value) {
            // 超出方法参数数量的部分保持原样传入（由 invokeArgs 自行处理多余参数）
            if (!isset($reflectionParams[$index])) {
                $resolved[] = $value;
                continue;
            }
            
            $type = $reflectionParams[$index]->getType();
            
            // 非内置类型（如对象）不做转换
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $resolved[] = $value;
                continue;
            }
            
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
            
            $resolved[] = match ($typeName) {
                'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $value,
                'float' => filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float)$value : $value,
                'bool' => match (strtolower($value)) {
                    'true', '1', 'yes' => true,
                    'false', '0', 'no', '' => false,
                    default => $value,
                },
                'array' => json_decode($value, true) ?? [$value],
                'null' => null,
                default => $value,
            };
        }
        
        return $resolved;
    }
}
