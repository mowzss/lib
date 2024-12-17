<?php
declare (strict_types=1);

namespace Mowzs\Lib\helper;

use Mowzs\Lib\Helper;

class EventHelper extends Helper
{

    /**
     * 自动处理事件
     * @access public
     * @param object|string $event 事件名称
     * @param mixed|null $params 传入参数
     * @param bool $once 只获取一个有效返回值
     * @return array|void
     */
    public function listen(object|string $event, mixed &$params = [], bool $once = false)
    {
        try {
            $event_plugin = app()->cache->remember('site_event_plugin_' . $event, function () use ($event) {
                return $this->app->db->name('SystemEventPlugin')->where([
                    'status' => 1,
                    'event_key' => $event
                ])->column('event_class');
            });
        } catch (\Throwable $e) {
            return [];
        }
        $this->app->event->listenEvents([$event => $event_plugin]);
        $data = $this->app->event->trigger($event, $params, $once);

        if (empty($once) && !empty($data) && !empty($params)) {
            $data = $this->array3_merge($data, $params);
        }

        if (empty($data)) {
            $data = [];
        }

        $params = array_merge($params, $data);
    }

    /**
     * 合并修改项
     * @param array $data
     * @param mixed $params
     * @return array
     */
    protected function array3_merge(array $data, mixed $params): array
    {
        $newArray = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (!isset($params[$k])) {
                        $newArray[$k] = $v;
                    } else {
                        if (is_array($params) && $params[$k] != $v) {
                            $newArray[$k] = $v;
                        }
                    }
                }
            }
        }
        return array_merge($params, $newArray);
    }
}
