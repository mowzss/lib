<?php
declare(strict_types=1);

namespace mowzs\lib\module\service;

use app\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\Exception;
use think\Model;
use think\Paginator;

class TagBaseService extends BaseService
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
        $this->model = $this->getModel($this->table);
        $this->contentModel = $this->getModel($this->contentTable);
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
        $info = $this->model->findOrEmpty($tid);
        if ($info->isEmpty()) {
            throw new Exception('标签不存在！');
        }
        return $info->toArray();
    }

    /**
     * 通过内容id获取tag
     * @param string $aid
     * @return array|void
     */
    public function getTagInfoListByAid(string $aid = '')
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
        $query = $this->getDbQuery($this->infoTable)->where(['tid' => $tid]);
        if (!empty($mid)) {
            $query = $query->where(['mid' => $mid]);
        }
        return $query->field('aid')->paginate($rows)->each(function ($item) {
            $item = ContentBaseService::instance([$this->getModule()])->getInfo($item['aid']);
            return $item;
        });
    }


}
