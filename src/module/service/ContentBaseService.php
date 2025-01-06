<?php

namespace mowzs\lib\module\service;

use app\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Exception;
use think\facade\Cache;
use think\facade\Request;
use think\Model;
use think\Paginator;

/**
 * 模块内容公用服务
 */
class ContentBaseService extends BaseService
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
        $this->table = $this->modelName . '_content';
        $this->model = $this->getModel($this->table);
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
        $mid = $this->model->where(['id' => $id])->value('mid');
        if (empty($mid)) {
            throw new Exception('内容不存在');
        }
        $info = $this->model->suffix("_{$mid}")->findOrEmpty($id);
        if ($info->isEmpty()) {
            throw new Exception('内容没找到！');
        }
        $info = $info->toArray();
        $info['content'] = $this->getContent($info);
        $info['column'] = ColumnBaseService::instance()->getInfo($info['cid']);
        $info['tags'] = TagBaseService::instance()->getTagInfoListByAid($info['id']);
        return $info;
    }

    /**
     * 获取内容字段
     * @param array $info
     * @return mixed
     */
    protected function getContent(array $info = []): mixed
    {
        if (empty($info)) {
            return [];
        }
        $id = $info['id'];
        $mid = $info['mid'];
        return $this->model->suffix("_{$mid}s")->where('id', $id)->value('content');
    }

    /**
     * @return void
     */
    public function formatContent(&$data = [])
    {

    }

    /**
     * 获取内容列表
     *
     * @param int $mid 模块 ID
     * @param int $cid 栏目 ID
     * @param array $where 查询条件
     * @param array $where_or 或条件
     * @param int $limit 每页显示条数
     * @param bool|string $page 分页参数
     * @param string $name
     * @param int $cache 缓存时间（秒），-1 表示不使用缓存
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function getList(int $mid = 0, int $cid = 0, array $where = [], array $where_or = [], int $limit = 20, bool|string $page = '', $name = '', int $cache = 0): mixed
    {
        // 解析并设置查询条件
        $query = $this->buildQuery($mid, $cid, $where, $where_or);
        // 设置分页或限制条数
        if ($page) {
            $query = $this->applyPagination($query, $page, $limit);
        } else {
            $query->limit($limit)->select();
        }
        // 执行查询并获取结果
        return $this->executeQuery($query, $name, $cache);
    }

    /**
     * 构建查询条件
     *
     * @param int $mid 模块 ID
     * @param int $cid 栏目 ID
     * @param array $where 查询条件
     * @param array $where_or 或条件
     * @return Query|Model
     * @throws Exception
     */
    protected function buildQuery(int $mid, int $cid, array $where, array $where_or): Model|Query
    {

        $query = $this->model;

        // 添加栏目子栏目条件
        if ($cid) {
            $cids = $this->getColumnSons($mid, $cid);
            $query->whereIn('cid', $cids);
        }

        // 添加删除状态条件
        $query->where('deleted', 0);  // 默认只查询未删除的数据

        // 添加状态条件
        $query->where('status', 1);   // 默认只查询已审核的数据

        // 添加时间范围条件
        if (isset($where['week']) && $where['week']) {
            $query->whereWeek('create_time');
        }
        if (isset($where['month']) && $where['month']) {
            $query->whereMonth('create_time');
        }

        // 添加 where 和 whereOr 条件
        if (!empty($where)) {
            $query->where($where);
        }
        if (!empty($where_or)) {
            $query->whereOr($where_or);
        }

        return $query;
    }

    /**
     * 应用分页
     *
     * @param Query $query 查询构建器
     * @param string|bool $page 分页参数
     * @param int $limit 每页显示条数
     * @return Paginator
     * @throws DbException
     */
    protected function applyPagination(Query $query, $page, int $limit): \think\Paginator
    {
        $pageConfig = [
            'page' => $page,
            'list_rows' => $limit,
            'query' => Request::instance()->get(),
        ];

        return $query->paginate($pageConfig);
    }

    /**
     * 执行查询并处理缓存
     *
     * @param Query $query 查询构建器
     * @param $name
     * @param int $cache 缓存时间（秒），-1 表示不使用缓存
     * @return mixed
     */
    protected function executeQuery(Query $query, $name, int $cache): mixed
    {
        // 生成缓存名称
        $cacheName = 'tpl_list_' . md5(json_encode(['name' => $name, 'page_url' => $this->request->url()]));

        // 尝试从缓存中获取结果
        $result = Cache::get($cacheName);

        if (empty($result) || $cache == -1) {
            // 如果缓存不存在或禁用缓存，则执行查询
            $result = $query;

            // 如果启用了缓存，则将结果存入缓存
            if ($cache > 0) {
                Cache::set($cacheName, $result, $cache);
            }
        }

        return $result;
    }


}
