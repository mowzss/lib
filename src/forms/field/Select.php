<?php

namespace mowzs\lib\forms\field;

use mowzs\lib\forms\FormFieldRenderer;
use mowzs\lib\forms\RendererInterface;

class Select extends FormFieldRenderer implements RendererInterface
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
        if (!is_array($option)) {
            $option = explode('|', $option);
        }
        $required = $required ? 'required' : '';
        return $this->fetch('select', [
            'name' => $name,
            'label' => $label,
            'value' => $value,
            'required' => $required,
            'option' => $option,
        ]);
    }
}
