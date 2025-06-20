<?php
declare(strict_types=1);

namespace mowzs\lib\taglib\extends;

use mowzs\lib\helper\DataHelper;
use mowzs\lib\taglib\TaglibBase;

class Column extends TaglibBase
{

    public function run(string $module, mixed $config): mixed
    {
        $this->module = $module;
        $sub = boolval($config['sub']);
        if (!empty($config['where'])) {
            $config['where'] = $this->parseWhereArray($config['where']);
        }
        if (!empty($config['whereor'])) {
            $config['whereor'] = $this->parseWhereArray($config['whereor']);
        }
        if (empty($config['order'])) {
            $config['order'] = "list";
        }
        $by = '';
        if (!preg_match('/( asc| desc)$/i', $config['order'])) {
            $by = $config['by'] ?? 'desc';
        }
        $name = $config['name'];

        $cacheName = 'tpl_cata_' . $name . '_' . $module . '_' . md5(json_encode($config));
        $return = $this->app->cache->get($cacheName);

        if (empty($return) || $config['cache'] == -1) {
            $table = $module . '_column';;
            $list = $this->app->db->name($table);
            if (!empty($config['status'])) {
                $list = $list->where(['status' => $config['status']]);
            }
            if (!empty($config['where']) && is_array($config['where'])) {
                $list = $list->where($config['where']);
            }
            if (!empty($config['whereor']) && is_array($config['whereor'])) {
                $list = $list->whereOr($config['whereor']);
            }
            if (!empty($config['rows'])) {
                $list = $list->limit($config['rows']);
            }
            if (stristr($config['order'], 'rand()')) {
                $list = $list->orderRaw('rand()');
            } else {
                $list = $list->order($config['order'], $by);
            }
            $list = $list->select()->each(function ($item) {
                $item['url'] = urls($this->module . '/column/index', ['id' => $item['id']]);
                return $item;
            });
            if (empty($sub)) {
                $return = $list->toArray();
            } else {
                $return = DataHelper::instance()->arrToTree($list->toArray(), 0, 'id', 'pid', 'sub');
            }
            if ($config['cache'] != -1) {
                $this->app->cache->set($cacheName, $return, $config['cache']);
            }
        }
        return $return;
    }
}
