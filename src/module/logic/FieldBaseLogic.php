<?php

namespace mowzs\lib\module\logic;

use mowzs\lib\BaseLogic;
use mowzs\lib\forms\FormatFieldOption;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;

class FieldBaseLogic extends BaseLogic
{
    protected ?string $modelName;
    protected string $table;

    /**
     * @return void
     * @throws Exception
     */
    protected function initialize(): void
    {
        $this->modelName = $this->getModule();
        $this->table = $this->modelName . '_field';
    }

    /**
     * @return \think\Model
     * @throws Exception
     */
    protected function model(): \think\Model
    {
        return $this->getModel($this->table);
    }

    /**
     * 获取栏目字段扩展
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getColumnFields()
    {
        $data = $this->model()->where('mid', '-1')->field('name,type,title,options,help,required')->select()->each(function ($rs) {
            $rs['label'] = $rs['title'];
            return $rs;
        });
        return $data->toArray();
    }

    /**
     * 获取模型搜索字段
     * @param $mid
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getSearchFields($mid): array
    {
        return $this->getFieldsInfoByType((int)$mid, ['select', 'radio', 'checkbox'], ['extend->search->is_open' => 1]);
    }

    /**
     * @param int $mid
     * @param array|string $fields
     * @param array $ext_where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function getFieldsInfoByType(int $mid, array|string $fields = '', array $ext_where = []): array
    {
        if (is_array($fields)) {
            $where[] = ['type', 'in', $fields];
        } else {
            $where = ['type' => $fields];
        }
        $data = $this->model()->where('mid', $mid)->where($where);
        if (!empty($ext_where)) {
            $data = $data->where($ext_where);
        }
        return $data->cache(true, 3600)->select()->toArray();
    }

    /**
     * 获取模型搜索字段key
     * @param $mid
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getSearchFieldsKey($mid): array
    {
        $data = $this->getSearchFields($mid);
        return array_column($data, 'name');
    }

    /**
     * @param $mid
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function buildFieldsUrls($mid): array
    {
        $fields = $this->getSearchFields($mid);
        $data = [];
        foreach ($fields as $key => $item) {
            $data[$key]['name'] = $item['name'];
            $data[$key]['title'] = $item['title'];
            if (!is_array($item['options'])) {
                $options = FormatFieldOption::strToArray($item['options']);
            }
            if (!empty($options) && is_array($options)) {
                $request_param = $this->request->param($item['name']);
                foreach ($options as $k => $v) {
                    $data[$key]['urls'][$k]['title'] = $v;
                    $data[$key]['urls'][$k]['url'] = url_with('', [$item['name'] => $k]);
                    if ($request_param == $k) {
                        $data[$key]['urls'][$k]['active'] = true;
                    } else {
                        $data[$key]['urls'][$k]['active'] = false;
                    }
                }
                $params = $this->request->rule()->getVars();
                $params[$item['name']] = 0;
                $urls_all = [
                    'title' => '全部',
                    'url' => urls('', $params),
                    'active' => !$request_param,
                ];
                array_unshift($data[$key]['urls'], $urls_all);
            }

        }
        return $data;
    }
}
