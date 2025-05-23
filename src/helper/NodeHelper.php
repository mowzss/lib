<?php
declare (strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;
use ReflectionClass;
use ReflectionMethod;
use think\Exception;
use think\facade\Config;
use think\helper\Str;

class NodeHelper extends Helper
{
    public string $cache_key = 'SystemNodeAll';
    /**
     * @var array|mixed
     */
    protected mixed $layer;

    protected function initialize(): void
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->layer = $this->app->config->get('route.controller_layer');
        $this->cache_key = $this->cache_key . '_' . $this->layer;
    }

    /**
     * 驼峰转下划线
     * @param $str
     * @return string
     */
    public function snake($str): string
    {
        $data = [];
        foreach (explode('.', strtr($str, '/', '.')) as $value) {
            $data[] = Str::snake($value);
        }
        return strtolower(join('.', $data));
    }

    /**
     * 获取整个节点
     * @param string $node 节点
     * @return string
     */
    public function wholeNode(string $node = ''): string
    {
        if (empty($node)) {
            return $this->getThisNode();
        }
        switch (count($attrs = explode('/', $node))) {
            case 1: # 方法名
                return $this->getThisNode('controller') . '/' . strtolower($node);
            default: # 控制器/方法名?[其他参数]
                $attrs[1] = static::snake($attrs[1]);
                return strtolower(join('/', $attrs));
        }
    }

    /**
     * 获取当前节点
     * @param string $type
     * @return string
     */
    public function getThisNode(string $type = ''): string
    {
        // 获取控制器节点
        $controller = $this->snake(request()->controller());
        if ($type === 'controller') {
            return $controller;
        }
        // 获取方法权限节点
        $method = strtolower(request()->action());
        return "{$controller}/{$method}";
    }

    protected function removeSlashes($string)
    {
        // 定义要删除的字符
        $charsToRemove = ['/', '\\'];

        // 使用 str_replace 删除这些字符
        return str_replace($charsToRemove, '', $string);
    }

    /**
     * 获取全部节点信息
     * @param bool $force_flush 是否强制刷新
     * @return array|mixed
     * @throws Exception
     */
    public function getMethods(bool $force_flush = false): mixed
    {

        $layer = $this->layer;
        if (empty($force_flush)) {
            $data = $this->app->cache->get($this->cache_key);
            if (!empty($data)) {
                return $data;
            }
        }
        $data = [];

        $basePath = $this->app->getBasePath() . $layer;
        $methods = FileHelper::instance()->scanDirectory($basePath, 'php', 0, true);
        foreach ($methods as $name) {
            $app_namespace = $this->removeSlashes(Config::get('app.app_namespace') ?: 'app') . '/';
            $name = $app_namespace . $name;
            if (preg_match("/^(.+?)\/$layer\/(.+)\.php$/", strtr($name, '\\', '/'), $matches)) {
                // 提取匹配的部分
                $app_name = $matches[1];
                $class_name = $matches[2];

                $excludeMethods = get_class_methods('\mowzs\lib\Controller');
                $this->getPublicMethodComments($app_name, $layer, $class_name, $excludeMethods, $data);
            }
        }
        $this->app->cache->set($this->cache_key, $data);
        return $data;
    }

    /**
     * 获取注释
     * @param string $app_name
     * @param string $layer
     * @param string $className
     * @param array $excludeMethods
     * @param array $data
     * @return void
     * @throws Exception
     */
    public function getPublicMethodComments(string $app_name, string $layer, string $className, array $excludeMethods = [], array &$data = []): void
    {
        //组合class
        $class = strtr($app_name . '/' . $layer . '/' . $className, '/', '\\');

        // 检测类是否存在
        if (!class_exists($class)) {
            throw new Exception("Class '$class' does not exist.");
        }
        // 创建反射类对象
        $reflectionClass = new ReflectionClass($class);
        // 解析类注释
        $classComment = $reflectionClass->getDocComment();
        $parsedClassComment = $this->parseComment($classComment);
        $key = $this->snake($className);
        $data[$key] = $parsedClassComment;
        // 获取所有公共方法
        $publicMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            // 过滤掉函数
            if (in_array($method->getName(), $excludeMethods)) {
                continue;
            }
            // 获取方法注释
            $comment = $method->getDocComment();
            // 将方法名和注释存储到数组中
            $data[$key . '/' . $method->getName()] = $this->parseComment($comment);
        }
    }

    /**
     * 去除多余的空格和换行符
     * @param $title
     * @return string
     */
    protected function cleanTitle($title): string
    {
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        return str_replace('/', '', $title);
    }

    /**
     * 解析注释信息
     * @param $comment
     * @return array
     */
    protected function parseComment($comment): array
    {
        $parsedComment = [];
        if (empty($comment)) {
            return [];
        }
        $lines = explode("\n", $comment);

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\*\s*@(\w+)\s+(.*)$/', $line, $matches)) {
                $tag = $matches[1];
                $value = $matches[2];
                $parsedComment[$tag] = $value;
            } elseif (preg_match('/^\*\s*(.*)$/', $line, $matches)) {
                if (isset($parsedComment['title'])) {
                    $parsedComment['title'] .= "\n" . trim($matches[1]);
                } else {
                    $parsedComment['title'] = trim($matches[1]);
                }
            }
        }
        $isAuth = isset($parsedComment['auth']) && strtolower($parsedComment['auth']) === 'true';
        $isLogin = isset($parsedComment['login']) && strtolower($parsedComment['login']) === 'true';
        if ($isAuth) {
            $isLogin = true;
        }
        return [
            'title' => isset($parsedComment['title']) ? $this->cleanTitle(trim($parsedComment['title'])) : '',
            'is_login' => $isLogin,
            'is_menu' => isset($parsedComment['menu']) && strtolower($parsedComment['menu']) === 'true',
            'is_auth' => $isAuth
        ];
    }

}
