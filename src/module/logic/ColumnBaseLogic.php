<?php
declare(strict_types=1);

namespace mowzs\lib\module\logic;

use mowzs\lib\BaseLogic;
use think\Exception;
use think\Model;

class ColumnBaseLogic extends BaseLogic
{
    /**
     * @return Model
     * @throws Exception
     */
    public function columnModel(): Model
    {
        return $this->getModel($this->getModule() . '_column');
    }

    /**
     * 通过id获取mid
     * @param int|string $id
     * @return mixed
     * @throws \Throwable
     */
    public function getMidById(int|string $id): mixed
    {
        $key = $this->getModule() . '_column_mid_by_id_' . $id;
        return $this->app->cache->remember($key, function () use ($id) {
            return $this->columnModel()->where('id', $id)->value('mid');
        }, mt_rand(3000, 9000));

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
        $info = $this->columnModel()->findOrEmpty($id);
        if ($info->isEmpty()) {
            throw new Exception('栏目不存在');
        }
        return $info->toArray();
    }

    /**
     * 通过id获取子类id
     * @param int|string $id
     * @return array 子id数组
     * @throws \Throwable
     */
    public function getColumnSonsById(int|string $id): array
    {
        // 构建缓存键名
        $sons_ids = $this->columnModel()->where(function ($query) use ($id) {
            $query->where('pid', $id)->whereOr('id', $id);
        })->column('id');
        if (empty($sons_ids)) {
            return [$id];
        }
        return $sons_ids;
    }
}
