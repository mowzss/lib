<?php
declare(strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

/**
 * 模块查询结果格式化助手
 */
class ModuleFoematHelper extends Helper
{
    /**
     * @param array $item 单组数据
     * @return array
     */
    public function content(array $item)
    {
        $item['_images'] = $item['images'] ?? '';
        $item['_image'] = $item['image'] ?? '';
        $item['images'] = [];
        if (!empty($item['_images'])) {
            $item['images'] = str2arr($item['_images']);
            $item['image'] = $item['images'][0];
        }
        $time = $item['update_time'];
        if (empty($item['update_time'])) {
            $time = $item['create_time'];
        }
        $item['time'] = format_datetime($time, 'Y-m-d');
        $item['published_time'] = format_datetime($item['create_time'], 'Y-m-d\TH:i:sP');
        $item['updated_time'] = format_datetime($item['update_time'], 'Y-m-d\TH:i:sP');
        $item['_time'] = format_time($item['create_time'], true);
        if (isset($item['view'])) {
            $item['_view'] = format_view($item['view']);
        }
        $item['_content'] = isset($item['content']) ? del_html($item['content']) : '';
        if (empty($item['description'])) {
            $item['description'] = get_word($item['_content'], 120);
        }
        if (!empty($item['module_dir'])) {
            $item['url'] = hurl("{$item['module_dir']}/content/index", ['id' => $item['id']]);
        }
        return $item;
    }
}
