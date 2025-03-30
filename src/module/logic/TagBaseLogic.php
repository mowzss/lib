<?php
declare(strict_types=1);

namespace mowzs\lib\module\logic;

use app\logic\BaseLogic;
use mowzs\lib\helper\UserHelper;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\facade\Db;
use think\Model;
use think\Paginator;

class TagBaseLogic extends BaseLogic
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
     * 当前服务操作主表
     * @var string
     */
    protected string $table;
    /**
     * tag 关联信息表
     * @var string
     */
    protected string $infoTable;
    /**
     * 内容表
     * @var string
     */
    protected string $contentTable;
    /**
     * 内容模型
     * @var Model
     */
    protected Model $contentModel;
    protected Model $tagInfoModel;

    /**
     * @return void
     * @throws Exception
     */
    protected function initialize(): void
    {
        $this->modelName = $this->getModule();
        $this->table = $this->modelName . '_tag';
        $this->infoTable = $this->modelName . '_tag_info';
        $this->contentTable = $this->modelName . '_content';
        $this->contentModel = $this->getModel($this->contentTable);
        $this->tagInfoModel = $this->getModel($this->infoTable);
    }

    /**
     * @return Model
     * @throws Exception
     */
    public function tagModel(): Model
    {
        return $this->getModel($this->table);
    }

    /**
     * @return Model
     * @throws Exception
     */
    public function tagInfoModel()
    {
        return $this->getModel($this->table)->setSuffix('_info');
    }

    /**
     * 获取tag详情
     * @param int $tid
     * @return array
     * @throws Exception
     */
    public function getInfo(int $tid = 0): array
    {
        if (empty($tid)) {
            throw new Exception('标签ID不能为空');
        }
        $info = $this->tagModel()->findOrEmpty($tid);
        if ($info->isEmpty()) {
            throw new Exception('标签不存在！');
        }
        return $info->toArray();
    }

    /**
     * 通过名称获取tagID
     * @param string $title
     * @return int|mixed|string
     * @throws Exception
     */
    public function getTagIdByTitle(string $title = ''): mixed
    {
        $id = $this->tagModel()->where('title', $title)->value('id');
        if (empty($id)) {
            $id = $this->tagModel()->insertGetId(['title' => $title, 'status' => 0, 'list' => time(), 'uid' => UserHelper::instance()->getUserId('0')]);
        }
        return $id;
    }

    /**
     * 通过内容id获取tag
     * @param string|int $aid
     * @return array|void
     */
    public function getTagInfoListByAid(string|int $aid = '')
    {
        try {
            return $this->app->db->view($this->table, 'id,title')
                ->view($this->infoTable, 'aid', $this->infoTable . '.tid=' . $this->table . '.id')
                ->where('aid', $aid)
                ->select()->each(function ($item) {
                    $item['url'] = urls($this->getModule() . '/tag/show', ['id' => $item['id']]);
                    return $item;
                })->toArray();
        } catch (DataNotFoundException|DbException $e) {
            new  exception($e);
        }
    }

    /**
     * 通过内容id获取tag
     * @param array $aids
     * @return Collection|\think\model\Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTagInfoListByAids(array $aids = []): \think\model\Collection|\think\Collection
    {
        return Db::view($this->infoTable, 'aid,tid')
            ->view($this->table, 'title', $this->infoTable . '.tid=' . $this->table . '.id')
            ->whereIn('aid', $aids)
            ->select()->each(function ($item) {
                $item['url'] = urls($this->getModule() . '/tag/show', ['id' => $item['tid']]);
                return $item;
            });
    }

    /**
     * @param int|string $tid
     * @param int|string $mid
     * @param string $order
     * @param string $by
     * @param int $rows
     * @return Paginator
     * @throws DbException
     * @throws Exception
     */
    public function getList(int|string $tid, int|string $mid = 0, string $order = 'id', string $by = 'desc', int $rows = 20): \think\Paginator
    {
        if (empty($tid)) {
            throw new Exception('TAG ID 不能为空');
        }
        $query = $this->tagInfoModel()->where(['tid' => $tid]);
        if (!empty($mid)) {
            $query = $query->where(['mid' => $mid]);
        }
        return $query->field('aid')->paginate($rows)
            ->each(function ($item) {
                $content = ContentBaseLogic::instance([$this->getModule()])->getInfo($item['aid']);
                foreach ($content as $key => $value) {
                    $item[$key] = $value;
                }
                return $item;
            });
    }

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
        $existingTids = $this->tagInfoModel()->where('aid', $data['id'])->column('tid');

        // 将现有和新的 tag 转换为集合以便进行比较
        $existingSet = array_flip($existingTids);
        $newSet = array_flip($data['tag']);

        // 找出需要移除和添加的 tid
        $toRemove = array_diff_key($existingSet, $newSet);
        $toAdd = array_diff_key($newSet, $existingSet);

        // 处理移除的标签
        foreach ($toRemove as $tid => $value) {
            // 减少 tag 表中对应的 count
            $this->tagModel()->where('id', $tid)->dec('count')->save();
            // 删除 tag_info 中的记录
            $this->tagInfoModel()->where(['aid' => $data['id'], 'tid' => $tid])->delete();
        }

        // 处理新增的标签
        foreach ($toAdd as $tid => $value) {
            // 增加 tag 表中对应的 count
            $this->tagModel()->where('id', $tid)->inc('count')->save();
            // 插入 tag_info 中的新记录
            $this->tagInfoModel()->insert(['tid' => $tid, 'mid' => $data['mid'], 'aid' => $data['id']]);
        }

        return true;
    }

    /**
     * @param string|int $aid
     * @return array|Collection|\think\model\Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTagListByAid(string|int $aid = 0): \think\model\Collection|Collection|array
    {
        // 首先检查传入的 aid 是否为空
        if (empty($aid)) {
            return [];
        }
        // 从 tag_info 表中获取与给定 aid 相关的所有 tid
        $tids = $this->tagInfoModel()
            ->where('aid', $aid)
            ->column('tid');
        // 如果没有找到任何 tid，直接返回空数组
        if (empty($tids)) {
            return [];
        }
        // 从 tag 表中获取这些 tid 对应的 title
        $return = $this->tagModel()->whereIn('id', $tids)->field('title,id')
            ->select()->each(function ($item) {
                $item['selected'] = true;
                return $item;
            });
        return $return;
    }
}
