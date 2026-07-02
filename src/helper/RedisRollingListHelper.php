<?php
declare(strict_types=1);

namespace mowzs\lib\helper;

use think\App;
use think\facade\Log;
use mowzs\lib\Helper;
use think\facade\Cache;

/**
 * 通用Redis滚动列表存储器
 * 支持基于唯一标识的去重、自动滚动覆盖、TTL管理
 */
class RedisRollingListHelper extends Helper
{
    /** @var string 缓存键前缀 */
    private string $keyPrefix;
    
    /** @var int 最大保留条数 */
    private int $maxSize;
    
    /** @var int 缓存过期时间(秒) */
    private int $ttl;
    
    /** @var string 用于去重的唯一字段名 */
    private string $uniqueField;
    
    /**
     * @param App $app 由容器自动注入，必须放在第一个参数
     * @param string $keyPrefix 缓存键前缀
     * @param int $maxSize 最大保留条数
     * @param int $ttl 缓存过期时间(秒)
     * @param string $uniqueField 用于去重的唯一字段名
     */
    public function __construct(App $app, string $keyPrefix = '', int $maxSize = 20, int $ttl = 86400, string $uniqueField = 'id')
    {
        parent::__construct($app);
        
        $this->keyPrefix = $keyPrefix;
        $this->maxSize = $maxSize;
        $this->ttl = $ttl;
        $this->uniqueField = $uniqueField;
    }
    
    /**
     * 获取完整的缓存Key
     * @param string $suffix
     * @return string
     */
    public function getCacheKey(string $suffix = ''): string
    {
        $safeSuffix = preg_replace('/[^a-zA-Z0-9_\-]/', '', $suffix);
        // 现在可以安全地使用 $this->app 了
        $systemPrefix = $this->app->config->get('cache.stores.redis.prefix', '');
        return $systemPrefix . $this->keyPrefix . $safeSuffix;
    }
    
    /**
     * 添加记录（自动去重 + 滚动覆盖）
     * @param array $data 要存储的数据
     * @param string $suffix Key后缀（如模块名、用户ID等）
     */
    public function add(array $data, string $suffix = ''): bool
    {
        try {
            $redis = Cache::store('redis')->handler();
            $key = $this->getCacheKey($suffix);
            $value = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            
            // 基于唯一字段精确去重
            if (!empty($data[$this->uniqueField])) {
                $allItems = $redis->lRange($key, 0, -1);
                foreach ($allItems as $item) {
                    $decoded = json_decode($item, true);
                    if (($decoded[$this->uniqueField] ?? null) === $data[$this->uniqueField]) {
                        $redis->lRem($key, $item, 1);
                        break;
                    }
                }
            }
            
            // Pipeline批量写入
            $pipe = $redis->multi(\Redis::PIPELINE);
            $pipe->lPush($key, $value);
            $pipe->lTrim($key, 0, $this->maxSize - 1);
            $pipe->expire($key, $this->ttl);
            $pipe->exec();
            
            return true;
        } catch (\Throwable $e) {
            Log::error("RedisRollingList::add failed [{$this->keyPrefix}{$suffix}]: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取列表
     * @param int $limit
     * @param string $suffix
     * @return array
     */
    public function getList(int $limit = 0, string $suffix = ''): array
    {
        try {
            $redis = Cache::store('redis')->handler();
            $key = $this->getCacheKey($suffix);
            $limit = $limit > 0 ? min($limit, $this->maxSize) : $this->maxSize;
            
            $rawList = $redis->lRange($key, 0, $limit - 1);
            if (empty($rawList)) {
                return [];
            }
            
            return array_map(fn($item) => json_decode($item, true), $rawList);
        } catch (\Throwable $e) {
            Log::error("RedisRollingList::getList failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 清空指定后缀的记录
     * @param string $suffix
     * @return bool
     */
    public function clear(string $suffix = ''): bool
    {
        try {
            return Cache::store('redis')->delete($this->getCacheKey($suffix));
        } catch (\Throwable $e) {
            Log::error("RedisRollingList::clear failed: " . $e->getMessage());
            return false;
        }
    }
}
