<?php
declare(strict_types=1);

namespace mowzs\lib\forms\field;

use mowzs\lib\forms\FormatFieldOption;
use mowzs\lib\forms\FormFieldRenderer;
use mowzs\lib\forms\RendererInterface;

class Xmselect extends FormFieldRenderer implements RendererInterface
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
            $option = FormatFieldOption::strToArray($option);
        }
        $required = $required ? 'required lay-verify="required"' : '';
        return $this->fetch('xmselect', [
            'name' => $name,
            'label' => $label,
            'value' => $value,
            'required' => $required,
            'option' => $option,
            'ext' => $ext,
        ]);
    }
}
