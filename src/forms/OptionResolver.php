<?php
declare (strict_types=1);


namespace happy\admin\libs\forms;

use think\facade\Db;
use think\facade\Log;
use think\Collection;
use happy\admin\libs\helper\ExecutorHelper;

/**
 * 表单字段选项统一解析器
 *
 * 支持以下写法（优先级从高到低）：
 * 1. JSON 结构化配置（推荐）
 * 2. 关联数组 key => label
 * 3. 数组数据 [{id,title}] / [['value'=>1,'label'=>'是']]
 * 4. 旧的文本 key|label，一行一个
 * 5. 旧的执行器字符串 Class@method@param1,param2
 */
class OptionResolver
{
    /**
     * 兼容旧 xmSelect 配置的保留键
     */
    protected const CONFIG_KEYS = [
        'mode', 'source', 'data', 'prop', 'searchUrl', 'initUrl', 'dataInit',
        'remoteSearch', 'radio', 'multiple', 'pageSize', 'autoAdd',
        'minKeywordLength', 'showToolbar', 'cache', 'empty',
    ];
    
    /**
     * 禁止读取的表名关键字，避免字段配置越权读取后台敏感表
     */
    protected const DENY_TABLE_KEYWORDS = [
        'admin', 'user', 'system', 'config', 'auth', 'role', 'permission',
        'session', 'token', 'log', 'queue', 'job',
    ];
    
    /**
     * 请求内缓存
     * @var array
     */
    protected static array $cache = [];
    
    /**
     * 解析为 key => label，供 select / radio / checkbox 及内容列表展示使用
     * @param mixed $options
     * @param array $context
     * @return array
     */
    public static function resolveKeyValue(mixed $options = [], array $context = []): array
    {
        return self::resolve($options, $context)['options'];
    }
    
    /**
     * 解析为完整结构
     * @param mixed $options
     * @param array $context
     * @return array{options: array, data: array, prop: array, mode: string, config: array}
     */
    public static function resolve(mixed $options = [], array $context = []): array
    {
        $config = self::parseConfig($options);
        $source = $config['source'];
        $prop = self::getProp($config, $source);
        $data = [];
        
        if ($source['type'] === 'table') {
            // 传入具体值时仅查询对应记录，避免远程大数据源全表加载
            $ids = self::normalizeValue($context['value'] ?? null);
            $rows = self::queryTable($source, $config, $ids);
            $data = self::rowsToData($rows, $prop);
        } else {
            $data = self::rowsToData($config['data'], $prop);
        }
        
        $options = [];
        foreach ($data as $item) {
            $options[(string)$item[$prop['value']]] = (string)$item[$prop['name']];
        }
        
        return [
            'options' => $options,
            'data' => $data,
            'prop' => $prop,
            'mode' => $config['mode'],
            'config' => $config,
        ];
    }
    
    /**
     * 解析 XmSelect 所需数据
     * @param mixed $options
     * @param mixed $value 当前字段值
     * @return array{config: array, data: array, value: array, prop: array}
     */
    public static function resolveXmSelect(mixed $options, mixed $value): array
    {
        $config = self::parseConfig($options);
        $source = $config['source'];
        $prop = self::getProp($config, $source);
        $ids = self::normalizeValue($value);
        $data = [];
        
        if ($config['mode'] === 'remote') {
            if ($source['type'] === 'table' && $ids) {
                $rows = self::queryTable($source, $config, $ids, 200);
                $data = self::rowsToData($rows, $prop);
            } else {
                $data = self::rowsToData($config['data'], $prop);
            }
        } else {
            $data = self::resolve($options)['data'];
        }
        
        $radio = (bool)($config['radio'] ?? false);
        if (($config['multiple'] ?? null) === false) {
            $radio = true;
        }
        
        $jsConfig = [
            'remoteSearch' => $config['mode'] === 'remote' && $config['searchUrl'] !== '',
            'searchUrl' => self::buildUrl((string)$config['searchUrl']),
            'initUrl' => self::buildUrl((string)$config['initUrl']),
            'prop' => $prop,
            'radio' => $radio,
            'pageSize' => max(1, (int)($config['pageSize'] ?? 20)),
            'autoAdd' => (bool)($config['autoAdd'] ?? false),
            'minKeywordLength' => max(0, (int)($config['minKeywordLength'] ?? 0)),
            'showToolbar' => (bool)($config['showToolbar'] ?? true),
            'empty' => (string)($config['empty'] ?? ''),
            // 远程搜索时前端需要把数据源一并提交，服务端校验后查询或自动创建
            'source' => $source['type'] === 'table' ? $source : null,
        ];
        
        return [
            'config' => $jsConfig,
            'data' => $data,
            'value' => $ids,
            'prop' => $prop,
        ];
    }
    
    /**
     * 远程搜索数据表数据（供通用 ajax 接口调用）
     * @param array $source
     * @param string $keyword
     * @param int $page
     * @param int $pageSize
     * @param bool $autoAdd 关键字无匹配时是否按 create 配置自动新增
     * @return array
     */
    public static function searchTableSource(array $source, string $keyword = '', int $page = 1, int $pageSize = 20, bool $autoAdd = false): array
    {
        $table = self::resolveTableName((string)($source['table'] ?? ''));
        if ($table === '') {
            return ['data' => [], 'total' => 0, 'last_page' => 0];
        }
        $columns = self::getTableColumns($table);
        $value = (string)($source['value'] ?? 'id');
        $label = (string)($source['label'] ?? 'title');
        if (!in_array($value, $columns, true) || !in_array($label, $columns, true)) {
            Log::warning("选项数据源字段不存在: {$table}.{$value}/{$label}");
            return ['data' => [], 'total' => 0, 'last_page' => 0];
        }
        
        // 自动新增：关键字无匹配记录时按 create 配置插入
        if ($autoAdd && $keyword !== '' && !empty($source['create'])) {
            self::createOptionRow($table, $columns, $source, $keyword, $label);
        }
        
        $query = Db::table($table)->field([$value, $label]);
        self::applyWhere($query, $source['where'] ?? [], $columns);
        if ($keyword !== '') {
            $searchFields = self::getSearchFields($source, $columns, $label);
            $query->where(function ($q) use ($searchFields, $keyword) {
                foreach (array_values($searchFields) as $field) {
                    $q->whereOr($field, 'like', '%' . $keyword . '%');
                }
            });
        }
        $order = trim((string)($source['order'] ?? ''));
        $query->order($order !== '' && self::isSafeOrder($order) ? $order : $value . ' desc');
        
        $paginate = $query->paginate([
            'list_rows' => min(100, max(1, $pageSize)),
            'page' => max(1, $page),
        ])->toArray();
        
        return [
            'data' => $paginate['data'] ?? [],
            'total' => $paginate['total'] ?? 0,
            'last_page' => $paginate['last_page'] ?? 0,
            'per_page' => $paginate['per_page'] ?? $pageSize,
            'current_page' => $paginate['current_page'] ?? $page,
        ];
    }
    
    /**
     * 获取搜索字段，默认使用 label；create.field 会自动纳入搜索字段
     * @param array $source
     * @param array $columns
     * @param string $label
     * @return array
     */
    protected static function getSearchFields(array $source, array $columns, string $label): array
    {
        $search = $source['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $fields = explode(',', $search);
        } elseif (is_array($search)) {
            $fields = $search;
        } else {
            $fields = [];
        }
        if (!$fields) {
            $fields = [$label];
        }
        $create = is_array($source['create'] ?? null) ? $source['create'] : [];
        if ($create) {
            // 自动创建时关键字写入 create.field，该字段必须参与搜索，避免新增后查不到
            $fields[] = trim((string)($create['field'] ?? '')) !== '' ? (string)$create['field'] : $label;
        }
        
        $result = [];
        foreach ($fields as $field) {
            $field = trim((string)$field);
            if ($field !== '' && in_array($field, $columns, true)) {
                $result[] = $field;
            }
        }
        return array_values(array_unique($result));
    }
    
    /**
     * 按 create 配置自动新增选项记录
     * @param string $table
     * @param array $columns
     * @param array $source
     * @param string $keyword
     * @param string $label
     * @return void
     */
    protected static function createOptionRow(string $table, array $columns, array $source, string $keyword, string $label): void
    {
        $create = is_array($source['create'] ?? null) ? $source['create'] : [];
        $field = trim((string)($create['field'] ?? '')) ?: $label;
        if (!in_array($field, $columns, true)) {
            return;
        }
        $defaults = is_array($create['defaults'] ?? null) ? $create['defaults'] : [];
        
        // 唯一性条件：unique 中接收关键字的字段使用关键字，其余字段取 defaults
        $unique = $create['unique'] ?? [$field];
        if (is_string($unique)) {
            $unique = explode(',', $unique);
        }
        if (!is_array($unique) || !$unique) {
            $unique = [$field];
        }
        $where = [];
        foreach ($unique as $item) {
            $item = trim((string)$item);
            if ($item === '' || !in_array($item, $columns, true)) {
                continue;
            }
            if ($item === $field) {
                $where[$item] = $keyword;
            } elseif (array_key_exists($item, $defaults)) {
                $defaultValue = self::resolveDefaultValue($defaults[$item]);
                if (is_scalar($defaultValue)) {
                    $where[$item] = $defaultValue;
                }
            }
        }
        if (!$where) {
            return;
        }
        if (Db::table($table)->where($where)->count() > 0) {
            return;
        }
        
        $row = [];
        foreach ($defaults as $key => $defaultValue) {
            $key = (string)$key;
            if ($key === $field || !in_array($key, $columns, true)) {
                continue;
            }
            $row[$key] = self::resolveDefaultValue($defaultValue);
        }
        $row[$field] = $keyword;
        try {
            Db::table($table)->insert($row);
        } catch (\Throwable $e) {
            Log::warning("字段选项自动新增失败: {$table}: " . $e->getMessage());
        }
    }
    
    /**
     * 解析默认值占位符
     * @param mixed $value
     * @return mixed
     */
    protected static function resolveDefaultValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        return match ($value) {
            '{{time}}' => time(),
            '{{date}}' => date('Y-m-d'),
            '{{datetime}}' => date('Y-m-d H:i:s'),
            '{{admin_id}}' => self::adminValue('id'),
            '{{admin_name}}' => self::adminValue('username'),
            default => $value,
        };
    }
    
    /**
     * 读取当前登录管理员信息
     * @param string $key
     * @return mixed
     */
    protected static function adminValue(string $key): mixed
    {
        try {
            $user = session('user');
        } catch (\Throwable) {
            return null;
        }
        return is_array($user) ? ($user[$key] ?? null) : null;
    }
    
    /**
     * 标准化字段值，支持数组、JSON、逗号分隔字符串
     * @param mixed $value
     * @return array
     */
    public static function normalizeValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            $list = $value;
        } elseif (is_string($value)) {
            $trim = trim($value);
            if ($trim === '') {
                return [];
            }
            if (str_starts_with($trim, '[')) {
                $decoded = json_decode($trim, true);
                $list = is_array($decoded) ? $decoded : explode(',', $trim);
            } else {
                $list = explode(',', $trim);
            }
        } else {
            $list = [$value];
        }
        
        $result = [];
        foreach ($list as $item) {
            if (is_array($item)) {
                $item = $item['value'] ?? $item['id'] ?? null;
            } elseif (is_string($item)) {
                $item = trim($item);
            }
            if ($item === null || $item === '') {
                continue;
            }
            $result[] = $item;
        }
        return array_values(array_unique($result));
    }
    
    /**
     * 解析配置
     * @param mixed $options
     * @return array
     */
    protected static function parseConfig(mixed $options): array
    {
        if (is_string($options)) {
            $raw = trim($options);
            if ($raw === '') {
                return self::emptyConfig();
            }
            if (preg_match('/^[\[\{]/', $raw) === 1) {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    Log::warning('字段选项 JSON 解析失败: ' . $raw);
                    return self::emptyConfig();
                }
                return self::fromArray($decoded);
            }
            if (ExecutorHelper::isValidString($raw)) {
                try {
                    return self::fromArray(ExecutorHelper::execute($raw));
                } catch (\Throwable $e) {
                    Log::error('字段选项执行器异常: ' . $e->getMessage());
                    return self::emptyConfig();
                }
            }
            return self::fromLegacyText($raw);
        }
        
        if (is_array($options)) {
            return self::fromArray($options);
        }
        
        return self::emptyConfig();
    }
    
    /**
     * 数组写法解析
     * @param array $options
     * @return array
     */
    protected static function fromArray(array $options): array
    {
        if (!self::isConfigArray($options)) {
            $config = self::emptyConfig();
            $config['data'] = $options;
            return $config;
        }
        
        $config = self::emptyConfig();
        $mode = (string)($options['mode'] ?? 'local');
        if (!empty($options['remoteSearch']) || !empty($options['searchUrl']) || !empty($options['initUrl']) || !empty($options['dataInit'])) {
            $mode = 'remote';
        }
        $config['mode'] = $mode === 'remote' ? 'remote' : 'local';
        
        $source = $options['source'] ?? [];
        if (is_string($source) && $source !== '') {
            $source = ['type' => 'table', 'table' => $source];
        }
        if (is_array($source) && $source) {
            $config['source'] = self::normalizeSource($source);
        } elseif (!empty($options['data']) && is_array($options['data'])) {
            $config['source'] = ['type' => 'array', 'value' => $config['source']['value'], 'label' => $config['source']['label']];
        }
        
        if (!empty($options['data']) && is_array($options['data'])) {
            $config['data'] = $options['data'];
        }
        if ($config['source']['type'] === 'array' && !empty($config['source']['data'])) {
            $config['data'] = $config['source']['data'];
        }
        
        $config['prop'] = is_array($options['prop'] ?? null) ? $options['prop'] : [];
        $config['searchUrl'] = (string)($options['searchUrl'] ?? ($options['search']['url'] ?? ''));
        if ($config['mode'] === 'remote' && $config['searchUrl'] === '' && $config['source']['type'] === 'table') {
            // 表格数据源远程模式默认使用后台通用选项接口
            $config['searchUrl'] = 'getOptionList';
        }
        $config['initUrl'] = (string)($options['initUrl'] ?? $options['dataInit'] ?? ($options['search']['init_url'] ?? ''));
        $config['radio'] = (bool)($options['radio'] ?? false);
        $config['multiple'] = $options['multiple'] ?? null;
        $config['pageSize'] = (int)($options['pageSize'] ?? 20);
        $config['autoAdd'] = (bool)($options['autoAdd'] ?? false);
        $config['minKeywordLength'] = (int)($options['minKeywordLength'] ?? 0);
        $config['showToolbar'] = (bool)($options['showToolbar'] ?? true);
        $config['empty'] = (string)($options['empty'] ?? '');
        $config['cache'] = (int)($options['cache'] ?? 0);
        
        return $config;
    }
    
    /**
     * 旧文本写法解析：key|label 或 searchUrl|xxx 等配置行
     * @param string $raw
     * @return array
     */
    protected static function fromLegacyText(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $meta = [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // 兼容旧的 key|label|extra 写法：仅取前两段，忽略附加描述
            $parts = explode('|', $line);
            $key = trim($parts[0]);
            $label = isset($parts[1]) ? trim($parts[1]) : $key;
            if (in_array($key, ['searchUrl', 'dataInit', 'remoteSearch', 'autoAdd', 'radio', 'multiple', 'pageSize', 'minKeywordLength', 'showToolbar', 'empty'], true)) {
                $meta[$key] = $label;
            } else {
                $items[$key] = $label;
            }
        }
        
        if ($meta) {
            $options = $meta;
            if ($items) {
                $options['data'] = $items;
            }
            return self::fromArray($options);
        }
        
        return self::fromArray($items);
    }
    
    /**
     * 是否为结构化配置数组
     * @param array $options
     * @return bool
     */
    protected static function isConfigArray(array $options): bool
    {
        foreach (self::CONFIG_KEYS as $key) {
            if (array_key_exists($key, $options)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 标准化数据源
     * @param array $source
     * @return array
     */
    protected static function normalizeSource(array $source): array
    {
        $type = (string)($source['type'] ?? '');
        if ($type === '') {
            $type = !empty($source['table']) ? 'table' : 'array';
        }
        return [
            'type' => $type === 'table' ? 'table' : 'array',
            'table' => (string)($source['table'] ?? ''),
            'value' => (string)($source['value'] ?? $source['value_field'] ?? 'id'),
            'label' => (string)($source['label'] ?? $source['label_field'] ?? 'title'),
            'where' => is_array($source['where'] ?? null) ? $source['where'] : [],
            'order' => (string)($source['order'] ?? ''),
            'limit' => (int)($source['limit'] ?? 2000),
            'data' => is_array($source['data'] ?? null) ? $source['data'] : [],
            'search' => $source['search'] ?? null,
            'create' => is_array($source['create'] ?? null) ? $source['create'] : [],
        ];
    }
    
    /**
     * 空配置
     * @return array
     */
    protected static function emptyConfig(): array
    {
        return [
            'mode' => 'local',
            'source' => ['type' => 'array', 'table' => '', 'value' => 'id', 'label' => 'title', 'where' => [], 'order' => '', 'limit' => 2000, 'data' => [], 'search' => null, 'create' => []],
            'data' => [],
            'prop' => [],
            'searchUrl' => '',
            'initUrl' => '',
            'radio' => false,
            'multiple' => null,
            'pageSize' => 20,
            'autoAdd' => false,
            'minKeywordLength' => 0,
            'showToolbar' => true,
            'empty' => '',
            'cache' => 0,
        ];
    }
    
    /**
     * 读取数据表的配置数据
     * @param array $source
     * @param array $config
     * @param array $ids
     * @param int $limit
     * @return array
     */
    protected static function queryTable(array $source, array $config, array $ids = [], int $limit = 0): array
    {
        $table = self::resolveTableName((string)($source['table'] ?? ''));
        if ($table === '') {
            return [];
        }
        $cacheKey = $table . '|' . md5(json_encode([$source, $ids]));
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $columns = self::getTableColumns($table);
        $value = (string)($source['value'] ?? 'id');
        $label = (string)($source['label'] ?? 'title');
        if (!in_array($value, $columns, true) || !in_array($label, $columns, true)) {
            Log::warning("选项数据源字段不存在: {$table}.{$value}/{$label}");
            return [];
        }
        
        $query = Db::table($table)->field([$value, $label]);
        if ($ids) {
            $query->whereIn($value, $ids);
        } else {
            self::applyWhere($query, $source['where'] ?? [], $columns);
        }
        $order = trim((string)($source['order'] ?? ''));
        if ($order !== '' && self::isSafeOrder($order)) {
            $query->order($order);
        }
        $max = $limit > 0 ? $limit : max(1, (int)($source['limit'] ?? 2000));
        $rows = $query->limit($max)->select()->toArray();
        
        self::$cache[$cacheKey] = $rows;
        return $rows;
    }
    
    /**
     * 应用 where 条件，仅允许已存在的字段
     * @param mixed $query
     * @param mixed $where
     * @param array $columns
     * @return void
     */
    protected static function applyWhere(mixed $query, mixed $where, array $columns): void
    {
        if (!is_array($where)) {
            return;
        }
        foreach ($where as $field => $value) {
            if (!in_array((string)$field, $columns, true)) {
                continue;
            }
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
    }
    
    /**
     * 数据表名合法性校验，并兼容不带前缀的写法
     * @param string $table
     * @return string
     */
    protected static function resolveTableName(string $table): string
    {
        $table = trim($table);
        if ($table === '' || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return '';
        }
        $tokens = explode('_', strtolower($table));
        foreach (self::DENY_TABLE_KEYWORDS as $keyword) {
            if (in_array($keyword, $tokens, true)) {
                Log::warning("选项数据源表被拒绝: {$table}");
                return '';
            }
        }
        if (self::getTableColumns($table) !== []) {
            return $table;
        }
        try {
            $prefix = (string)Db::connect()->getConfig('prefix');
        } catch (\Throwable) {
            $prefix = '';
        }
        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            return '';
        }
        $full = $prefix . $table;
        return self::getTableColumns($full) !== [] ? $full : '';
    }
    
    /**
     * 获取数据表字段
     * @param string $table
     * @return array
     */
    protected static function getTableColumns(string $table): array
    {
        $key = 'columns|' . $table;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return [];
        }
        try {
            $columns = array_column(Db::query('SHOW COLUMNS FROM `' . $table . '`'), 'Field');
        } catch (\Throwable $e) {
            Log::warning("选项数据源表不存在: {$table}: " . $e->getMessage());
            $columns = [];
        }
        self::$cache[$key] = $columns;
        return $columns;
    }
    
    /**
     * 排序表达式安全校验
     * @param string $order
     * @return bool
     */
    protected static function isSafeOrder(string $order): bool
    {
        return preg_match('/^[A-Za-z0-9_,.\s]+$/i', $order) === 1;
    }
    
    /**
     * 输出 prop 配置
     * @param array $config
     * @param array $source
     * @return array
     */
    protected static function getProp(array $config, array $source): array
    {
        $prop = is_array($config['prop'] ?? null) ? $config['prop'] : [];
        return [
            'name' => (string)($prop['name'] ?? ($source['label'] ?? 'title')),
            'value' => (string)($prop['value'] ?? ($source['value'] ?? 'id')),
        ];
    }
    
    /**
     * 转换为 xmSelect 数据格式
     * @param mixed $rows
     * @param array $prop
     * @return array
     */
    protected static function rowsToData(mixed $rows, array $prop): array
    {
        if ($rows instanceof Collection) {
            $rows = $rows->toArray();
        }
        if (!is_array($rows)) {
            return [];
        }
        
        $result = [];
        if (array_is_list($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $value = $row[$prop['value']] ?? $row['value'] ?? $row['id'] ?? null;
                    $label = $row[$prop['name']] ?? $row['label'] ?? $row['title'] ?? null;
                } elseif (is_scalar($row)) {
                    $value = $row;
                    $label = $row;
                } else {
                    continue;
                }
                if ($value === null) {
                    continue;
                }
                $result[] = [
                    $prop['value'] => $value,
                    $prop['name'] => (string)($label ?? $value),
                ];
            }
        } else {
            foreach ($rows as $key => $label) {
                if (is_array($label)) {
                    $value = $label[$prop['value']] ?? $label['value'] ?? $label['id'] ?? $key;
                    $text = $label[$prop['name']] ?? $label['label'] ?? $label['title'] ?? $value;
                } else {
                    $value = $key;
                    $text = $label;
                }
                $result[] = [
                    $prop['value'] => $value,
                    $prop['name'] => (string)$text,
                ];
            }
        }
        return $result;
    }
    
    /**
     * 解析远程地址，支持旧的 url@method 写法
     * @param string $url
     * @return string
     */
    protected static function buildUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/') || preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        if (str_contains($url, '@')) {
            [$path, $method] = explode('@', $url, 2);
            return urls($path, $method);
        }
        return urls($url);
    }
}
