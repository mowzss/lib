<?php
declare (strict_types=1);

namespace mowzs\lib\module\model;

use mowzs\lib\Model;
use think\Exception;

abstract class ContentBaseModel extends Model
{

    /**
     * 新增内容
     * @param $data
     * @return false|int|string
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
     * @return void
     */
    protected function getMidByID($id)
    {

    }

    public function updataInc($id, $field = '', $mid = '')
    {
        if (empty($mid)) {

        }
        $this->where(['id' => $id])->inc('mid', $mid)->save();
    }
}
