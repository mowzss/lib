<?php
declare(strict_types=1);

namespace mowzs\lib;

use think\App;
use think\Container;
use think\db\BaseQuery;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\Model;

/**
 * 基础服务类
 */
abstract class BaseLogic
{
    /**
     * @var App 应用程序实例
     */
    protected App $app;
    protected \think\Request $request;

    /**
     * @var string|null 静态属性用于存储 module 的值
     */
    protected static ?string $module = null;

    /**
     * 构造函数
     *
     * @param string|null $moduleName 模块名称
     */
    public function __construct(?string $moduleName = null)
    {
        // 如果提供了模块名称，则设置它
        if ($moduleName !== null) {
            $this->setModule($moduleName);
        }
        $this->app = app();
        $this->request = $this->app->request;
        // 控制器初始化
        $this->initialize();
    }

    /**
     * 初始化
     * @return void
     */
    protected function initialize()
    {
    }

    /**
     * 设置 module 的值
     *
     * @param string $module
     * @return BaseLogic
     */
    public function setModule(string $module): static
    {
        self::$module = $module;
        return $this;
    }

    /**
     * 获取 module 的值
     *
     * @return string|null
     */
    protected function getModule(): ?string
    {
        if (self::$module === null) {
            // 如果没有设置过 module，则尝试从请求中获取
            self::$module = $this->request->layer(true);
        }
        return self::$module;
    }


    /**
     * 获取当前类的实例（用于静态调用）
     *
     * @param array $var 构造函数参数
     * @param bool $new 是否强制创建新实例
     * @return static 返回当前类的实例
     */
    public static function instance(array $var = [], bool $new = false): static
    {
        // 创建实例
        return Container::getInstance()->make(static::class, $var, $new);
    }

    /**
     * 获取模型实例
     *
     * @param string $model 模型类名或简写
     * @return Model
     * @throws Exception
     */
    protected function getModel(string $model): \think\Model
    {
        // 将模型名称转换为标准命名空间路径
        $modelName = $this->resolveModelName($model);
        // 从容器中获取模型实例
        return $this->app->make($modelName, [], true);
    }

    /**
     * 解析模型名称为完整命名空间路径
     *
     * @param string $model 模型类名或简写（支持下划线风格、驼峰命名和 PascalCase）
     * @return string 完整的命名空间路径
     * @throws Exception
     */
    private function resolveModelName(string $model): string
    {
        // 缓存解析后的模型名称
        static $modelCache = [];

        if (isset($modelCache[$model])) {
            return $modelCache[$model];
        }

        // 处理不同命名格式
        $snakeModel = $this->camelToSnake($model);  // 将驼峰命名或 PascalCase 转换为下划线命名
        $parts = explode('_', $snakeModel);

        // 确保至少有一个部分（模块名称）
        if (empty($parts)) {
            throw new Exception("Invalid model name format: [{$model}]. Expected format: module_name or module_name_class_name.");
        }

        // 保留原始的 $parts 数组副本，用于后续拼接类名
        $originalParts = $parts;

        // 获取模块名称（如 'article'）
        $module = array_shift($parts);

        // 如果有剩余部分，则将其作为类名
        if (!empty($parts)) {
            // 将剩余部分转换为 PascalCase 格式的类名
            $classNameParts = array_map('ucfirst', $parts);
            $className = ucfirst(implode('', $classNameParts));  // 确保类名首字母大写
        } else {
            // 如果没有其他部分，则类名与模块名称相同
            $className = ucfirst($module);
        }

        // 如果原始的 $parts 数组中只有一个元素，说明类名应该包含模块名称
        if (count($originalParts) === 1) {
            $className = ucfirst($originalParts[0]);
        } else {
            // 否则，类名由剩余部分拼接而成
            $classNameParts = array_map('ucfirst', $originalParts);
            $className = implode('', $classNameParts);
        }

        // 构建完整的命名空间路径
        $namespace = "app\\model\\{$module}\\{$className}";

        // 检查类是否存在
        if (!class_exists($namespace)) {
            throw new Exception("Model [{$namespace}] does not exist.");
        }

        // 缓存解析结果
        $modelCache[$model] = $namespace;

        return $namespace;
    }

    /**
     * 将驼峰命名或 PascalCase 转换为下划线命名
     *
     * @param string $string 驼峰命名或 PascalCase 的字符串
     * @return string 下划线命名的字符串
     */
    private function camelToSnake(string $string): string
    {
        // 使用正则表达式将驼峰命名或 PascalCase 转换为下划线命名
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }


    /**
     * 获取查询构建器
     *
     * @param string $name 模型类名或简写
     * @return BaseQuery
     */
    protected function getDbQuery(string $name): BaseQuery
    {
        return $this->app->db->connect()->name($name);
    }

    /**
     * 事务处理
     *
     * @param callable $callback 事务回调函数
     * @return mixed 返回回调函数的结果
     * @throws \Exception
     */
    protected function transaction(callable $callback): mixed
    {
        Db::startTrans();

        try {
            $result = $callback();
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Transaction failed: " . $e->getMessage());
            throw $e;
        }

    }

    /**
     * 缓存操作
     *
     * @param string $key 缓存键
     * @param mixed|null $value 缓存值
     * @param int|null $ttl 缓存过期时间（秒）
     * @return mixed 返回缓存结果
     */
    protected function cache(string $key, mixed $value = null, ?int $ttl = null): mixed
    {
        if ($value !== null) {
            // 设置缓存
            return Cache::set($key, $value, $ttl);
        } else {
            // 获取缓存
            return Cache::get($key);
        }
    }

    /**
     * 记录日志
     *
     * @param string $message 日志内容
     * @param string $level 日志级别（如 'info', 'error', 'debug' 等）
     * @return void
     */
    protected function log(string $message, string $level = 'info'): void
    {
        Log::record($message, $level);
    }

    /**
     * 获取配置项
     *
     * @param string $name 配置项名称
     * @param mixed $default 默认值
     * @return mixed 返回配置项的值
     */
    protected function getConfig(string $name, $default = null): mixed
    {
        return $this->app->config->get($name, $default);
    }


}
