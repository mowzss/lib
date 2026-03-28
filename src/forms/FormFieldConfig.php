<?php

namespace mowzs\lib\forms;

use mowzs\lib\Helper;

class FormFieldConfig
{
    protected static array $forms = [
        'text' => '单行输入框',
        'textarea' => '多行输入框',
        'radio' => '单选框',
        'checkbox' => '复选框',
        'select' => '单选下拉框',
        'xmselect' => '异步[单选/多选]',
        'image' => '单图上传',
        'images' => '多图上传',
        'file' => '单文件上传',
        'files' => '多文件上传',
        'date' => '日期',
        'datetime' => '日期+时间',
        'daterange' => '日期区间',
        'icon' => 'Icon图标',
        'color' => '颜色选择器',
        'cron' => 'Cron表达式',
        'editor' => '富文本编辑器(默认)',
        'tinymce' => 'tinymce编辑器',
        'wangeditor' => 'WangEditor',
        'ueditor' => '百度Ueditor',
        'hidden' => '隐藏字段',
    ];

    /**
     * 获取表单配置
     * @return array
     */
    public static function get(): array
    {
        $custom_config = Helper::instance()->app->config->get('form');
        return array_merge(self::$forms, $custom_config);
    }
}
