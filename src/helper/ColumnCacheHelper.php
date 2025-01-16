<?php
declare(strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;
use think\facade\Db;
use Throwable;

class ColumnCacheHelper extends Helper
{
    /**
     * 自动获取或验证模块名称
     *
     * @param string|null $module 模块名称（可为空）
     * @return string 获取到的模块名称
     */
    protected function getModule(string $module = null): string
    {
        // 如果 module 为空，尝试从请求中获取
        if (empty($module)) {
            $module = $this->app->request->layer(true);
        }
        return $module;
    }

    /**
     * 获取模块所有栏目的 ID 和标题映射
     *
     * @param string|null $module 模块名称（可为空）
     * @return array 栏目 ID 和标题的映射数组
     * @throws Throwable
     */
    public function getColumnsByIdTitle(?string $module = null): array
    {
        // 自动获取或验证模块名称
        $module = $this->getModule($module);

        // 定义缓存键名
        $cacheKey = "{$module}_columns_id_title";

        // 从缓存中获取栏目数据，如果不存在则查询数据库并缓存
        return $this->app->cache->remember($cacheKey, function () use ($module) {
            $tableColumn = $module . '_column';
            return Db::name($tableColumn)->column('title', 'id');
        }, 86400);
    }

    /**
     * 清除模块所有栏目的 ID 和标题映射缓存
     *
     * @param string|null $module 模块名称（可为空）
     * @return bool 是否成功清除缓存
     * @throws \Exception
     */
    public function clearColumnsByIdTitleCache(?string $module = null): bool
    {
        // 自动获取或验证模块名称
        $module = $this->getModule($module);

        $cacheKey = "{$module}_columns_id_title";
        return $this->app->cache->delete($cacheKey);
    }

    /**
     * 获取单个栏目的详细信息
     *
     * @param string|null $module 模块名称（可为空）
     * @param int|string $columnId 栏目 ID
     * @return array|null 栏目信息，如果不存在则返回 null
     * @throws Throwable
     */
    public function getColumnById(?string $module = null, int|string $columnId = 0): ?array
    {
        // 自动获取或验证模块名称
        $module = $this->getModule($module);

        // 定义缓存键名
        $cacheKey = "{$module}_column_{$columnId}";

        // 从缓存中获取栏目数据，如果不存在则查询数据库并缓存
        return $this->app->cache->remember($cacheKey, 3600, function () use ($module, $columnId) {
            $tableColumn = $module . '_column';
            return Db::name($tableColumn)->where('id', $columnId)->findOrEmpty()->toArray();
        });
    }

    /**
     * 清除单个栏目的缓存
     *
     * @param string|null $module 模块名称（可为空）
     * @param int|string $columnId 栏目 ID
     * @return bool 是否成功清除缓存
     */
    public function clearColumnCache(?string $module = null, int|string $columnId = 0): bool
    {
        // 自动获取或验证模块名称
        $module = $this->getModule($module);

        $cacheKey = "{$module}_column_{$columnId}";
        return $this->app->cache->delete($cacheKey);
    }

    /**
     * 获取模块所有栏目的详细信息
     *
     * @param string|null $module 模块名称（可为空）
     * @return array 栏目详细信息数组
     * @throws Throwable
     */
    public function getAllColumns(?string $module = null): array
    {
        // 自动获取或验证模块名称
        $module = $this->getModule($module);

        // 定义缓存键名
        $cacheKey = "{$module}_all_columns";

        // 从缓存中获取栏目数据，如果不存在则查询数据库并缓存
        return $this->app->cache->remember($cacheKey, 86400, function () use ($module) {
            $tableColumn = $module . '_column';
            return Db::name($tableColumn)->select()->toArray();
        });
    }

    /**
     * 清除模块所有栏目的缓存
     *
     * @param string|null $module 模块名称（可为空）
     * @return bool 是否成功清除缓存
     * @throws \Exception
     */
    public function clearAllColumnsCache(?string $module = null): bool
    {
        // 自动获取或验证模块名称
        $module = $this->getModule($module);

        $cacheKey = "{$module}_all_columns";
        return $this->app->cache->delete($cacheKey);
    }

    /**
     * 清除模块所有栏目相关的缓存（包括单个栏目、全部栏目和 id-title 映射）
     *
     * @param string|null $module 模块名称（可为空）
     * @return bool 是否成功清除缓存
     * @throws Throwable
     */
    public function clearAllColumnRelatedCache(?string $module = null): bool
    {
        // 自动获取或验证模块名称
        $module = $this->getModule($module);

        // 清除单个栏目缓存
        $allColumns = $this->getColumnsByIdTitle($module);
        foreach (array_keys($allColumns) as $columnId) {
            $this->clearColumnCache($module, $columnId);
        }

        // 清除全部栏目缓存
        $this->clearAllColumnsCache($module);

        // 清除 id-title 映射缓存
        $this->clearColumnsByIdTitleCache($module);

        return true;
    }
}
