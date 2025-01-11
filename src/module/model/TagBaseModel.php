<?php
declare(strict_types=1);

namespace mowzs\lib\module\model;

use mowzs\lib\Model;
use think\Collection;

abstract class TagBaseModel extends Model
{
    public function saveTagList($data): bool
    {
        if (empty($data['tag']) || empty($data['id']) || empty($data['mid'])) {
            return false;
        }

        // 确保 tag 是一个数组
        if (!is_array($data['tag'])) {
            $data['tag'] = str2arr($data['tag']);
        }

        // 获取当前所有与 aid 关联的 tid
        $existingTids = $this->suffix('_info')->where('aid', $data['id'])->column('tid');

        // 将现有和新的 tag 转换为集合以便进行比较
        $existingSet = array_flip($existingTids);
        $newSet = array_flip($data['tag']);

        // 找出需要移除和添加的 tid
        $toRemove = array_diff_key($existingSet, $newSet);
        $toAdd = array_diff_key($newSet, $existingSet);

        // 处理移除的标签
        foreach ($toRemove as $tid => $value) {
            // 减少 tag 表中对应的 count
            $this->where('id', $tid)->dec('count')->save();
            // 删除 tag_info 中的记录
            $this->suffix('_info')->where(['aid' => $data['id'], 'tid' => $tid])->delete();
        }

        // 处理新增的标签
        foreach ($toAdd as $tid => $value) {
            // 增加 tag 表中对应的 count
            $this->where('id', $tid)->inc('count')->save();
            // 插入 tag_info 中的新记录
            $this->suffix('_info')->insert(['tid' => $tid, 'mid' => $data['mid'], 'aid' => $data['id']]);
        }

        return true;
    }

    /**
     * @param string|int $aid
     * @return array|Collection|\think\model\Collection
     */
    public function getTagListByAid(string|int $aid = 0): \think\model\Collection|Collection|array
    {
        // 首先检查传入的 aid 是否为空
        if (empty($aid)) {
            return [];
        }
        // 从 tag_info 表中获取与给定 aid 相关的所有 tid
        $tids = $this->suffix('_info')
            ->where('aid', $aid)
            ->column('tid');
        // 如果没有找到任何 tid，直接返回空数组
        if (empty($tids)) {
            return [];
        }
        // 从 tag 表中获取这些 tid 对应的 title
        $return = $this->whereIn('id', $tids)->field('title,id')
            ->select()->each(function ($item) {
                $item['selected'] = true;
                return $item;
            });
        return $return;
    }
}
