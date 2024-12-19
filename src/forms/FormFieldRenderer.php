<?php

namespace mowzs\lib\forms;

use mowzs\lib\forms\field\Text;
use think\facade\Config;
use think\facade\Log;
use think\facade\View;

class FormFieldRenderer
{
    private static array $renderers = [];

    /**
     * 初始化渲染器映射
     */
    public static function initRenderers(): void
    {
        // 从配置文件中加载表单字段类型
        $formConfig = Config::get('form', []);

        // 动态生成渲染器映射
        foreach ($formConfig as $type => $label) {
            // 假设渲染器类名与字段类型相同，并位于 `Mowzs\Libs\forms\field` 命名空间下
            $className = 'mowzs\\lib\\forms\\field\\' . ucfirst($type);
            // 检查类是否存在
            if (class_exists($className)) {
                self::$renderers[$type] = $className;
            } else {
                // 如果类不存在，记录日志或使用默认的 Text 渲染器
                Log::warning("Renderer class not found for type: {$type}");
                self::$renderers[$type] = Text::class;
            }
        }
    }

    /**
     * 创建渲染器实例
     * @param string $type
     * @return mixed
     */
    protected static function create(string $type): mixed
    {
        // 初始化表单字段渲染器
        FormFieldRenderer::initRenderers();
        $class = self::$renderers[$type] ?? Text::class; // 默认为文本输入框
        return new $class();
    }

    /**
     * 渲染表单字段
     * @param array $field
     * @return string
     */
    public function renderField(array $field = []): string
    {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $value = $field['value'] ?? '';
        $option = $field['options'] ?? [];
        $required = $field['required'] ?? false;
        $ext = $field['ext'] ?? [];

        try {
            $renderer = self::create($type);
            return $renderer->render($name, $label, $value, $option, $required, $ext);
        } catch (\Exception $e) {
            // 记录错误日志并返回空字符串或默认渲染
            Log::error("Error rendering field: " . $e->getMessage());
            return '';
        }
    }

    /**
     * 渲染模板
     * @param string $template
     * @param array $vars
     * @return string
     */
    protected function fetch(string $template = '', array $vars = []): string
    {
        $template = 'field/' . $template;
        return View::fetch($template, $vars);
    }
}
