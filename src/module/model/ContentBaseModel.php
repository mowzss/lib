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
     * 新增内容
     * @param $data
     * @return false|int|string
     * @throws Exception
     */
    public function saveContent($data): bool|int|string
    {
        if (empty($data['mid'])) {
            throw new Exception('模型id不能为空');
        }
        $newId = $this->insertGetId($data);
        $data['id'] = $newId;
        if ($data['create_time']) {
            $data['create_time'] = time();
        }
        if ($data['update_time']) {
            $data['update_time'] = time();
        }
        if ($data['list']) {
            $data['list'] = time();
        }
        $this->suffix("_{$data['mid']}")->insert($data);
        $this->suffix("_{$data['mid']}s")->insert($data);
        return $newId;
    }

    /**
     * @param $data
     * @return bool
     */
    public function editContent($data): bool
    {
        if (empty($data['id'])) {
            return false;
        }
        $where = ['id' => $data['id']];
        if (empty($data['update_time'])) {
            $data['update_time'] = time();
        }
        if (empty($data['list'])) {
            $data['list'] = time();
        }
        $this->where($where)->update($data);
        $this->suffix("_{$data['mid']}")->where($where)->update($data);
        $this->suffix("_{$data['mid']}s")->where($where)->update($data);
        return true;
    }

    /**
     * @param string $id
     * @return array|mixed|\think\Model|\think\model\contract\Modelable
     * @throws Exception
     */
    public function getInfo(string $id = ''): mixed
    {
        if (empty($id)) {
            throw new Exception('内容ID不能为空');
        }
        $mid = $this->where(['id' => $id])->value('mid');
        if (empty($mid)) {
            throw new Exception('内容不存在');
        }
        $info = $this->suffix("_{$mid}")->findOrEmpty($id);
        if ($info->isEmpty()) {
            throw new Exception('内容没找到！');
        }
        $info = $info->toArray();
        $info['content'] = $this->suffix("_{$mid}s")->value('content');
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
     * 更新字段
     * @param int|string $id
     * .
     * @param string $field
     * @param int $step
     * @param int|string $mid
     * @return void
     */
    public function updateInc(int|string $id, string $field = '', int $step = 1, int|string $mid = 0): void
    {
        if (empty($mid)) {
            $mid = $this->getMidByID($id);
        }
        $where = ['id' => $mid];
        $this->where($where)->inc($field, 1)->save();
        $this->suffix("_{$mid}")->where($where)->update([$field => $id]);
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
