<?php

namespace mowzs\lib\module\service;

use app\service\BaseService;
use think\Exception;
use think\facade\Log;
use think\Model;

class ColumnBaseService extends BaseService
{
    /**
     * 当前操作模块名
     * @var string
     */
    protected string $modelName;

    /**
     * 当前模型
     * @var Model
     */
    protected Model $model;

    /**
     * 数据表
     * @var string
     */
    protected string $table;

    /**
     * @return void
     * @throws Exception
     */
    protected function initialize(): void
    {
        $this->modelName = $this->getModule();
        $this->table = $this->modelName . '_column';
        $this->model = $this->getModel($this->table);
    }

    /**
     * 获取栏目信息
     * @param string $id
     * @return array
     */
    public function getInfo(string $id = ''): array
    {
        return $this->model->findOrEmpty($id)->toArray();
    }

    /**
     * 通过id获取子类id
     * @param int $id
     * @return array 子id数组
     */
    public function getColumnSonsById(int $id): array
    {
        // 构建缓存键名
        $cacheKey = 'cate_sons_' . strtolower($this->modelName) . '_' . $id;

        try {
            // 尝试从缓存中获取数据
            $cachedData = $this->app->cache->get($cacheKey);

            if ($cachedData !== false) {
                return $cachedData;
            }
            $query = $this->app->db->name($this->table)
                ->field('id')
                ->where(function ($query) use ($id) {
                    $query->where('pid', $id)->whereOr('id', $id);
                });
            $data = $query->column('id');
            if (!empty($data)) {
                // 将查询结果存入缓存，设置缓存过期时间为1小时
                $this->app->cache->set($cacheKey, $data, 3600);
            }
            return $data;

        } catch (\Exception $e) {
            // 记录异常日志（假设有一个日志记录器）
            Log::error("Error fetching column sons for  id [{$id}]: " . $e->getMessage());
            // 根据业务需求决定是否抛出异常或返回空数组
            return [];
        }
    }
}
