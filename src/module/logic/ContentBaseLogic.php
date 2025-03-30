<?php
declare(strict_types=1);

namespace mowzs\lib\module\logic;

use app\logic\BaseLogic;
use mowzs\lib\helper\ColumnCacheHelper;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\facade\Db;
use think\Model;
use think\Paginator;

/**
 * 模块内容公用服务
 */
class ContentBaseLogic extends BaseLogic
{
    /**
     * 当前操作模块名
     * @var string
     */
    protected string $modelName;


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
    }

    /**
     * @return Model
     * @throws Exception
     */
    protected function ContentModel(): Model
    {
        return $this->getModel($this->table);
    }

    /**
     * 删除
     * @param mixed $data 数据或模型实例
     * @param bool $force 是否强制删除
     * @return bool
     * @throws DbException
     * @throws Exception
     * @throws DataNotFoundException
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
            Db::name("{$this->table}_{$data['mid']}")->where('id', $data['id'])->useSoftDelete('delete_time', time())->delete();
            Db::name("{$this->table}_{$data['mid']}s")->where('id', $data['id'])->useSoftDelete('delete_time', time())->delete();
        }

        return $this->ContentModel()->destroy($data['id'], $force);
    }

    /**
     * 更新字段
     * @param int|string $id
     * @param string $field
     * @param int $step
     * @param int|string $mid
     * @return bool
     * @throws Exception
     */
    public function updateInc(int|string $id, string $field = '', int $step = 1, int|string $mid = 0): bool
    {
        if (empty($mid)) {
            $mid = $this->ContentModel()->where(['id' => $id])->value('mid');
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
        $mid = $this->ContentModel()->where(['id' => $id])->value('mid');
        if (empty($mid)) {
            throw new Exception('内容不存在');
        }
        $info = $this->getInfoByMid($id, $mid);
        if ($prev_next && !empty($info)) {
            $info['prev_info'] = $this->ContentModel()->where([['cid', '=', $info['cid']], ['id', '<', $id]])->findOrEmpty()->toArray();
            $info['next_info'] = $this->ContentModel()->where([['cid', '=', $info['cid']], ['id', '>', $id]])->findOrEmpty()->toArray();
        }
        $info['module_dir'] = $this->getModule();
        return $this->formatContentData($info);
    }

    /**
     * 格式化内容数据
     * @param array $item 单组数据
     * @return array
     */
    public function formatContentData(array $item)
    {
        $item['_images'] = $item['images'] ?? '';
        $item['_image'] = $item['image'] ?? '';
        $item['images'] = [];
        if (!empty($item['_images'])) {
            $item['images'] = str2arr($item['_images']);
            $item['image'] = $item['images'][0];
        }
        $time = $item['update_time'];
        if (empty($item['update_time'])) {
            $time = $item['create_time'];
        }
        $item['time'] = format_datetime($time, 'Y-m-d');
        $item['published_time'] = format_datetime($item['create_time'], 'Y-m-d\TH:i:sP');
        $item['updated_time'] = format_datetime($item['update_time'], 'Y-m-d\TH:i:sP');
        $item['_time'] = format_time($item['create_time'], true);
        if (isset($item['view'])) {
            $item['_view'] = format_view($item['view']);
        }
        $item['_content'] = isset($item['content']) ? del_html($item['content']) : '';
        if (empty($item['description'])) {
            $item['description'] = get_word($item['_content'], 120);
        }
        if (!empty($item['module_dir'])) {
            $item['url'] = hurl("{$item['module_dir']}/content/index", ['id' => $item['id']]);
        }
        return $item;
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
        $tags = TagBaseLogic::instance([$this->getModule()])->getTagInfoListByAids($return->column('id'))->toArray();
        $return->each(function ($item) use ($content_data, $tags) {
            if (isset($content_data[$item['id']])) {
                $item['content'] = $content_data[$item['id']];
            } else {
                $item['content'] = '';
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
        $info = $this->ContentModel()->setSuffix("_{$mid}")->findOrEmpty($id);

        if ($info->isEmpty()) {
            throw new Exception('内容没找到！');
        }
        $info = $info->toArray();

        $info['content'] = $this->getContent($info);
        $info['column'] = ColumnBaseLogic::instance([$this->getModule()])->getInfo($info['cid']);
        $info['tags'] = TagBaseLogic::instance([$this->getModule()])->getTagInfoListByAid($info['id']);
        return $info;
    }

    /**
     * 获取内容字段
     * @param array $info
     * @return mixed
     * @throws Exception
     */
    protected function getContent(array $info = []): mixed
    {
        if (empty($info)) {
            return [];
        }
        $id = $info['id'];
        $mid = $info['mid'];
        return $this->ContentModel()->setSuffix("_{$mid}s")->where('id', $id)->value('content');
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
            return $this->formatContentData($item);
        });
    }

    /**
     * 新增内容
     * @param $data
     * @return int|string
     * @throws Exception
     */
    public function saveContent($data): int|string
    {
        if (empty($data['mid'])) {
            throw new Exception('模型id不能为空');
        }
        $model = $this->ContentModel()->create($data);
        if ($data['create_time']) {
            $data['create_time'] = time();
        }
        if ($data['update_time']) {
            $data['update_time'] = time();
        }
        if ($data['list']) {
            $data['list'] = time();
        }
        Db::name("{$this->table}_{$data['mid']}")->insert($data);
        Db::name("{$this->table}_{$data['mid']}s")->insert($data);
        return $model->id;
    }

    /**
     * @param $data
     * @return bool
     * @throws Exception
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
        $this->ContentModel()->where($where)->update($data);
        Db::name("{$this->table}_{$data['mid']}")->where($where)->update($data);
        Db::name("{$this->table}_{$data['mid']}s")->where($where)->update($data);
        return true;
    }
}
