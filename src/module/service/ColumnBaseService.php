<?php
declare(strict_types=1);

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
     * 通过id获取mid
     * @param $id
     * @return mixed
     */
    public function getMidById($id)
    {
        return $this->model->where('id', $id)->value('mid');
    }

    /**
     * 获取栏目信息
     * @param int|string $id
     * @return array
     * @throws Exception
     */
    public function getInfo(int|string $id = ''): array
    {
        if (empty($id)) {
            throw new Exception('栏目ID不能为空');
        }
        $info = $this->model->findOrEmpty($id);
        if ($info->isEmpty()) {
            throw new Exception('栏目不存在');
        }
        return $info->toArray();
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

            if (!empty($cachedData)) {
                return $cachedData;
            }

            // 查询数据库以获取所有子类ID
            $query = $this->app->db->name($this->table)
                ->field('id')
                ->where(function ($query) use ($id) {
                    $query->where('pid', $id)->whereOr('id', $id);
                });

            $data = $query->column('id');

            // 如果没有任何子类ID，则至少返回传入的$id
            if (empty($data)) {
                $data = [$id];
            } else if (!in_array($id, $data)) { // 确保传入的$id也在结果集中
                $data[] = $id;
            }

            // 将查询结果存入缓存，设置缓存过期时间为1小时
            $this->app->cache->set($cacheKey, $data, 3600);

            return $data;

        } catch (\Exception $e) {
            // 记录异常日志（假设有一个日志记录器）
            Log::error("Error fetching column sons for id [{$id}]: " . $e->getMessage());
            // 根据业务需求决定是否抛出异常或返回空数组
            return [$id]; // 即使发生错误，也返回传入的ID
        }
    }
}
