<?php

namespace mowzs\lib\forms\field;

use mowzs\lib\forms\FormFieldRenderer;
use mowzs\lib\forms\RendererInterface;
use mowzs\lib\forms\FormatFieldOption;

class Switchs extends FormFieldRenderer implements RendererInterface
{
    /**
     * 渲染表单
     * @param string $name
     * @param string $label
     * @param mixed $value
     * @param mixed $option
     * @param bool $required
     * @param mixed $disabled
     * @param mixed $extra
     * @return string
     */
    public function render(string $name, string $label, mixed $value, mixed $option, bool $required, mixed $disabled, mixed $extra): string
    {

        if (!is_array($option)) {
            $option = FormatFieldOption::strToArray($option);
        }
        if (is_array($option) && count($option) == 2) {
            $title = implode('|', array_reverse($option));
        }
        $required = $required ? 'required' : '';
        return $this->fetch('switchs', [
            'name' => $name,
            'label' => $label,
            'value' => $value,
            'required' => $required,
            'option' => $option,
            'disabled' => $disabled,
            'extra' => $extra,
            'title' => $title ?? '开启|关闭',
        ]);
    }
}
