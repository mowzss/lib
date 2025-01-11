<?php
declare (strict_types=1);

namespace mowzs\lib;

use mowzs\lib\forms\FormFieldRenderer;
use think\Exception;
use think\exception\HttpResponseException;
use think\facade\Env;
use think\facade\Request;
use think\facade\View;

/**
 * 表单构建类
 */
class Forms
{
    /**
     * 表单默认值
     * @var array|mixed
     */
    protected array $value = [];
    /**
     * 提交地址
     * @var string|mixed
     */
    protected string $action;
    /**
     * 表单提交类型
     * @var string|mixed
     */
    protected string $method = 'post';
    /**
     * 表单数组
     * @var array|mixed
     */
    protected array $inputData = [];
    /**
     * 表单字段处理类
     * @var FormFieldRenderer
     */
    protected FormFieldRenderer $renderer;
    /**
     * 表单主键
     * @var string|mixed|null
     */
    protected ?string $pk = 'id'; // 主键名，默认为 'id'
    /**
     * 表单附加说明
     * @var string|mixed
     */
    protected string $description = ''; // 表单说明介绍
    /**
     * 输出模式 page为直接渲染页面 code 返回html代码
     * @var string|mixed
     */
    protected string $outputMode = 'page'; // 输出模式，默认为 'page'
    /**
     * 提交按钮
     * @var array
     */
    protected array $submit = [];
    /**
     * layui选择器
     * @var mixed|string
     */
    protected mixed $lay_filter = '';
    /**
     * @var \think\Template
     */
    protected \think\Template $view;
    /**
     * @var array|mixed
     */
    protected mixed $old_view_config;
    /**
     * @var string 渲染页面模版路径
     */
    protected string $out_template;
    /**
     * @var mixed|string
     */
    protected mixed $form_html;
    /**
     * 联动菜单
     * @var array|Forms
     */
    protected array|Forms $trigger = [];

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->setFormsViewPath($options['theme'] ?? null);
        $this->action = $options['action'] ?? urls(Request::action(), Request::get());
        $this->method = $options['method'] ?? 'post';
        $this->value = $options['value'] ?? [];
        $this->inputData = $options['inputData'] ?? [];
        $this->pk = $options['pk'] ?? 'id'; // 初始化主键名，默认为 'id'
        $this->description = $options['description'] ?? ''; // 表单说明介绍
        $this->outputMode = $options['outputMode'] ?? 'page'; // 初始化输出模式，默认为 'page'
        $this->renderer = new FormFieldRenderer();
        $this->lay_filter = $options['lay_filter'] ?? 'filter' . md5(time() . Request::url(true));
        $this->out_template = 'form';
        $this->form_html = isset($options['form_html']) ? $this->setFormHtml($options['form_html']) : '';
        $this->trigger = isset($options['trigger']) ? $this->setTriggers($options['trigger']) : [];
    }

    /**
     * 设置联动显示隐藏效果
     *
     * @param string $name 字段
     * @param mixed $value 触发值，可以是字符串、数组等
     * @param array|string $field 触发字段，多个使用数组或逗号分隔
     * @return $this
     * @throws Exception
     */
    public function setTrigger(string $name, mixed $value, array|string $field): static
    {
        // 将 $field 转换为数组
        if (is_string($field)) {
            $field = explode(',', $field);
        }

        // 确保 $field 是一个数组
        if (!is_array($field)) {
            throw new Exception('触发字段必须是字符串或数组');
        }

        // 查找已存在的 trigger，如果有则合并 value 和 field
        foreach ($this->trigger as &$existingTrigger) {
            if ($existingTrigger['name'] === $name) {
                // 如果已经存在该 name 的 trigger，则添加新的 value 和 field
                $existingTrigger['values'][] = [
                    'value' => $value,
                    'field' => $field,
                ];
                return $this;
            }
        }

        // 如果没有找到已存在的 trigger，则创建一个新的
        $this->trigger[] = [
            'name' => $name,
            'values' => [
                [
                    'value' => $value,
                    'field' => $field,
                ]
            ]
        ];

        return $this;
    }

    /**
     * 批量设置联动显示隐藏效果
     *
     * @param array $triggers 多个触发条件的数组
     * @return $this
     * @throws Exception
     */
    public function setTriggers(array $triggers): static
    {
        foreach ($triggers as $trigger) {
            if (!isset($trigger['name'], $trigger['values'])) {
                throw new Exception('每个触发条件必须包含 name 和 values');
            }

            // 确保 values 是一个数组
            if (!is_array($trigger['values'])) {
                throw new Exception('触发条件的 values 必须是一个数组');
            }

            // 遍历 values 数组，调用 setTrigger 方法来设置每个触发条件
            foreach ($trigger['values'] as $valueConfig) {
                if (!isset($valueConfig['value'], $valueConfig['field'])) {
                    throw new Exception('每个 valueConfig 必须包含 value 和 field');
                }

                $this->setTrigger(
                    $trigger['name'],
                    $valueConfig['value'],
                    $valueConfig['field']
                );
            }
        }

        return $this;
    }

    /**
     * 获取所有触发条件
     *
     * @param array $field
     * @return void
     * @throws Exception
     */
    protected function getTriggers(array $field = []): void
    {
        if (empty($field)) {
            $field = $this->inputData;
        }

        // 解析字段数据并生成触发条件
        foreach ($field as $item) {
            if (!empty($item['options'])) {
                $this->parseOptionsTriggers($item);
            }
        }

        // 将触发条件传递给视图层
        View::assign('trigger', $this->trigger);
    }

    /**
     * 解析 options 字段并生成触发条件
     *
     * @param array $item 字段数据
     * @return false|void
     * @throws Exception
     */
    protected function parseOptionsTriggers(array $item)
    {
        if (is_array($item['options'])) {
            return false;
        }

        // 解析 options 字段
        $options = array_filter(explode("\n", trim($item['options'])));
        foreach ($options as $option) {
            $parts = array_map('trim', explode('|', $option));
            if (count($parts) < 2) {
                continue;  // 忽略无效的选项
            }

            $value = $parts[0];
            $label = $parts[1];
            $dependentFields = isset($parts[2]) ? explode(',', $parts[2]) : [];

            // 如果有依赖字段，则设置触发条件
            if (!empty($dependentFields)) {
                $this->setTrigger(
                    $item['name'],  // 触发字段
                    $value,         // 触发值
                    $dependentFields, // 依赖字段
                );
            }
        }
    }


    /**
     * 静态实例化
     * @param array $options
     * @return static
     * @throws Exception
     */
    public static function instance(array $options = []): static
    {
        return new static($options);
    }

    /**
     * 设置默认值
     * @param array $value
     * @return $this
     */
    public function setValue(array $value = []): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * 设置提交地址
     * @param string $action
     * @return $this
     */
    public function setAction(string $action = ''): static
    {
        $this->action = $action;
        return $this;
    }

    /**
     * 设置提交方式
     * @param string $method
     * @return $this
     */
    public function setMethod(string $method = ''): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 设置主键
     * @param string|null $pk
     * @return $this
     */
    public function setPk(?string $pk = 'id'): static
    {
        $this->pk = $pk;
        return $this;
    }

    /**
     * 设置说明提示
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description = ''): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 设置输出方式
     * @param string $outputMode
     * @return $this
     */
    public function setOutputMode(string $outputMode = 'page'): static
    {
        $this->outputMode = in_array($outputMode, ['page', 'tpl']) ? $outputMode : 'page';
        return $this;
    }

    /**
     * 设置按钮
     * @param string $title
     * @return Forms
     */
    public function setReset(string $title = '清空'): static
    {
        $this->submit[] = $this->setButton('reset', $title, 'layui-btn layui-btn-primary');
        return $this;
    }

    /**
     * 提交按钮
     * @param string $title
     * @param string $class
     * @return Forms
     */
    public function setSubmit(string $title = '立即提交', string $class = 'layui-btn'): static
    {
        $this->submit[] = $this->setButton('submit', $title, $class, true);
        return $this;
    }

    /**
     * 设置按钮
     * @param string $type 按钮类型
     * @param string $title 按钮名称
     * @param string $class 按钮css类
     * @param bool $lay_filter lay_filter
     * @return string
     */
    protected function setButton(string $type = '', string $title = '', string $class = 'layui-btn', bool $lay_filter = false): string
    {
        $lay_submit = $type === 'submit' ? 'lay-submit=""' : '';
        $lay_filter = $lay_filter ? 'lay-filter="' . $this->lay_filter . '"' : '';
        return $this->fetch('button', ['btn' => [
            'title' => $title,
            'class' => $class,
            'lay_filter' => $lay_filter,
            'type' => $type,
            'lay_submit' => $lay_submit,
        ]]);
    }

    /**
     * 设置表单字段
     * @param mixed $type 表单类型
     * @param string|null $name 字段名称
     * @param string|null $label 标签名称
     * @param string|null $value 默认值
     * @param array|null $options 扩展参数
     * @param string|null $help 帮助说明
     * @param bool $required 是否必填
     * @return $this
     */
    public function setInput($type, ?string $name = null, ?string $label = null, ?string $value = null, ?array $options = null, ?string $help = null, bool $required = false): static
    {
        if (is_array($type)) {
            // 如果传入的是数组，则直接添加到输入数据中
            $this->inputData[] = $type;
        } else {
            // 如果传入的是单独的参数，则构建一个字段数组
            $this->inputData[] = [
                'type' => $type,
                'name' => $name,
                'label' => $label,
                'value' => $value,
                'options' => $options ?? [],
                'help' => $help ?? '',
                'required' => $required
            ];
        }
        return $this;
    }

    /**
     * 设置多个表单字段
     * @param array $fields
     * @return $this
     */
    public function setInputs(array $fields): static
    {
        foreach ($fields as $field) {
            $this->inputData[] = $field;
        }
        return $this;
    }

    /**
     * 合并字段值
     * @param array $field
     * @return array
     */
    protected function mergeValue(array $field): array
    {
        $name = $field['name'] ?? '';
        $value = $field['value'] ?? null;

        // 如果字段名包含方括号，则尝试解析为嵌套数组
        if (strpos($name, '[') !== false && strpos($name, ']') !== false) {
            // 去掉方括号并分割成键数组
            $parts = explode('[', str_replace(']', '', $name));

            // 从 this->value 中获取最深层的值
            $tempValue = $this->value;
            foreach ($parts as $part) {
                if (isset($tempValue[$part])) {
                    $tempValue = $tempValue[$part];
                } else {
                    $tempValue = null;  // 如果路径中的某个部分不存在，则返回null
                    break;
                }
            }

            if ($tempValue !== null) {
                $field['value'] = $tempValue;
            } elseif ($value !== null) {
                $field['value'] = $value;
            }
        } else {
            // 对于非嵌套的字段名，直接从 this->value 获取值
            if (isset($this->value[$name])) {
                $field['value'] = $this->value[$name];
            } elseif ($value !== null) {
                $field['value'] = $value;
            }
        }

        return $field;
    }

    /**
     * 渲染表单
     * @param array $data 覆盖或合并的数据
     * @param string $template 渲染模版
     * @param string|null $outputMode 输出模式，默认为 null（使用类中的 outputMode）
     * @return string
     * @throws Exception
     */
    public function render(array $data = [], string $template = '', ?string $outputMode = null): string
    {

        $this->outputMode = $outputMode ?? $this->outputMode;
        // 处理传入的数据
        $fields = array_merge($this->inputData, $data);

        if (empty($fields)) {
            return $this->outputMode();
        }
        //渲染字段
        $this->renderField($fields);
        $this->getTriggers($fields);
        $html = $this->fetch($template ?: $this->out_template, [
            'action' => $this->action,
            'method' => $this->method,
            'submit' => $this->renderSubmit(),
            'description' => $this->description,
            'lay_filter' => $this->lay_filter,
            'form_html' => $this->form_html
        ]);
        return $this->outputMode($html);
    }

    public function setFormHtml(string|array $html = ''): static
    {
        if (is_array($html)) {
            // 如果参数是数组，则生成HTML属性对
            $attributes = [];
            foreach ($html as $key => $value) {
                // 确保键和值都是有效的字符串
                if (is_string($key) && is_string($value)) {
                    // 使用 htmlspecialchars 来防止XSS攻击，确保输出安全
                    $attributes[] = htmlspecialchars($key) . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
                }
            }
            // 将所有属性连接成一个字符串
            $html = implode(' ', $attributes);
        }

        // 追加生成的HTML或原始字符串到form_html属性中
        $this->form_html .= $html;

        return $this;
    }

    /**
     * @param string $html
     * @return mixed
     */
    protected function outputMode(mixed $html = ''): mixed
    {
        if ($this->outputMode === 'page') {
            throw new HttpResponseException(display($html));
        } else {
            $config = array_merge($this->old_view_config, ['view_path' => '']);
            View::config($config);
            Helper::instance()->app->config->set($config, 'view');
            return $html;
        }
    }

    /**
     * 生成提交按钮
     * @return string
     */
    protected function renderSubmit(): string
    {
        // 如果没有提交按钮和重置按钮，设置默认值
        if (empty($this->submit)) {
            $this->setSubmit();
            $this->setReset();
        }
        // 生成提交按钮
        return implode('', $this->submit);
    }

    /**
     * @param array $fields
     * @return $this
     */
    protected function renderField(array $fields): static
    {
        // 如果 value 中包含主键值，将其添加到 inputData 中
        if ($this->pk && isset($this->value[$this->pk])) {
            $fields[] = [
                'type' => 'hidden',
                'name' => $this->pk,
                'value' => $this->value[$this->pk]
            ];
        }
        foreach ($fields as &$field) {
            $field = $this->mergeValue($field);
        }
        View::assign([
            'fields' => $fields,
            'renderer' => $this->renderer,
        ]);
        return $this;
    }

    /**
     * 获取模板目录
     * @param string|null $theme_name
     * @return string
     */
    protected function getFormsViewPath(string $theme_name = null): string
    {
        $path = 'forms_style';
        if (Helper::instance()->app->request->isMobile() && Env::get('CONTROLLER_LAYER') != 'admin') {
            $theme = 'wap_default';
            Helper::instance()->app->config->set(['cache_prefix' => 'wap_',], 'view');
        } else {
            $theme = 'default';
        }
        $theme = $theme_name ?: $theme;
        return Helper::instance()->app->getRootPath() . 'view' . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR;
    }

    /**
     * 设置模板风格路径
     * @param $theme
     * @return void
     */
    protected function setFormsViewPath($theme = null): void
    {
        $this->old_view_config = Helper::instance()->app->config->get('view');
        View::config(['view_path' => $this->getFormsViewPath($theme)]);
        Helper::instance()->app->config->set(['view_path' => $this->getFormsViewPath($theme)], 'view');
    }

    /**
     * 渲染模板
     * @param string $template
     * @param array $vars
     * @param string $prefix
     * @return string
     */
    protected function fetch(string $template = '', array $vars = [], string $prefix = '/index/'): string
    {
        $template = $prefix . $template;
        return View::fetch($template, $vars);
    }

}
