<?php
declare(strict_types=1);


namespace happy\admin\libs\forms\field;

use happy\admin\libs\forms\OptionResolver;
use happy\admin\libs\forms\FormFieldRenderer;
use happy\admin\libs\forms\RendererInterface;

class Radio extends FormFieldRenderer implements RendererInterface
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
        $option = OptionResolver::resolveKeyValue($option);
        $required_html = ($required && !$disabled) ? 'required lay-verify="required"' : '';
        return $this->fetch('radio', [
            'name' => $name,
            'label' => $label,
            'value' => $value,
            'required' => $required_html,
            'extra' => $extra,
            'option' => $option,
            'disabled' => $disabled,
        ]);
        
    }
}
