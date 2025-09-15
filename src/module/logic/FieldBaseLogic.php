<?php

namespace mowzs\lib\module\logic;

use mowzs\lib\BaseLogic;
use mowzs\lib\forms\FormatFieldOption;
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
        $where = [];
        $data = $this->model()->where('mid', $mid)->where('type', 'in', ['select', 'radio', 'checkbox'])->where('extend->search->is_open', 1)->select();
        return $data->toArray();
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
                foreach ($options as $k => $v) {
                    $data[$key]['urls'][$k]['title'] = $v;
                    $data[$key]['urls'][$k]['url'] = url_with('', [$item['name'] => $k]);
                }
            }
        }
        return $data;
    }
}
