<?php
declare (strict_types=1);

namespace mowzs\lib\taglib;

class Hp extends \think\template\TagLib
{
    /**
     * 定义标签列表
     */
    protected $tags = [
        //数据库列表
        'table' => [
            'attr' => 'name,table,where,item,rows,page,val,cache,status,whereor,order,by',
            'level' => 3,
            'close' => 1,
        ],
        //数据列表
        'lists' => [
            'attr' => 'name,module,mid,where,item,rows,page,val,cache,order,by,cid,status,whereor,week,month,count,get,filter,sort_field',
            'level' => 3,
            'close' => 1,
        ],
        //分类
        'column' => [
            'attr' => 'name,module,where,item,rows，val,cache,status,whereor,order,by,offset,length,key,mod,sub',
            'level' => 3,
            'close' => 1,
        ]
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
        $parse .= '$' . $vals . '=\\mowzs\\lib\\taglib\\extends\\Table::getInstance()->run("' . $table . '",[
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

        $parse .= '$' . $vals . '=\\mowzs\\lib\\taglib\\extends\\Column::getInstance()->run("' . $module . '",[
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
        $name = $tag['name'];
        $rows = $tag['rows'] ?? 20;
        $module = $tag['module'];
        $page = isset($tag['page']) ? 1 : 0;
        $filter = isset($tag['filter']) ? 1 : 0;
        $status = $tag['status'] ?? 1;
        $pageNum = isset($tag['pagenum']) ? 1 : 0;
        $cache = $tag['cache'] ?? 360;
        $item = $tag['item'] ?? 'rs';
        $where = $tag['where'] ?? '';
        $cid = $tag['cid'] ?? 0;
        $mid = $tag['mid'] ?? 0;
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $whereor = $tag['whereor'] ?? '';
        $sort_field = $tag['sort_field'] ?? '';
        $week = $tag['week'] ?? '';
        $month = $tag['month'] ?? '';
        $count = $tag['count'] ?? 'count';
        $get = $tag['get'] ?? '';
        $val = $tag['val'] ?? false;
        $vals = $tag['val'] ?? $tag['name'];
        $parse = '<?php ';
        $parse .= '$' . $vals . '=\\mowzs\\lib\\taglib\\extends\\Lists::getInstance()->run("' . $module . '",[
        "module" => "' . $module . '",
        "page"=>' . $page . ',
        "filter"=>' . $filter . ',
        "status"=>' . $status . ',
        "pagenum"=>' . $pageNum . ',
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
        ]);';
        if (!empty($page)) {
            $parse .= '$' . $count . ' = $' . $vals . '->total();';
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

}
