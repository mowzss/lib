<?php

declare (strict_types=1);


namespace happy\admin\libs\taglib\extends;

use happy\admin\libs\taglib\TaglibBase;
use mowzs\cms\logic\ColumnBaseLogic;
use mowzs\cms\logic\ContentBaseLogic;

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
        //条件
        if (!empty($config['where'])) {
            $params['where'] = $this->parseWhereArray($config['where']);
        }
        //或条件
        if (!empty($config['whereor'])) {
            $params['whereor'] = $this->parseWhereArray($config['whereor']);
        }
        //状态
        //未传 status 时默认只取正常数据；显式传 status="0" 时应能查询禁用数据
        $status = $config['status'] ?? 1;
        $params['where'][] = ['status', '=', is_numeric($status) ? (int)$status : 1];
        //排序方式
        if (!preg_match('/( asc| desc)$/i', $config['order'])) {
            $params['by'] = $config['by'] ?? 'desc';
        }
        //排序字段
        if (stripos($config['order'], 'rand()') !== false) {
            $params['order'] = 'rand()';
        } elseif (!empty($config['order'])) {
            $params['order'] = $config['order'];
        }
        //筛选模型
        if (!empty($config['mid'])) {
            $params['mid'] = $config['mid'];
        } else {
            //指定cid的情况下 可以通过cid获取mid
            if (!empty($config['cid'])) {
                $params['mid'] = ColumnBaseLogic::instance([$module], true)->getMidById($config['cid']);
            }
        }
        //筛选栏目
        if (!empty($config['cid'])) {
            $config['cid'] = ColumnBaseLogic::instance([$module], true)->getColumnSonsById($config['cid']);
            $params['where'][] = ['cid', 'in', $config['cid']];
        }
        //筛选字段
        if (!empty($config['filter'])) {
            $where = $this->getModelWhere($config['mid']);
            $params['where'] = $this->mergeWhereConditions($params['where'], $where);
        }
        //自定义排序
        if (!empty($config['sort_field'])) {
            $data = $this->parseSort($config['sort_field']);
            if (!empty($data)) {
                $params['order'] = $data['order'];
                $params['by'] = $data['by'];
            }
        }
        if (!empty($config['field'])) {
            $params['field'] = $config['field'];
        }
        //正文加载控制
        if (isset($config['content_length'])) {
            $params['content_length'] = (int)$config['content_length'];
        }
        if (isset($config['with_content'])) {
            $params['with_content'] = (bool)$config['with_content'];
        }
        if (isset($config['with_tags'])) {
            $params['with_tags'] = (bool)$config['with_tags'];
        }
        $name = $config['name'];

        $cacheName = 'tpl_list_' . $name . '_' . $module . '_' . md5(json_encode($params) . json_encode($config));
        $return = cache($cacheName);
        if (empty($return) || $config['cache'] == -1) {
            $return = ContentBaseLogic::instance([$module], true)->getList($params);
            if ($config['cache'] != -1) {
                $this->app->cache->set($cacheName, $return, $config['cache']);
            }
        }
        return $return;
    }
}
