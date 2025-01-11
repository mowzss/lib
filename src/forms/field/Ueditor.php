<?php
declare(strict_types=1);

namespace mowzs\lib\forms\field;

use mowzs\lib\forms\FormFieldRenderer;
use mowzs\lib\forms\RendererInterface;

class Ueditor extends FormFieldRenderer implements RendererInterface
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
        $required = $required ? 'required lay-verify="required"' : '';
        return $this->fetch('ueditor', [
            'name' => $name,
            'label' => $label,
            'value' => $value,
            'required' => $required,
            'ext' => $ext,
        ]);
    }
}
