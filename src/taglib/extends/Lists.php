<?php

declare (strict_types=1);

namespace mowzs\lib\taglib\extends;

use mowzs\lib\helper\ColumnCacheHelper;
use mowzs\lib\helper\ModuleFoematHelper;
use mowzs\lib\module\service\ContentBaseService;
use mowzs\lib\taglib\TaglibBase;
use think\facade\Db;

class Lists extends TaglibBase
{
    /**
     * 获取列表数据
     * @param string $module 数据表
     * @param mixed $config 配置文件
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function run(string $module, mixed $config): mixed
    {
        $params = [];
        $this->module = $module;
        $params['pagenum'] = !empty($config['pagenum']) ? $config['pagenum'] : $this->request->param('page');
        $params['rows'] = $config['rows'] ?? 20;
        $params['paginate'] = $config['page'] ?? false;
        if (!empty($config['where'])) {
            $params['where'] = $this->parseWhereArray($config['where']);
        }
        if (!empty($config['whereor'])) {
            $params['whereor'] = $this->parseWhereArray($config['whereor']);
        }
        if (isset($config['mid'])) {
            $params['mid'] = $config['mid'];
        }
        if (empty($config['status'])) {
            $params['where'][] = ['status', '=', 1];
        } else {
            $params['where'][] = ['status', '=', $config['status']];
        }
        if (!preg_match('/( asc| desc)$/i', $config['order'])) {
            $params['by'] = $config['by'] ?? 'desc';
        }
        if (stristr($config['order'], 'rand()')) {
            $params['order'] = 'rand()';
        } elseif (!empty($config['order'])) {
            $params['order'] = $config['order'];
        }
        if (!empty($config['cid'])) {
            $params['where'][] = ['cid', 'in', $config['cid']];
        }
        $name = $config['name'];

        $cacheName = 'tpl_list_' . $name . '_' . $module . '_' . md5(json_encode($params));

        $return = cache($cacheName);
        if (empty($return) || $config['cache'] == -1) {
            $return = ContentBaseService::instance([$module])->getList($params);
            if ($config['cache'] != -1) {
                $this->app->cache->set($cacheName, $return, $config['cache']);
            }
        }
        return $return;
    }

    /**
     * 格式化查询结果，添加分类和标签信息
     *
     * @param \think\Paginator|\think\Collection $data 查询结果对象
     * @return void
     */
    protected function formatData(&$data): void
    {
        try {
            $column_data = ColumnCacheHelper::instance()->getColumnsByIdTitle($this->module);
        } catch (\Throwable $e) {
            $column_data = [];
        }
        // 获取标签表和标签关联表的名称
        $table_tag = $this->module . '_tag';
        $table_tag_info = $this->module . '_tag_info';
        // 遍历每个 item，追加分类和标签信息
        $data->each(function ($item) use ($table_tag, $table_tag_info, $column_data) {
            // 添加分类标题
            $item['column_title'] = $column_data[$item['cid']] ?? '未知分类';
            // 获取与当前 content 记录相关的 tag_info 和 tag 记录
            $tags = Db::view($table_tag_info, 'tid')
                ->view($table_tag, 'title', "{$table_tag}.id = {$table_tag_info}.tid")
                ->where('aid', $item['id'])  // 使用 aid 关联 content 表
                ->column('title');  // 获取 tag 的 title 字段
            // 将标签信息追加到 item 中
            $item['tags'] = $tags;  // 如果没有标签，设置为空字符串
            $item['module_dir'] = $this->module;
            $item = ModuleFoematHelper::instance()->content($item);
            return $item;
        });
    }

    /**
     * @param string $module
     * @param int|string $mid
     * @return \think\db\Query|Db
     */
    private function setDbQuery(string $module, int|string $mid = ''): Db|\think\db\Query
    {
        $content_table = $module . '_content';
        $db = Db::name($content_table);
        //构建查询视图
        if (!empty($mid)) {
            $content_table = $module . '_content_' . $mid;
            $contents_table = $module . '_content_' . $mid . 's';
            $db = Db::view($content_table)->view($contents_table, 'content', "{$contents_table}.id={$content_table}.id", 'LEFT');
        }
        return $db;
    }
}
