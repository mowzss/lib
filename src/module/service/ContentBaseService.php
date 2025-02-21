<?php
declare(strict_types=1);

namespace mowzs\lib\module\service;

use app\service\BaseService;
use mowzs\lib\helper\ColumnCacheHelper;
use mowzs\lib\helper\ModuleFoematHelper;
use think\Collection;
use think\db\exception\DbException;
use think\Exception;
use think\facade\Db;
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
     * 更新字段
     * @param int|string $id
     * @param string $field
     * @param int $step
     * @param int|string $mid
     * @return bool
     */
    public function updateInc(int|string $id, string $field = '', int $step = 1, int|string $mid = 0): bool
    {
        if (empty($mid)) {
            $mid = $this->model->where(['id' => $id])->value('mid');
        }
        try {
            $where = ['id' => $id];
            $this->getDbQuery($this->table)->where($where)->inc($field, $step)->update();
            $this->getDbQuery($this->table . '_' . $mid)->where($where)->inc($field, $step)->update();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string|int $id
     * @param bool $prev_next
     * @return array
     * @throws Exception
     */
    public function getInfo(string|int $id = '', bool $prev_next = false): array
    {
        if (empty($id)) {
            throw new Exception('内容ID不能为空');
        }
        $mid = $this->model->where(['id' => $id])->value('mid');
        if (empty($mid)) {
            throw new Exception('内容不存在');
        }
        $info = $this->getInfoByMid($id, $mid);
        if ($prev_next && !empty($info)) {
            $info['prev_info'] = $this->model->where([['cid', '=', $info['cid']], ['id', '<', $id]])->findOrEmpty()->toArray();
            $info['next_info'] = $this->model->where([['cid', '=', $info['cid']], ['id', '>', $id]])->findOrEmpty()->toArray();
        }
        $info['module_dir'] = $this->getModule();
        return ModuleFoematHelper::instance()->content($info);
    }

    /**
     * @param array $options
     * @return Collection|\think\model\Collection|Paginator
     * @throws DbException
     */
    public function getListByMid(array $options): Paginator|Collection|\think\model\Collection
    {
// 构建表名
        $content = $this->getModule() . '_content_' . $options['mid'];
        $contents = $this->getModule() . '_content_' . $options['mid'] . 's';
        // 开始构建查询
        $query = Db::name($content);
        // 添加 where 条件
        if (!empty($options['where'])) {
            $query->where($options['where']);
        }

        // 添加 orWhere 条件
        if (!empty($options['whereor'])) {
            foreach ($options['whereor'] as $orCondition) {
                $query->whereOr($orCondition);
            }
        }
        // 排序
        $query->order($options['order'], $options['by'])->whereNull('delete_time');

        // 如果是分页查询
        if ($options['paginate']) {
            $return = $query
                ->paginate([
                    'list_rows' => $options['rows'],
                    'page' => request()->param('page') ?: 1, // 获取当前页码，默认第一页
                    'query' => request()->get(),
                ]);
        } else {
            // 不是分页查询，则限制查询条数
            $return = $query->limit($options['rows'])->select();
        }
        $content_data = $this->getDbQuery($contents)->whereIn('id', $return->column('id'))
            ->column('content', 'id');
        $tags = TagBaseService::instance([$this->getModule()])->getTagInfoListByAids($return->column('id'))->toArray();
        $return->each(function ($item) use ($content_data, $tags) {
            foreach ($content_data as $id => $content) {
                if ($id == $item['id']) {
                    $item['content'] = $content;
                } else {
                    $item['content'] = '';
                }

            }
            $item['tags'] = [];
            foreach ($tags as $tag) {
                if ($tag['aid'] == $item['id']) {
                    $item['tags'][] = $tag;
                }
            }
            return $item;
        });
        $this->formatData($return);
        return $return;
    }


    /**
     * 通过id和mid获取内容
     * @param $id
     * @param $mid
     * @return array
     * @throws Exception
     */
    protected function getInfoByMid($id, $mid): array
    {
        $info = $this->model->suffix("_{$mid}")->findOrEmpty($id);
        if ($info->isEmpty()) {
            throw new Exception('内容没找到！');
        }
        $info = $info->toArray();
        $info['content'] = $this->getContent($info);
        $info['column'] = ColumnBaseService::instance([$this->getModule()])->getInfo($info['cid']);
        $info['tags'] = TagBaseService::instance([$this->getModule()])->getTagInfoListByAid($info['id']);
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
     * 获取列表数据
     *
     * @param array $params
     * @return Collection|\think\model\Collection|Paginator \think\Collection|\think\model\Collection|\think\Paginator
     * \think\Collection|\think\model\Collection|\think\Paginator
     * @throws Exception
     * @throws DbException
     */
    public function getList(array $params = []): \think\Collection|\think\model\Collection|\think\Paginator
    {
        // 设置默认参数
        $defaults = [
            'mid' => 0,
            'where' => [],
            'order' => 'id',
            'by' => 'desc',
            'paginate' => false,
            'rows' => 10,
            'whereor' => []
        ];
        // 合并默认参数与传入的参数，并以传入的参数优先
        $options = array_merge(
            $defaults,
            $params
        );
        // 检查mid是否为空
        if (empty($options['mid'])) {
            return $this->getListNoMid($options);
        }
        return $this->getListByMid($options);
    }

    /**
     * 未指定mid时 获取全部数据列表
     * @param $params
     * @return Collection
     * @throws DbException
     */
    protected function getListNoMid($params)
    {
        // 主表支持的字段列表
        $supportedFields = ['uid', 'cid', 'view', 'status', 'list', 'create_time', 'update_time'];
        $_where = [];
        $_where_or = [];
        foreach ($params['where'] as $condition) {
            // 检查条件中的字段是否在支持的字段列表中
            if (in_array($condition[0], $supportedFields)) {
                // 如果字段被支持，添加到筛选后的数组中
                $_where[] = $condition;
            }
        }
        foreach ($params['whereor'] as $condition) {
            // 检查条件中的字段是否在支持的字段列表中
            if (in_array($condition[0], $supportedFields)) {
                // 如果字段被支持，添加到筛选后的数组中
                $_where_or[] = $condition;
            }
        }
        // 开始构建查询
        $query = $this->getDbQuery($this->table);

        // 添加 where 条件
        if (!empty($_where)) {
            $query = $query->where($_where);
        }

        // 添加 orWhere 条件
        if (!empty($_where_or)) {
            foreach ($_where_or as $orCondition) {
                $query = $query->whereOr($orCondition);
            }
        }

        // 排序
        $query = $query->order($params['order'], $params['by'])->field('mid,id');

        // 如果是分页查询
        if ($params['paginate']) {
            $return = $query
                ->paginate([
                    'list_rows' => $params['rows'],
                    'page' => request()->param('page') ?: 1, // 获取当前页码，默认第一页
                    'query' => request()->get(),
                ]);
        } else {
            // 不是分页查询，则限制查询条数
            $return = $query->limit($params['rows'])->select();
        }
        $mids = [];
        foreach ($return->toArray() as $item) {
            // 使用 mid 作为键值来分组
            $mid = $item['mid'];
            if (!isset($mids[$mid])) {
                $mids[$mid] = ['mid' => $mid, 'ids' => []];
            }
            $mids[$mid]['ids'][] = $item['id'];
        }
        $data = [];
        foreach ($mids as $key => $item) {
            $params['where'][] = ['id', 'in', $item['ids']];
            $params['mid'] = $item['mid'];
            $data = array_merge($data, $this->getListByMid($params)->toArray());
        }
        return $return->intersect($data, 'id')->each(function ($item) use ($data) {
            foreach ($data as $value) {
                if ($item['id'] == $value['id']) {
                    return $value;
                }
            }
            return $item;
        });
    }


    /**
     * 格式化查询结果，添加分类和标签信息
     *
     * @param Collection|Paginator $data 查询结果对象
     * @return void
     * @throws \Throwable
     */
    protected function formatData(\think\Collection|\think\Paginator &$data): void
    {
        try {
            $column_data = ColumnCacheHelper::instance()->getColumnsByIdTitle($this->getModule());
        } catch (\Throwable $e) {
            $column_data = [];
        }
        // 遍历每个 item，追加分类和标签信息
        $data->each(function ($item) use ($column_data) {
            // 添加分类标题
            $item['column_title'] = $column_data[$item['cid']] ?? '未知分类';
            $item['module_dir'] = $this->getModule();
            $item = ModuleFoematHelper::instance()->content($item);
            return $item;
        });
    }
}
