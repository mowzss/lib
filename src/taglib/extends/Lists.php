<?php

namespace mowzs\lib\taglib\extends;

use mowzs\lib\taglib\TabLibs;

class Lists extends TabLibs
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
    public function run(mixed $config): mixed
    {
        $module = $config['module'];
        $config['pagenum'] = !empty($config['pagenum']) ? $config['pagenum'] : $this->request->param('page');
        $config['del'] = !empty($config['del']) ? $config['del'] : false;
        $config['rows'] = $config['rows'] ?? 20;
        $config['page'] = $config['page'] ?? false;
        if (!empty($config['where'])) {
            $config['where'] = $this->parseWhereArray($config['where']);
        }
        if (!empty($config['whereor'])) {
            $config['whereor'] = $this->parseWhereArray($config['whereor']);
        }
        $by = '';
        if (!preg_match('/( asc| desc)$/i', $config['order'])) {
            $by = $config['by'] ?? 'desc';
        }
        $name = $config['name'];

        $cacheName = 'tpl_list_' . $name . '_' . $module . '_' . md5(json_encode($config));

        $return = cache($cacheName);
        if (empty($return) || $config['cache'] == -1) {
            $db_name = ucfirst($module) . 'Content';
            $list = $this->getModel($module, $db_name)->with(['content']);
            if (!empty($config['cid'])) {
                $config['cid'] = $this->getColumnSons($module, $config['cid']);
                $list->whereIn('cid', $config['cid']);
            }
            if (!empty($config['del'])) {
                $list->where(['deleted' => 1]);
            } else {
                $list->where(['deleted' => 0]);
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
            if ($config['cache'] != -1) {
                $this->cache->set($cacheName, $return, $config['cache']);
            }
        }
        return $return;
    }


}
