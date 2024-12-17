<?php

namespace Mowzs\Lib\forms\field;

use Mowzs\Lib\forms\FormFieldRenderer;
use Mowzs\Lib\forms\RendererInterface;

class Hidden extends FormFieldRenderer implements RendererInterface
{
    /**
     * 渲染表单
     * @param string $name
     * @param string $label
     * @param mixed $value
     * @param mixed $option
     * @param bool $required
     * @param mixed $ext
     * @return string
     */
    public function render(string $name, string $label, mixed $value, mixed $option, bool $required, mixed $ext): string
    {
        return <<<HTML
<input type="hidden" name="{$name}" value="{$value}">
HTML;
    }
}
