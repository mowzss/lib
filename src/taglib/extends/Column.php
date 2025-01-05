<?php

namespace mowzs\lib\taglib\extends;

use mowzs\lib\helper\DataHelper;
use mowzs\lib\taglib\TaglibBase;

class Column extends TaglibBase
{

    public function Taglib(string $module, mixed $config): mixed
    {
        $this->module = $module;
        if (!empty($config['where'])) {
            $config['where'] = $this->parseWhereArray($config['where']);
        }
        if (!empty($config['whereor'])) {
            $config['whereor'] = $this->parseWhereArray($config['whereor']);
        }
        if (empty($config['order'])) {
            $config['order'] = "sort";
        }
        $by = '';
        if (!preg_match('/( asc| desc)$/i', $config['order'])) {
            $by = $config['by'] ?? 'desc';
        }
        $name = $config['name'];

        $cacheName = 'tpl_cata_' . $name . '_' . $module . '_' . md5(json_encode($config));
        $return = $this->app->cache->get($cacheName);

        if (empty($return) || $config['cache'] == -1) {
            $modelName = ucfirst(strtolower($module)) . ucfirst(strtolower('cate'));
            $list = $this->app->db->name($modelName)->where(['deleted' => 0]);
            if (!empty($config['status'])) {
                $list->where(['status' => $config['status']]);
            }
            if (!empty($config['where']) && is_array($config['where'])) {
                $list->where($config['where']);
            }
            if (!empty($config['whereor']) && is_array($config['whereor'])) {
                $list->where($config['whereor']);
            }
            if (!empty($config['rows'])) {
                $list->limit($config['rows']);
            }
            if (stristr($config['order'], 'rand()')) {
                $list->orderRaw('rand()');
            } else {
                $list->order($config['order'], $by);
            }

            $return = DataHelper::instance()->arrToTree($list->select()->toArray());
            if ($config['cache'] != -1) {
                $this->app->cache->set($cacheName, $return, $config['cache']);
            }
        }
        return $return;
    }
}
