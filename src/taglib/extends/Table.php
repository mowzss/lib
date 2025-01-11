<?php
declare(strict_types=1);

namespace mowzs\lib\taglib\extends;

use mowzs\lib\taglib\TaglibBase;

class Table extends TaglibBase
{

    public function run(string $module, mixed $config)
    {
        $config['pagenum'] = !empty($config['pagenum']) ? $config['pagenum'] : $this->request->param('page');
        $config['rows'] = $config['rows'] ?? 20;
        $config['page'] = $config['page'] ?? false;
        if (!empty($config['where'])) {
            $config['where'] = $this->parseWhereArray($config['where']);
        }
        if (!empty($config['whereor'])) {
            $config['whereor'] = $this->parseWhereArray($config['whereor']);
        }
        if (empty($config['order'])) {
            $config['order'] = "id";
        }
        $by = '';
        if (!preg_match('/( asc| desc)$/i', $config['order'])) {
            $by = $config['by'] ?? 'desc';
        }

        $name = $config['name'];

        $cacheName = 'tpl_table_' . $name . '_' . $module . '_' . md5(json_encode($config));
        $return = $this->app->cache->get($cacheName);

        if (empty($return) || $config['cache'] == -1) {
            $list = $this->app->db->connect()->name($module);
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
            } else {
                $list->order($config['order'], $by);
            }
            if (!empty($config['page'])) {
                $pageCofig = [
                    'page' => $config['pagenum'],
                    'list_rows' => $config['rows'],
                    'query' => $this->app->request->get(),
                ];
                $return = $list->paginate($pageCofig);
            } else {
                $return = $list->select()->toArray();
            }
            if ($config['cache'] != -1) {
                $this->app->cache->set($cacheName, $return, $config['cache']);
            }
        }
        return $return;
    }
}
