<?php
declare (strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class EventHelper extends Helper
{
    /**
     * 触发事件但不处理返回值
     * @access public
     * @param object|string $event 事件名称
     * @param mixed|null $params 传入参数
     * @return void
     */
    public function triggerNoReturn(object|string $event, mixed $params = []): void
    {
        $this->loadAndTriggerListeners($event, $params);
    }

    /**
     * 自动处理事件，并根据监听器返回的数据更新传入参数
     * @access public
     * @param object|string $event 事件名称
     * @param mixed|null $params 传入参数
     * @param bool $once 只获取一个有效返回值
     * @return void
     */
    public function listen(object|string $event, mixed &$params = [], bool $once = false): void
    {
        $data = $this->loadAndTriggerListeners($event, $params);

        if (empty($once) && !empty($data) && !empty($params)) {
            // 假设array3_merge是你的自定义函数，用于合并数组
            $data = $this->array3_merge($data, $params);
            $params = array_merge($params, $data);
        }
    }

    /**
     * 加载并触发事件监听器
     * @param object|string $event 事件名称
     * @param mixed|null $params 传入参数
     * @return array 返回所有监听器处理的结果
     */
    private function loadAndTriggerListeners(object|string $event, mixed &$params): array
    {
        try {
            // 获取并缓存有效的事件监听器类名列表
            $event_plugins = app()->cache->remember('ha_system_event_listen_' . $event, function () use ($event) {
                return $this->app->db->name('SystemEventListen')->where([
                    'status' => 1,
                    'event_key' => $event
                ])->column('event_class');
            });
        } catch (\Throwable $e) {
            $event_plugins = [];
            $this->app->log->error('事件报错:' . $e->getMessage());
        }

        // 注册事件监听器
        $this->app->event->listenEvents([$event => $event_plugins]);

        // 触发事件并获取所有监听器的返回结果
        return $this->app->event->trigger($event, $params);
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
