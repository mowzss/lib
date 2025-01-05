<?php

declare (strict_types=1);

namespace mowzs\lib\taglib\extends;

use mowzs\lib\helper\ColumnCacheHelper;
use mowzs\lib\helper\ModuleFoematHelper;
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
        $this->module = $module;
        $config['pagenum'] = !empty($config['pagenum']) ? $config['pagenum'] : $this->request->param('page');
        $config['rows'] = $config['rows'] ?? 20;
        $config['page'] = $config['page'] ?? false;
        if (!empty($config['where'])) {
            $config['where'] = $this->parseWhereArray($config['where']);
        }
        if (!empty($config['whereor'])) {
            $config['whereor'] = $this->parseWhereArray($config['whereor']);
        }
        if (!isset($config['mid'])) {
            $config['mid'] = null;
        }
        if (empty($config['status'])) {
            $config['status'] = 1;
        }
        $by = '';
        if (!preg_match('/( asc| desc)$/i', $config['order'])) {
            $by = $config['by'] ?? 'desc';
        }
        $name = $config['name'];

        $cacheName = 'tpl_list_' . $name . '_' . $module . '_' . md5(json_encode($config));

        $return = cache($cacheName);
        if (empty($return) || $config['cache'] == -1) {


            $list = $this->setDbQuery($module, $config['mid']);
            if (!empty($config['cid'])) {
                $config['cid'] = $this->getCateSons($module, $config['cid']);
                $list->whereIn('cid', $config['cid']);
            }
            if (!empty($config['status'])) {
                $list->where(['status' => 1]);
            } else {
                $list->where(['status' => 0]);
            }
            if (!empty($config['week'])) {
                $list->whereWeek('create_time');
            }
            if (!empty($config['month'])) {
                $list->whereMonth('create_time');
            }
            if (!empty($config['where']) && is_array($config['where'])) {
                $list->where($config['where']);
            }
            if (!empty($config['whereor']) && is_array($config['whereor'])) {
                $list->whereOr($config['whereor']);
            }
            if (!empty($config['rows']) && empty($config['page'])) {
                $list->limit($config['rows']);
            }
            if (stristr($config['order'], 'rand()')) {
                $list->orderRaw('rand()');
            } elseif (!empty($config['order'])) {
                $list->order($config['order'], $by);
            }
            if (!empty($config['page'])) {
                $pageConfig = [
                    'page' => $config['pagenum'],
                    'list_rows' => $config['rows'],
                    'query' => $this->request->get(),
                ];
                $return = $list->paginate($pageConfig);
            } else {
                $return = $list->select();
            }
            $this->formatData($return);
            dump($return);
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
