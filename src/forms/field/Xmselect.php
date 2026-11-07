<?php
declare(strict_types=1);


namespace happy\admin\libs\forms\field;

use happy\admin\libs\forms\OptionResolver;
use happy\admin\libs\forms\FormFieldRenderer;
use happy\admin\libs\forms\RendererInterface;

class Xmselect extends FormFieldRenderer implements RendererInterface
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
        
        $resolved = OptionResolver::resolveXmSelect($option, $value);
        return $this->fetch('xmselect', [
            'name' => $name,
            'label' => $label,
            'value' => $resolved['value'],
            'required' => $required && !$disabled,
            'option' => $resolved['config'],
            'data' => $resolved['data'],
            'extra' => $extra,
            'disabled' => $disabled,
        ]);
    }
}
