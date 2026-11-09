<?php
declare (strict_types=1);


namespace happy\admin\libs\taglib;

class Hp extends \think\template\TagLib
{
    /**
     * 定义标签列表
     */
    protected $tags = [
        //数据库列表
        'table' => [
            'attr' => 'name,table,where,item,rows,page,pagenum,val,cache,status,whereor,order,by',
            'level' => 3,
            'close' => 1,
        ],
        //数据列表
        'lists' => [
            'attr' => 'name,module,mid,where,item,rows,page,pagenum,val,cache,order,by,cid,status,whereor,week,month,count,get,filter,sort_field,field',
            'level' => 3,
            'close' => 1,
        ],
        //分类
        'column' => [
            'attr' => 'name,module,where,item,rows,val,cache,status,whereor,order,by,offset,length,key,mod,sub',
            'level' => 3,
            'close' => 1,
        ],
    ];
    
    /**
     * 获取数据表数据
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagTable(array $tag, string $content): string
    {
        $name = $tag['name'];
        $rows = $tag['rows'] ?? 20;
        $table = $tag['table'];
        $page = isset($tag['page']) ? 1 : 0;
        $status = $tag['status'] ?? 1;
        $pageNum = isset($tag['pagenum']) ? 1 : 0;
        $cache = $tag['cache'] ?? 360;
        $item = $tag['item'] ?? 'rs';
        $where = $tag['where'] ?? '';
        $whereor = $tag['whereor'] ?? '';
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $val = $tag['val'] ?? false;
        $vals = $tag['val'] ?? $tag['name'];
        $parse = '<?php ';
        $parse .= '$' . $vals . '=\\happy\\admin\\libs\\taglib\\extends\\Table::getInstance()->run("' . $table . '",[
        "page"=>' . $page . ',
        "status"=>' . $status . ',
        "pagenum"=>' . $pageNum . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "where"=>"' . $where . '",
        "whereor"=>"' . $whereor . '",
        "order"=>"' . $order . '",
        "by"=>"' . $by . '",
        "cache"=>' . $cache . ',
        ]);';
        if (!empty($page)) {
            $parse .= '$pages = $' . $vals . '->render();';
        }
        $parse .= ' ?>';
        if (!empty($val)) {
            $parse .= $content;
        } else {
            $parse .= '{volist name="$' . $vals . '" id="' . $item . '"}';
            $parse .= $content;
            $parse .= '{/volist}';
        }
        return $parse;
    }
    
    /**
     * 获取栏目数据列表信息
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagColumn(array $tag, string $content): string
    {
        $name = $tag['name'];
        $rows = $tag['rows'] ?? 0;
        $module = $tag['module'];
        $status = $tag['status'] ?? 1;
        $cache = $tag['cache'] ?? 360;
        $item = $tag['item'] ?? 'rs';
        $where = $tag['where'] ?? '';
        $whereor = $tag['whereor'] ?? '';
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $val = $tag['val'] ?? false;
        $sub = $tag['sub'] ?? true;
        $vals = $tag['val'] ?? $tag['name'];
        $mod = $tag['mod'] ?? '2';
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        $length = !empty($tag['length']) && is_numeric($tag['length']) ? intval($tag['length']) : 'null';
        $key = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty = $tag['empty'] ?? '';
        $parse = '<?php ';
        
        $parse .= '$' . $vals . '=\\happy\\admin\\libs\\taglib\\extends\\Column::getInstance()->run("' . $module . '",[
        "status"=>' . $status . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "order"=>"' . $order . '",
        "by"=>"' . $by . '",
        "sub"=>"' . $sub . '",
        "where"=>"' . $where . '",
        "whereor"=>"' . $whereor . '",
        "cache"=>' . $cache . ',
        ]);';
        
        if (!empty($val)) {
            $parse .= '?>';
            $parse .= $content;
        } else {
            $parse .= 'if(is_array($' . $vals . ') || $' . $vals . ' instanceof \think\Collection || $' . $vals . ' instanceof \think\Paginator): $' . $key . ' = 0;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $length) {
                $parse .= '$__LIST__ = is_array($' . $vals . ') ? array_slice($' . $vals . ',' . $offset . ',' . $length . ', true) : $' . $vals . '->slice(' . $offset . ',' . $length . ', true); ';
            } else {
                $parse .= ' $__LIST__ = $' . $vals . ';';
            }
            $parse .= 'if( count($__LIST__)==0 ) : echo "' . $empty . '" ;';
            $parse .= 'else: ';
            $parse .= 'foreach($__LIST__ as $key=>$' . $item . '): ';
            $parse .= '$mod = ($' . $key . ' % ' . $mod . ' );';
            $parse .= '++$' . $key . ';?>';
            
            $parse .= $content;
            $parse .= '<?php endforeach; endif; else: echo "' . $empty . '" ;endif; ?>';
        }
        return $parse;
    }
    
    /**
     * 获取数据列表信息
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagLists(array $tag, string $content): string
    {
        $name = $tag['name'] ?? '';
        $module = $tag['module'] ?? '';
        $rows = $this->attrInt($tag['rows'] ?? null, 20);
        $page = $this->attrBool($tag, 'page');
        $filter = $this->attrBool($tag, 'filter');
        $status = $this->attrInt($tag['status'] ?? null, 1);
        $pagenum = $this->attrCode($tag['pagenum'] ?? null);
        $cache = $this->attrInt($tag['cache'] ?? null, 360);
        $item = $this->attrVar($tag['item'] ?? null, 'rs');
        $where = $tag['where'] ?? '';
        $cid = $this->attrCode($tag['cid'] ?? null);
        $mid = $this->attrCode($tag['mid'] ?? null);
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $whereor = $tag['whereor'] ?? '';
        $field = $tag['field'] ?? '';
        $sort_field = $tag['sort_field'] ?? '';
        $week = $tag['week'] ?? '';
        $month = $tag['month'] ?? '';
        $count = $this->attrVar($tag['count'] ?? null, 'count');
        $get = $tag['get'] ?? '';
        //val 作为变量名使用时校验合法性，非法名称回退到 name，避免生成 $1 这类非法变量
        $vals = $this->attrVar($tag['val'] ?? null, $name);
        $parse = '<?php ';
        $parse .= '$' . $vals . '=\\happy\\admin\\libs\\taglib\\extends\\Lists::getInstance()->run("' . $module . '",[
        "module" => "' . $module . '",
        "page"=>' . $page . ',
        "filter"=>' . $filter . ',
        "status"=>' . $status . ',
        "pagenum"=>' . $pagenum . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "where"=>"' . $where . '",
        "whereor"=>"' . $whereor . '",
        "sort_field"=>"' . $sort_field . '",
        "month"=>"' . $month . '",
        "week"=>"' . $week . '",
        "cache"=>' . $cache . ',
        "cid"=>' . $cid . ',
        "mid"=>' . $mid . ',
        "order"=>"' . $order . '",
        "by"=>"' . $by . '",
        "get"=>"' . $get . '",
        "field"=>"' . $field . '",
        ]);';
        if (!empty($page)) {
            //按 count 属性派生独立的统计/分页变量，避免同页多个分页列表互相覆盖
            $parse .= '$' . $count . ' = $' . $vals . '->total();';
            $parse .= '$' . $count . '_pages = $' . $vals . '->render();';
            //保留 $pages 变量，兼容既有模板
            $parse .= '$pages = $' . $count . '_pages;';
        }
        $parse .= ' ?>';
        if (!empty($tag['val'])) {
            $parse .= $content;
        } else {
            $parse .= '{volist name="$' . $vals . '" id="' . $item . '"}';
            $parse .= $content;
            $parse .= '{/volist}';
        }
        return $parse;
    }

    /**
     * 解析布尔型属性（page、filter 等）
     * 不写该属性时为关闭；page="0"、page="false" 等按关闭处理
     * @param array $tag
     * @param string $key
     * @return int
     */
    private function attrBool(array $tag, string $key): int
    {
        if (!isset($tag[$key])) {
            return 0;
        }
        if (is_bool($tag[$key])) {
            return $tag[$key] ? 1 : 0;
        }
        return in_array(strtolower(trim((string)$tag[$key])), ['', '0', 'false', 'no', 'off'], true) ? 0 : 1;
    }

    /**
     * 解析变量名属性，非法名称回退到后续候选值
     * @param mixed $value
     * @param string ...$fallback
     * @return string
     */
    private function attrVar($value, string ...$fallback): string
    {
        foreach (array_merge([$value], $fallback) as $candidate) {
            if (is_string($candidate) && preg_match('/^[A-Za-z_]\w*$/', $candidate)) {
                return $candidate;
            }
        }
        return 'list';
    }

    /**
     * 解析数值型属性，非数值回退默认值，避免生成非法 PHP 代码
     * @param mixed $value
     * @param int $default
     * @return int
     */
    private function attrInt($value, int $default): int
    {
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * 原样透传属性表达式（支持数字与 $var 模板变量），空值回退默认值
     * @param mixed $value
     * @param string $default
     * @return string
     */
    private function attrCode($value, string $default = '0'): string
    {
        $value = is_scalar($value) ? trim((string)$value) : '';
        return $value === '' ? $default : $value;
    }
    
}
