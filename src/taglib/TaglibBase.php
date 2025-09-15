<?php
declare(strict_types=1);

namespace mowzs\lib\taglib;

use mowzs\lib\module\logic\FieldBaseLogic;
use think\App;
use think\Container;
use think\facade\Log;

abstract class TaglibBase
{
    protected App $app;

    protected \think\Request $request;
    /**
     * 当前操作模块
     * @var string
     */
    protected string $module;

    /**
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
    }

    protected function getModel(mixed $module, string $db_name)
    {
    }

    abstract public function run(string $module, mixed $config);

    /**
     * 获取指定模块下某个分类及其子分类的ID列表
     *
     * @param string $module 模块名称
     * @param int $cid 分类ID
     * @return array|mixed 返回分类ID列表
     */
    protected function getColumnSons(string $module, int $cid): mixed
    {
        // 构建缓存键名
        $cacheKey = 'cate_sons_' . strtolower($module) . '_' . $cid;

        try {
            // 尝试从缓存中获取数据
            $cachedData = $this->app->cache->get($cacheKey);

            if ($cachedData !== false) {
                return $cachedData;
            }

            // 如果缓存中没有数据，则从数据库查询
            $tableName = strtolower($module) . '_column';
            $query = $this->app->db->name($tableName)
                ->field('id')
                ->where(function ($query) use ($cid) {
                    $query->where('pid', $cid)->whereOr('id', $cid);
                });

            $data = $query->column('id');

            if (!empty($data)) {
                // 将查询结果存入缓存，设置缓存过期时间为1小时
                $this->app->cache->set($cacheKey, $data, 3600);
            }

            return $data;

        } catch (\Exception $e) {
            // 记录异常日志
            Log::error("Error fetching column sons for module [{$module}] and cid [{$cid}]: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取当前类的实例（用于静态调用）
     *
     * @return static 返回当前类的实例
     */
    public static function getInstance(): static
    {
        return Container::getInstance()->make(static::class);
    }

    /**
     * 解析排序字段
     * @param $sortString
     * @return array
     */
    protected function parseSort($sortString): array
    {
        $sort_field_data = $this->request->param('sort');
        if (!empty($sort_field_data)) {
            $sort_array = str2arr($sortString);
            //判端sort_array数组中的值 是否包含 $sort_field_data
            if (in_array($sort_field_data, $sort_array)) {
                $by = $this->request->param('by', 'desc');
                //处理by的值只能为desc和asc
                $by = in_array($by, ['desc', 'asc']) ? $by : 'desc';
                $data = [
                    'order' => $sort_field_data,
                    'by' => $by,
                ];
            }
        }
        return $data ?? [];
    }

    protected function parseWhereArray(string $whereString): array
    {
        // 定义简写到完整操作符的映射
        $shorthandOperators = [
            '=' => '=',
            '<>' => '<>',
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<=',
            'like' => 'LIKE',
            'not like' => 'NOT LIKE',
            'between' => 'BETWEEN',
            'not between' => 'NOT BETWEEN',
            'in' => 'IN',
            'not in' => 'NOT IN',
            'is null' => 'IS NULL',
            'is not null' => 'IS NOT NULL',
            'exists' => 'EXISTS',
            'not exists' => 'NOT EXISTS',
            'regexp' => 'REGEXP',
            'not regexp' => 'NOT REGEXP',
            'between time' => 'BETWEEN TIME',
            '> time' => '> TIME',
            '< time' => '< TIME',
            '>= time' => '>= TIME',
            '<= time' => '<= TIME',
            'exp' => 'EXP',
            'find in set' => 'FIND IN SET'
        ];

        // 将简写转换为完整形式
        $whereString = preg_replace_callback(
            '/(\w+)\|([^\|]+)\|(.*)/',
            function ($matches) use ($shorthandOperators) {
                $field = $matches[1];
                $operator = strtoupper(trim($matches[2]));
                $value = $matches[3];

                // 检查是否是简写
                if (array_key_exists(strtolower($operator), $shorthandOperators)) {
                    $operator = $shorthandOperators[strtolower($operator)];
                }

                return "$field|$operator|$value";
            },
            $whereString
        );

        $conditions = [];
        $parts = explode('&', $whereString);

        foreach ($parts as $part) {
            $condition = explode('|', $part, 3); // 最多分割成3个部分

            if (count($condition) !== 3) {
                continue; // 忽略不完整的条件
            }

            list($field, $operator, $value) = $condition;

            // 标准化操作符并处理特殊操作符
            switch (strtoupper($operator)) {
                case '=':
                    $conditions[] = [$field, '=', $value];
                    break;
                case '<>':
                    $conditions[] = [$field, '<>', $value];
                    break;
                case '>':
                    $conditions[] = [$field, '>', $value];
                    break;
                case '>=':
                    $conditions[] = [$field, '>=', $value];
                    break;
                case '<':
                    $conditions[] = [$field, '<', $value];
                    break;
                case '<=':
                    $conditions[] = [$field, '<=', $value];
                    break;
                case 'LIKE':
                    $conditions[] = [$field, 'like', '%' . $value . '%'];
                    break;
                case 'NOT LIKE':
                    $conditions[] = [$field, 'not like', '%' . $value . '%'];
                    break;
                case 'BETWEEN':
                    $values = explode(',', $value);
                    if (count($values) === 2) {
                        $conditions[] = [$field, 'between', $values];
                    }
                    break;
                case 'NOT BETWEEN':
                    $values = explode(',', $value);
                    if (count($values) === 2) {
                        $conditions[] = [$field, 'not between', $values];
                    }
                    break;
                case 'IN':
                    $values = explode(',', $value);
                    $conditions[] = [$field, 'in', $values];
                    break;
                case 'NOT IN':
                    $values = explode(',', $value);
                    $conditions[] = [$field, 'not in', $values];
                    break;
                case 'IS NULL':
                    $conditions[] = [$field, 'is null'];
                    break;
                case 'IS NOT NULL':
                    $conditions[] = [$field, 'is not null'];
                    break;
                case 'EXISTS':
                    $conditions[] = [$field, 'exists', $value];
                    break;
                case 'NOT EXISTS':
                    $conditions[] = [$field, 'not exists', $value];
                    break;
                case 'REGEXP':
                    $conditions[] = [$field, 'regexp', $value];
                    break;
                case 'NOT REGEXP':
                    $conditions[] = [$field, 'not regexp', $value];
                    break;
                case 'BETWEEN TIME':
                    $values = explode(',', $value);
                    if (count($values) === 2) {
                        $conditions[] = [$field, 'between time', $values];
                    }
                    break;
                case '> TIME':
                    $conditions[] = [$field, '>', strtotime($value)];
                    break;
                case '< TIME':
                    $conditions[] = [$field, '<', strtotime($value)];
                    break;
                case '>= TIME':
                    $conditions[] = [$field, '>=', strtotime($value)];
                    break;
                case '<= TIME':
                    $conditions[] = [$field, '<=', strtotime($value)];
                    break;
                case 'EXP':
                    $conditions[] = [$field, 'exp', $value];
                    break;
                case 'FIND IN SET':
                    $conditions[] = [$field, 'find in set', $value];
                    break;
                default:
                    // 如果是未知的操作符，尝试将其作为自定义表达式
                    $conditions[] = [$field, 'exp', "$field $operator $value"];
                    break;
            }
        }

        return $conditions;
    }

    /**
     * @param $mid
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getModelWhere($mid): array
    {
        $fields = FieldBaseLogic::instance()->getSearchFieldsKey($mid);
        $where = [];
        foreach ($fields as $field) {
            $get_data = $this->request->param($field);
            if (!empty($get_data)) {
                $where[] = [$field, '=', $get_data];
            }
        }
        return $where;
    }

    /**
     * 合并多个 WHERE 条件数组，并根据字段名去重（后面的覆盖前面的）
     *
     * @param array ...$conditions 一个或多个条件数组，每个元素为 [field, operator, value]
     * @return array 合并并去重后的索引数组
     *
     * 示例：
     *   mergeWhereConditions($where, $params['where']);
     */
    public function mergeWhereConditions(...$conditions): array
    {
        $merged = [];

        // 遍历所有传入的条件数组
        foreach ($conditions as $conditionGroup) {
            if (!is_array($conditionGroup)) {
                continue;
            }
            foreach ($conditionGroup as $cond) {
                if (is_array($cond) && count($cond) >= 3) {
                    $field = $cond[0];
                    $merged[$field] = $cond; // 后面的字段覆盖前面的
                }
            }
        }

        // 返回重新索引的数组
        return array_values($merged);
    }
}
