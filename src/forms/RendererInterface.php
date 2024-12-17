<?php

namespace mowzs\lib\forms;

interface RendererInterface
{
    /**
     * 渲染表单字段
     * @param string $name 表单字段名
     * @param string $label
     * @param mixed $value 默认值
     * @param mixed $option 表单参数
     * @param bool $required
     * @param mixed $ext
     * @return string
     */
    public function render(string $name, string $label, mixed $value, mixed $option, bool $required, mixed $ext): string;
}
