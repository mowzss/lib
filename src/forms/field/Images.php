<?php
declare(strict_types=1);

namespace happy\admin\libs\forms\field;

use happy\admin\libs\forms\FormFieldRenderer;
use happy\admin\libs\forms\RendererInterface;

class Images extends FormFieldRenderer implements RendererInterface
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
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $required_html = $required ? 'required lay-verify="required"' : '';
        return $this->fetch('images', [
            'name' => $name,
            'label' => $label,
            'value' => $value,
            'required' => $required_html,
            'extra' => $extra,
            'disabled' => $disabled,
        ]);
    }
}
