<?php
declare(strict_types=1);

namespace mowzs\lib\module\service;

use app\service\BaseService;
use mowzs\lib\helper\ColumnCacheHelper;
use mowzs\lib\helper\ModuleFoematHelper;
use think\Exception;
use think\facade\Db;
use think\Model;

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
     * @param string|int $id
     * @return array|mixed|Model
     * @throws Exception
     */
    public function getInfo(string|int $id = '', $prev_next = false): mixed
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
        $info['column'] = ColumnBaseService::instance([$this->getModule()])->getInfo($info['cid']);
        $info['tags'] = TagBaseService::instance([$this->getModule()])->getTagInfoListByAid($info['id']);
        if ($prev_next) {
            $info['prev_info'] = $this->model->where('id', '<', $id)->findOrEmpty()->toArray();
            $info['next_info'] = $this->model->where('id', '>', $id)->findOrEmpty()->toArray();
        }
        $info['module_dir'] = $this->getModule();
        return ModuleFoematHelper::instance()->content($info);
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
     * @param
     * array $params 包含查询条件的参数数组
     * @return
     * \think\Collection|\think\model\Collection|\think\Paginator
     * @throws
     * Exception
     * @throws
     * DbException
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
            throw new Exception('mid不能为空！');
        }

        // 构建表名
        $content = $this->getModule() . '_content_' . $options['mid'];
        $contents = $this->getModule() . '_content_' . $options['mid'] . 's';

        // 开始构建查询
        $query = Db::view($content)->view($contents, 'content', "{$contents}.id={$content}.id", 'LEFT');

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
        $query->order($options['order'], $options['by']);

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

        $this->formatData($return);

        return $return;
    }

    /**
     * 格式化查询结果，添加分类和标签信息
     *
     * @param \think\Collection|\think\Paginator $data 查询结果对象
     * @return void
     */
    protected function formatData(\think\Collection|\think\Paginator &$data): void
    {
        try {
            $column_data = ColumnCacheHelper::instance()->getColumnsByIdTitle($this->getModule());
        } catch (\Throwable $e) {
            $column_data = [];
        }
        // 获取标签表和标签关联表的名称
        $table_tag = $this->getModule() . '_tag';
        $table_tag_info = $this->getModule() . '_tag_info';
        // 遍历每个 item，追加分类和标签信息
        $data->each(function ($item) use ($table_tag, $table_tag_info, $column_data) {
            // 添加分类标题
            $item['column_title'] = $column_data[$item['cid']] ?? '未知分类';
            // 获取与当前 content 记录相关的 tag_info 和 tag 记录
            $tags = Db::view($table_tag_info, 'tid')
                ->view($table_tag, 'title', "{$table_tag}.id = {$table_tag_info}.tid")
                ->where('aid', $item['id'])  // 使用 aid 关联 content 表
                ->column('title', 'tid');  // 获取 tag 的 title 字段
            // 将标签信息追加到 item 中
            $item['tags'] = $tags;  // 如果没有标签，设置为空字符串
            $item['module_dir'] = $this->getModule();
            $item = ModuleFoematHelper::instance()->content($item);
            return $item;
        });
    }
}
