<?php
declare(strict_types=1);

namespace happy\admin\libs\helper;

use ReflectionClass;
use think\Exception;
use ReflectionMethod;
use think\helper\Str;
use happy\admin\libs\Helper;

class NodeHelper extends Helper
{
    public string $cache_key = 'SystemNodeAll';
    
    protected mixed $layer;
    
    protected function initialize(): void
    {
        parent::initialize();
        $this->layer = $this->app->config->get('route.controller_layer', 'controller');
        $appName = $this->app->http->getName();
        $this->cache_key = "SystemNodeAll_{$appName}_{$this->layer}";
    }
    
    /**
     * 驼峰转下划线（支持多级路径）
     */
    public function snake(string $str): string
    {
        $parts = explode('.', strtr($str, '/', '.'));
        $data = [];
        foreach ($parts as $value) {
            $data[] = Str::snake($value);
        }
        return strtolower(implode('.', $data));
    }
    
    /**
     * 获取整个节点
     */
    public function wholeNode(string $node = ''): string
    {
        if (empty($node)) {
            return $this->getThisNode();
        }
        switch (count($attrs = explode('/', $node))) {
            case 1: // 仅方法名
                return $this->getThisNode('controller') . '/' . strtolower($node);
            default: // 控制器/方法名
                $attrs[1] = $this->snake($attrs[1]);
                return strtolower(implode('/', $attrs));
        }
    }
    
    /**
     * 获取当前请求节点
     */
    public function getThisNode(string $type = ''): string
    {
        $controller = $this->snake(request()->controller());
        if ($type === 'controller') {
            return $controller;
        }
        $method = strtolower(request()->action());
        return "{$controller}/{$method}";
    }
    
    /**
     * 获取全部节点信息
     * @param bool $force_flush 是否强制刷新
     * @return array
     * @throws Exception
     */
    public function getMethods(bool $force_flush = false): array
    {
        if (!$force_flush) {
            $cached = $this->app->cache->get($this->cache_key);
            if (!empty($cached)) {
                return $cached;
            }
        }
        
        $appName = $this->app->http->getName();
        $basePath = $this->app->getAppPath() . $this->layer . DIRECTORY_SEPARATOR;
        $baseNamespace = rtrim($this->app->getNamespace(), '\\') . '\\' . $this->layer;
        
        // ✅ 仅排除 \happy\admin\libs\Controller 基类的方法
        $excludeMethods = [];
        if (class_exists('\happy\admin\libs\Controller')) {
            $excludeMethods = get_class_methods('\happy\admin\libs\Controller');
        }
        
        $data = [];
        
        if (!is_dir($basePath)) {
            $this->app->cache->set($this->cache_key, $data, 3600);
            return $data;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            
            $relativePath = substr($file->getPathname(), strlen($basePath));
            $relativeClass = str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath
            );
            $fullClass = $baseNamespace . '\\' . $relativeClass;
            
            try {
                if (!class_exists($fullClass)) {
                    continue;
                }
                
                $reflection = new ReflectionClass($fullClass);
                
                if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                    continue;
                }
                
                // 解析类注释
                $classComment = $this->parseComment($reflection->getDocComment());
                $nodeKey = $this->snake(str_replace('\\', '/', $relativeClass));
                $data[$nodeKey] = $classComment;
                
                // ✅ 获取所有 public 方法（含继承、含 Trait），仅靠黑名单过滤
                $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    $methodName = $method->getName();
                    // 跳过魔术方法和构造函数
                    if (str_starts_with($methodName, '__')) {
                        continue;
                    }
                    // ✅ 仅排除 Controller 基类方法，其余全部保留
                    if (in_array($methodName, $excludeMethods, true)) {
                        continue;
                    }
                    
                    $methodComment = $this->parseComment($method->getDocComment());
                    $methodNodeKey = $nodeKey . '/' . strtolower($methodName);
                    $data[$methodNodeKey] = $methodComment;
                }
            } catch (\Throwable $e) {
                trace("Node scan failed for {$fullClass}: " . $e->getMessage(), 'warning');
                continue;
            }
        }
        
        $this->app->cache->set($this->cache_key, $data, 3600);
        return $data;
    }
    
    /**
     * 获取类的公共方法及注释（保留原签名供外部调用）
     * @param string $appName
     * @param string $layer
     * @param string $className
     * @param array $excludeMethods
     * @param array $data
     * @return void
     * @throws Exception
     */
    public function getPublicMethodComments(
        string $appName,
        string $layer,
        string $className,
        array $excludeMethods = [],
        array &$data = []
    ): void
    {
        $class = str_replace('/', '\\', $appName . '/' . $layer . '/' . $className);
        
        if (!class_exists($class)) {
            throw new Exception("Class '$class' does not exist.");
        }
        
        $reflection = new ReflectionClass($class);
        
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }
        
        $classComment = $this->parseComment($reflection->getDocComment());
        $key = $this->snake($className);
        $data[$key] = $classComment;
        
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            $methodName = $method->getName();
            
            if (in_array($methodName, $excludeMethods, true)) {
                continue;
            }
            if (str_starts_with($methodName, '__')) {
                continue;
            }
            
            $comment = $this->parseComment($method->getDocComment());
            $data[$key . '/' . strtolower($methodName)] = $comment;
        }
    }
    
    /**
     * 清理标题
     * @param string $title
     * @return string
     */
    protected function cleanTitle(string $title): string
    {
        $title = preg_replace('/\s+/', ' ', trim($title));
        return str_replace('/', '', $title);
    }
    
    /**
     * 解析 DocBlock 注释
     * @param string|null $comment
     * @return array
     */
    protected function parseComment(?string $comment): array
    {
        $default = [
            'title' => '',
            'is_login' => false,
            'is_menu' => false,
            'is_auth' => false,
        ];
        
        if (empty($comment)) {
            return $default;
        }
        
        $parsed = [];
        $lines = explode("\n", $comment);
        $titleLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\*\s*@(\w+)\s+(.*)$/', $line, $matches)) {
                $parsed[strtolower($matches[1])] = trim($matches[2]);
            } elseif (preg_match('/^\*\s*([^@].*)$/', $line, $matches)) {
                $text = trim($matches[1]);
                if ($text !== '' && $text !== '/') {
                    $titleLines[] = $text;
                }
            }
        }
        
        $isAuth = isset($parsed['auth']) && strtolower($parsed['auth']) === 'true';
        $isLogin = isset($parsed['login']) && strtolower($parsed['login']) === 'true';
        if ($isAuth) {
            $isLogin = true;
        }
        
        return [
            'title' => $this->cleanTitle(implode(' ', $titleLines)),
            'is_login' => $isLogin,
            'is_menu' => isset($parsed['menu']) && strtolower($parsed['menu']) === 'true',
            'is_auth' => $isAuth,
        ];
    }
}
