<?php
declare (strict_types=1);

namespace mowzs\lib\module\model;

use mowzs\lib\Model;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\model\concern\SoftDelete;

abstract class ContentBaseModel extends Model
{
    use SoftDelete;

    protected string $deleteTime = 'delete_time';

    /**
     * @param string|int $id
     * @return array
     * @throws Exception
     */
    public function getInfo(string|int $id = ''): array
    {
        if (empty($id)) {
            throw new Exception('内容ID不能为空');
        }
        $mid = $this->where(['id' => $id])->value('mid');
        if (empty($mid)) {
            throw new Exception('内容不存在');
        }
        $info = $this->setSuffix("_{$mid}")->findOrEmpty($id);
        if ($info->isEmpty()) {
            throw new Exception('内容没找到！');
        }
        $info = $info->toArray();
        $info['content'] = $this->setSuffix("_{$mid}s")->where(['id' => $id])->value('content');
        return $info;
    }

    /**
     * 通过id获取mid
     * @param $id
     * @return mixed
     */
    protected function getMidByID($id): mixed
    {
        return $this->where(['id' => $id])->value('mid');
    }


    /**
     * 删除
     * @param mixed $data 数据或模型实例
     * @param bool $force 是否强制删除
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function del(mixed $data, bool $force = false): bool
    {
        // 如果$data是模型实例，则转换为数组
        if (is_object($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }
        // 确保$data是一个数组
        if (!is_array($data)) {
            throw new \InvalidArgumentException('The provided data must be an array or a model instance.');
        }
        // 检查是否存在'mid'键，并根据需要执行删除操作
        if (!empty($data['mid'])) {
            $this->setSuffix("_{$data['mid']}")->find($data['id'])->delete();
        }

        return $this->destroy($data['id'], $force);
    }
}
