<?php

declare(strict_types=1);

namespace happy\admin\libs\helper;

use ReflectionClass;
use think\Exception;
use ReflectionMethod;
use think\helper\Str;
use happy\admin\libs\Helper;

/**
 * 系统节点扫描助手
 *
 * 通过 Token 解析 PHP 源文件获取控制器方法及注释信息，
 * 无需加载类即可安全扫描，避免依赖缺失导致的扫描失败。
 */
class NodeHelper extends Helper
{
    /**
     * 节点缓存键名
     */
    public string $cache_key = 'SystemNodeAll';
    
    /**
     * 控制器目录层级名称
     */
    protected string $layer = 'controller';
    
    /**
     * 初始化配置
     *
     * @return void
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->layer = (string)$this->app->config->get('route.controller_layer', 'controller');
        $appName = $this->app->http->getName();
        $this->cache_key = "SystemNodeAll_{$appName}_{$this->layer}";
    }
    
    /**
     * 将驼峰/路径格式字符串转为下划线小写格式
     *
     * @param string $str 待转换字符串，支持 "/" 或 "." 分隔的多级路径
     * @return string 转换后的下划线小写字符串
     */
    public function snake(string $str): string
    {
        $parts = explode('.', str_replace('/', '.', $str));
        $result = [];
        foreach ($parts as $part) {
            $result[] = Str::snake($part);
        }
        return strtolower(implode('.', $result));
    }
    
    /**
     * 获取完整节点标识
     *
     * @param string $node 节点字符串，为空时返回当前请求节点
     * @return string 格式化后的节点标识
     */
    public function wholeNode(string $node = ''): string
    {
        if ($node === '') {
            return $this->getThisNode();
        }
        
        $attrs = explode('/', $node);
        if (count($attrs) === 1) {
            return $this->getThisNode('controller') . '/' . strtolower($node);
        }
        
        $attrs[1] = $this->snake($attrs[1]);
        return strtolower(implode('/', $attrs));
    }
    
    /**
     * 获取当前请求的节点标识
     *
     * @param string $type 返回类型，"controller" 仅返回控制器节点，其他返回完整节点
     * @return string 当前请求对应的节点标识
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
     *
     * @param bool $force_flush 是否强制刷新缓存
     * @return array<string, array{title: string, is_login: bool, is_menu: bool, is_auth: bool}> 节点列表
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
        
        $basePath = $this->app->getAppPath() . $this->layer . DIRECTORY_SEPARATOR;
        $data = [];
        
        if (!is_dir($basePath)) {
            $this->app->cache->set($this->cache_key, $data, 3600);
            return $data;
        }
        
        $baseControllerMethods = $this->getBaseControllerMethods();
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            
            $filePath = $file->getPathname();
            $relativePath = substr($filePath, strlen($basePath));
            $relativeClass = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
            $nodeKey = $this->snake(str_replace('\\', '/', $relativeClass));
            
            try {
                $fileMethods = $this->parsePublicMethodsFromFile($filePath);
                $classDocComment = $this->extractClassDocComment($filePath);
                $data[$nodeKey] = $this->parseComment($classDocComment);
                
                $traits = $this->parseTraitsFromFile($filePath);
                $traitMethods = [];
                $resolved = [];
                foreach ($traits as $traitName) {
                    $traitMethods = $traitMethods + $this->parseTraitMethods($traitName, $resolved);
                }
                
                $allMethods = $traitMethods + $fileMethods;
                
                foreach ($allMethods as $methodName => $docComment) {
                    if (str_starts_with($methodName, '__')) {
                        continue;
                    }
                    if (in_array($methodName, $baseControllerMethods, true)) {
                        continue;
                    }
                    $methodNodeKey = $nodeKey . '/' . strtolower($methodName);
                    $data[$methodNodeKey] = $this->parseComment($docComment);
                }
            } catch (\Throwable $e) {
                $msg = sprintf(
                    'Node scan FAILED for %s: [%s] %s',
                    $relativeClass,
                    get_class($e),
                    $e->getMessage()
                );
                trace($msg, 'error');
            }
        }
        
        $this->app->cache->set($this->cache_key, $data, 3600);
        return $data;
    }
    
    /**
     * 获取类的公共方法及注释（保留原签名供外部调用）
     *
     * @param string $appName 应用名称
     * @param string $layer 控制器层级
     * @param string $className 类名（含子目录路径）
     * @param array $excludeMethods 需排除的方法名列表
     * @param array $data 引用传递的结果数组
     * @return void
     * @throws Exception 当类不存在时抛出异常
     */
    public function getPublicMethodComments(
        string $appName,
        string $layer,
        string $className,
        array $excludeMethods = [],
        array &$data = []
    ): void {
        $class = str_replace('/', '\\', $appName . '/' . $layer . '/' . $className);
        
        if (!class_exists($class)) {
            throw new Exception("Class '{$class}' does not exist.");
        }
        
        $reflection = new ReflectionClass($class);
        
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }
        
        $baseControllerClass = 'happy\admin\libs\Controller';
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
            if ($method->getDeclaringClass()->getName() === $baseControllerClass) {
                continue;
            }
            
            $comment = $this->parseComment($method->getDocComment());
            $data[$key . '/' . strtolower($methodName)] = $comment;
        }
    }
    
    /**
     * 从文件中解析 trait use 声明列表
     *
     * @param string $filePath PHP 文件绝对路径
     * @return list<string> trait 类名列表
     */
    private function parseTraitsFromFile(string $filePath): array
    {
        $traits = [];
        $tokens = token_get_all(file_get_contents($filePath));
        $count = count($tokens);
        
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_USE) {
                continue;
            }
            
            if (!$this->isTraitUseContext($tokens, $i)) {
                continue;
            }
            
            $collected = $this->collectTraitNames($tokens, $i + 1, $count);
            foreach ($collected as $name) {
                $traits[] = $name;
            }
        }
        
        return $traits;
    }
    
    /**
     * 判断 T_USE 是否处于类内部的 trait 引入上下文
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token 数组
     * @param int $index T_USE 所在的索引位置
     * @return bool 是否为 trait use 声明
     */
    private function isTraitUseContext(array $tokens, int $index): bool
    {
        $prev = $index - 1;
        while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
            $prev--;
        }
        
        return $prev >= 0
            && !is_array($tokens[$prev])
            && in_array($tokens[$prev], ['{', ',', ';'], true);
    }
    
    /**
     * 从 T_USE 之后收集 trait 名称直到语句结束
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token 数组
     * @param int $start 起始搜索索引（T_USE 之后）
     * @param int $count Token 总数
     * @return list<string> 收集到的 trait 类名列表
     */
    private function collectTraitNames(array $tokens, int $start, int $count): array
    {
        $names = [];
        $current = '';
        
        for ($j = $start; $j < $count; $j++) {
            if (is_array($tokens[$j])) {
                $tokenType = $tokens[$j][0];
                if ($this->isNameToken($tokenType)) {
                    $current .= $tokens[$j][1];
                } elseif ($tokenType === T_NS_SEPARATOR) {
                    $current .= '\\';
                } elseif ($tokenType !== T_WHITESPACE) {
                    break;
                }
            } else {
                if ($tokens[$j] === ',') {
                    if ($current !== '') {
                        $names[] = $current;
                        $current = '';
                    }
                    continue;
                }
                if ($tokens[$j] === ';' || $tokens[$j] === '{') {
                    break;
                }
                break;
            }
        }
        
        if ($current !== '') {
            $names[] = $current;
        }
        
        return $names;
    }
    
    /**
     * 判断 token 类型是否为名称类 token（兼容不同 PHP 版本）
     *
     * @param int $tokenType Token 类型常量值
     * @return bool 是否为名称类 token
     */
    private function isNameToken(int $tokenType): bool
    {
        if ($tokenType === T_STRING) {
            return true;
        }
        // T_NAME_QUALIFIED (PHP 8.0+)、T_NAME_FULLY_QUALIFIED (PHP 8.0+)
        if (defined('T_NAME_QUALIFIED') && $tokenType === T_NAME_QUALIFIED) {
            return true;
        }
        if (defined('T_NAME_FULLY_QUALIFIED') && $tokenType === T_NAME_FULLY_QUALIFIED) {
            return true;
        }
        return false;
    }
    
    /**
     * 从文件中解析 public 方法及其 DocComment
     *
     * @param string $filePath PHP 文件绝对路径
     * @return array<string, string|null> 方法名 => DocComment 映射
     */
    private function parsePublicMethodsFromFile(string $filePath): array
    {
        $methods = [];
        $tokens = token_get_all(file_get_contents($filePath));
        $count = count($tokens);
        $lastDocComment = null;
        
        for ($i = 0; $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_DOC_COMMENT) {
                $lastDocComment = $tokens[$i][1];
                continue;
            }
            
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_PUBLIC) {
                $funcIndex = $this->findNextFunctionKeyword($tokens, $i + 1, $count);
                if ($funcIndex !== null) {
                    $nameIndex = $this->findNextNonWhitespace($tokens, $funcIndex + 1, $count);
                    if ($nameIndex !== null
                        && is_array($tokens[$nameIndex])
                        && $tokens[$nameIndex][0] === T_STRING
                    ) {
                        $methods[$tokens[$nameIndex][1]] = $lastDocComment;
                    }
                }
                $lastDocComment = null;
                continue;
            }
            
            if ($this->isStatementTerminator($tokens[$i])) {
                $lastDocComment = null;
            }
        }
        
        return $methods;
    }
    
    /**
     * 从指定位置向后查找 function 关键字（跳过修饰符和空白）
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token 数组
     * @param int $start 起始搜索索引
     * @param int $count Token 总数
     * @return int|null function 关键字的索引，未找到返回 null
     */
    private function findNextFunctionKeyword(array $tokens, int $start, int $count): ?int
    {
        $j = $this->skipWhitespace($tokens, $start, $count);
        
        while ($j < $count && is_array($tokens[$j])
            && in_array($tokens[$j][0], [T_STATIC, T_FINAL, T_ABSTRACT], true)
        ) {
            $j++;
            $j = $this->skipWhitespace($tokens, $j, $count);
        }
        
        if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION) {
            return $j;
        }
        
        return null;
    }
    
    /**
     * 跳过连续的空白 token，返回第一个非空白 token 的索引
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token 数组
     * @param int $start 起始搜索索引
     * @param int $count Token 总数
     * @return int 第一个非空白 token 的索引
     */
    private function skipWhitespace(array $tokens, int $start, int $count): int
    {
        $j = $start;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        return $j;
    }
    
    /**
     * 从指定位置向后查找第一个非空白 token
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token 数组
     * @param int $start 起始搜索索引
     * @param int $count Token 总数
     * @return int|null 非空白 token 的索引，未找到返回 null
     */
    private function findNextNonWhitespace(array $tokens, int $start, int $count): ?int
    {
        for ($k = $start; $k < $count; $k++) {
            if (!is_array($tokens[$k]) || $tokens[$k][0] !== T_WHITESPACE) {
                return $k;
            }
        }
        return null;
    }
    
    /**
     * 判断 token 是否为语句终止符（用于清除暂存的 DocComment）
     *
     * @param array{0: int, 1: string, 2: int}|string $token Token 元素
     * @return bool 是否为语句终止符
     */
    private function isStatementTerminator(array|string $token): bool
    {
        if (is_array($token)) {
            return false;
        }
        return in_array($token, [';', '{', '}', ')'], true);
    }
    
    /**
     * 递归解析 Trait 中的 public 方法
     *
     * @param string $traitClassName Trait 完整类名
     * @param array<string, array<string, string|null>> $resolved 已解析的 trait 缓存（防循环引用）
     * @return array<string, string|null> 方法名 => DocComment 映射
     */
    private function parseTraitMethods(string $traitClassName, array &$resolved = []): array
    {
        if (isset($resolved[$traitClassName])) {
            return $resolved[$traitClassName];
        }
        
        $resolved[$traitClassName] = [];
        
        $traitFile = $this->resolveTraitFilePath($traitClassName);
        if ($traitFile === null) {
            return [];
        }
        
        $methods = $this->parsePublicMethodsFromFile($traitFile);
        
        $nestedTraits = $this->parseTraitsFromFile($traitFile);
        foreach ($nestedTraits as $nestedTrait) {
            $nestedMethods = $this->parseTraitMethods($nestedTrait, $resolved);
            $methods = $nestedMethods + $methods;
        }
        
        $resolved[$traitClassName] = $methods;
        return $methods;
    }
    
    /**
     * 解析 Trait 类名对应的文件路径
     *
     * @param string $traitClassName Trait 完整类名
     * @return string|null 文件绝对路径，无法定位返回 null
     */
    private function resolveTraitFilePath(string $traitClassName): ?string
    {
        if (trait_exists($traitClassName, false)) {
            try {
                $ref = new ReflectionClass($traitClassName);
                $file = $ref->getFileName();
                if ($file !== false && file_exists($file)) {
                    return $file;
                }
            } catch (\Throwable) {
                // 反射失败，降级到路径推导
            }
        }
        
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $traitClassName) . '.php';
        $possiblePaths = [
            $this->app->getRootPath() . $relativePath,
            $this->app->getRootPath() . 'vendor' . DIRECTORY_SEPARATOR . $relativePath,
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * 获取 Controller 基类的 public 方法名列表
     *
     * @return list<string> 基类方法名数组
     */
    private function getBaseControllerMethods(): array
    {
        $baseControllerClass = 'happy\admin\libs\Controller';
        
        if (!class_exists($baseControllerClass, false)) {
            return [];
        }
        
        try {
            $file = (new ReflectionClass($baseControllerClass))->getFileName();
        } catch (\Throwable) {
            return [];
        }
        
        if ($file === false || !file_exists($file)) {
            return [];
        }
        
        return array_keys($this->parsePublicMethodsFromFile($file));
    }
    
    /**
     * 提取文件中类级别的 DocComment
     *
     * @param string $filePath PHP 文件绝对路径
     * @return string|null 类 DocComment，未找到返回 null
     */
    private function extractClassDocComment(string $filePath): ?string
    {
        $tokens = token_get_all(file_get_contents($filePath));
        $count = count($tokens);
        $lastDoc = null;
        
        for ($i = 0; $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_DOC_COMMENT) {
                $lastDoc = $tokens[$i][1];
                continue;
            }
            
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
                return $lastDoc;
            }
            
            if ($this->isDocCommentResetToken($tokens[$i])) {
                $lastDoc = null;
            }
        }
        
        return $lastDoc;
    }
    
    /**
     * 判断 token 是否应重置暂存的 DocComment
     *
     * @param array{0: int, 1: string, 2: int}|string $token Token 元素
     * @return bool 是否应重置
     */
    private function isDocCommentResetToken(array|string $token): bool
    {
        if (!is_array($token)) {
            return $token !== ';';
        }
        
        $resetTypes = [T_WHITESPACE, T_DOC_COMMENT, T_COMMENT, T_ABSTRACT, T_FINAL];
        
        // T_ATTRIBUTE (PHP 8.0+)
        if (defined('T_ATTRIBUTE')) {
            $resetTypes[] = T_ATTRIBUTE;
        }
        // T_READONLY (PHP 8.1+)
        if (defined('T_READONLY')) {
            $resetTypes[] = T_READONLY;
        }
        
        return !in_array($token[0], $resetTypes, true);
    }
    
    /**
     * 清理标题文本
     *
     * @param string $title 原始标题
     * @return string 清理后的标题
     */
    protected function cleanTitle(string $title): string
    {
        $title = preg_replace('/\s+/', ' ', trim($title));
        return str_replace('/', '', $title);
    }
    
    /**
     * 解析 DocBlock 注释为结构化数组
     *
     * @param string|false|null $comment DocComment 原始文本
     * @return array{title: string, is_login: bool, is_menu: bool, is_auth: bool} 解析结果
     */
    protected function parseComment(string|false|null $comment): array
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
