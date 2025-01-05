<?php
declare (strict_types=1);

namespace mowzs\lib\taglib;

class Hp extends \think\template\TagLib
{
    /**
     * 定义标签列表
     */
    protected $tags = [

        //tag数据列表
        'tag_lists' => [
            'attr' => 'name,model,item,rows,page,val,cache,order,by,tid',
            'level' => 3,
            'close' => 1,
        ],
        //专题内容数据列表
        'spec_lists' => [
            'attr' => 'name,model,item,rows,page,val,cache,order,by,sid',
            'level' => 3,
            'close' => 1,
        ],
        //专题数据列表
        'spec' => [
            'attr' => 'name,model,item,rows,page,val,cache,order,by,where,flags,whereor',
            'level' => 3,
            'close' => 1,
        ],
        //数据列表
        'lists' => [
            'attr' => 'name,module,mid,where,item,rows,page,val,cache,order,by,cid,status,whereor,week,month,count,get',
            'level' => 3,
            'close' => 1,
        ],
        //分类
        'column' => [
            'attr' => 'name,model,where,item,rows，val,cache,status,whereor,order,by,offset,length,key,mod',
            'level' => 3,
            'close' => 1,
        ]
    ];

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
        $model = $tag['model'];
        $status = $tag['status'] ?? 1;
        $cache = $tag['cache'] ?? 360;
        $item = $tag['item'] ?? 'rs';
        $where = $tag['where'] ?? '';
        $whereor = $tag['whereor'] ?? '';
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $val = $tag['val'] ?? false;
        $vals = $tag['val'] ?? $tag['name'];
        $mod = $tag['mod'] ?? '2';
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        $length = !empty($tag['length']) && is_numeric($tag['length']) ? intval($tag['length']) : 'null';
        $key = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty = $tag['empty'] ?? '';
        $parse = '<?php ';

        $parse .= '$' . $vals . '=\\mowzs\\lib\\taglib\\Lists::getInstance()->run("' . $model . '",[
        "status"=>' . $status . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "order"=>"' . $order . '",
        "by"=>"' . $by . '",
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
        $week = $tag['week'] ?? '';
        $month = $tag['month'] ?? '';
        $count = $tag['count'] ?? 'count';
        $get = $tag['get'] ?? '';
        $val = $tag['val'] ?? false;
        $vals = $tag['val'] ?? $tag['name'];
        $parse = '<?php ';
        $parse .= '$' . $vals . '=\\mowzs\\lib\\taglib\\Lists::getInstance()->run([
        "module" => "' . $module . '",
        "page"=>' . $page . ',
        "status"=>' . $status . ',
        "pagenum"=>' . $pageNum . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "where"=>"' . $where . '",
        "whereor"=>"' . $whereor . '",
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
        }
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
     * 获取数据列表信息
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagTag_Lists(array $tag, string $content): string
    {
        $name = $tag['name'];
        $rows = $tag['rows'] ?? 0;
        $model = $tag['model'];
        $cache = $tag['cache'] ?? 360;
        $item = $tag['item'] ?? 'rs';
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $val = $tag['val'] ?? false;
        $vals = $tag['val'] ?? $tag['name'];
        $tid = $tag['tid'] ?? '0';
        $page = isset($tag['page']) ? 1 : 0;
        $parse = '<?php ';

        $parse .= '$' . $vals . '=\\Yx\\taglib\\Init::instance("TagLists")->Taglib("' . $model . '",[
        "page"=>' . $page . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "order"=>"' . $order . '",
        "by"=>"' . $by . '",
        "tid"=>"' . $tid . '",
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
     * 获取数据列表信息
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagSpec_Lists(array $tag, string $content): string
    {
        $name = $tag['name'];
        $rows = $tag['rows'] ?? 0;
        $model = $tag['model'];
        $cache = $tag['cache'] ?? 360;
        $item = $tag['item'] ?? 'rs';
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $val = $tag['val'] ?? false;
        $vals = $tag['val'] ?? $tag['name'];
        $sid = $tag['sid'] ?? '0';
        $page = isset($tag['page']) ? 1 : 0;
        $parse = '<?php ';

        $parse .= '$' . $vals . '=\\Yx\\taglib\\Init::instance("SpecLists")->Taglib("' . $model . '",[
        "page"=>' . $page . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "order"=>"' . $order . '",
        "by"=>"' . $by . '",
        "sid"=>"' . $sid . '",
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
     * 获取数据列表信息
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagSpec(array $tag, string $content): string
    {
        $where = $tag['where'] ?? '';
        $whereor = $tag['whereor'] ?? '';
        $name = $tag['name'];
        $rows = $tag['rows'] ?? 0;
        $model = $tag['model'];
        $cache = $tag['cache'] ?? 360;
        $item = $tag['item'] ?? 'rs';
        $order = $tag['order'] ?? 0;
        $by = $tag['by'] ?? 0;
        $val = $tag['val'] ?? false;
        $flags = $tag['flags'] ?? '';
        $vals = $tag['val'] ?? $tag['name'];
        $page = isset($tag['page']) ? 1 : 0;
        $parse = '<?php ';

        $parse .= '$' . $vals . '=\\Yx\\taglib\\Init::instance("Spec")->Taglib("' . $model . '",[
        "page"=>' . $page . ',
        "rows"=>' . $rows . ',
        "name"=>"' . $name . '",
        "order"=>"' . $order . '",
        "by"=>"' . $by . '",
        "where"=>"' . $where . '",
        "whereor"=>"' . $whereor . '", 
        "flags"=>"' . $flags . '",
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


}
