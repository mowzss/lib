<?php
declare (strict_types=1);

namespace mowzs\lib;

use think\App;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\View;
use think\Response;
use think\Validate;

abstract class Controller
{

    /**
     * Request实例
     * @var \think\Request
     */
    protected \think\Request $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected App $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected bool $batchValidate = false;
    /**
     * 存储模板变量的数组
     * @var array
     */
    protected array $vars = [];
    /**
     * 控制器中间件
     * @var array
     */
    protected array $middleware = [];
    /**
     * @var array|mixed
     */
    public mixed $get;
    /**
     * @var array|mixed
     */
    public mixed $post;

    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        if (in_array($app->request->action(), get_class_methods(__CLASS__))) {
            $this->error('禁止访问内置方法！');
        }
        $this->app = $app->bind('mowzs\lib\Controller', $this);
        $this->request = $this->app->request;
        $this->get = $this->request->get();
        $this->post = $this->request->post();
        // 控制器初始化
        $this->initialize();
    }

    /**
     * 初始化
     * @return void
     */
    protected function initialize()
    {
    }


    /**
     * 渲染模板
     * @param string $template
     * @param array $vars
     * @return string
     */
    protected function fetch(string $template = '', array $vars = []): string
    {
        foreach ($this as $name => $value) {
            $vars[$name] = $value;
        }
        return View::fetch($template, $vars);
    }

    /**
     * 数据回调
     * @param string $name
     * @param array $one
     * @param array $two
     * @param array $thr
     * @return bool
     */
    public function callback(string $name, mixed &$one = [], mixed &$two = [], mixed &$thr = []): bool
    {
        if (is_callable($name)) {
            return call_user_func($name, $this, $one, $two, $thr);
        }
        foreach (["_{$this->app->request->action()}{$name}", $name] as $method) {
            if (method_exists($this, $method) && false === $this->$method($one, $two, $thr)) {
                return false;
            }
        }
        return true;
    }


    /**
     * 模板变量赋值
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return \think\View
     */
    public function assign(mixed $name, mixed $value = ''): \think\View
    {
        return View::assign($name, $value);
    }


    /**
     * 验证数据
     * @access protected
     * @param array $data 数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array $message 提示信息
     * @param bool $batch 是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, string|array $validate, array $message = [], bool $batch = false): bool|array|string
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * @param string|null $url
     * @param mixed $msg
     * @param mixed $data
     * @param int $wait
     * @param array $header
     * @param int $code
     * @return void
     */
    private function ret(?string $url, mixed $msg, mixed $data, int $wait, array $header, int $code = 0, $tpl = ''): void
    {
        if (is_null($url) && isset($_SERVER["HTTP_REFERER"])) {
            $url = $_SERVER["HTTP_REFERER"];
        } elseif ($url) {
            $url = (strpos($url, '://') || str_starts_with($url, '/')) ? $url : (string)$this->app->route->buildUrl($url);
        }

        $result = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        // 把跳转模板的渲染下沉，这样在 response_send 行为里通过getData()获得的数据是一致性的格式
        if ('html' == strtolower($type)) {
            $type = 'view';
            if (empty($tpl)) {
                if ($code == 0) {
                    $tpl = $this->app->config->get('app.dispatch_success_tmpl');
                } else {
                    $tpl = $this->app->config->get('app.dispatch_error_tmpl');
                }
            }
            $response = Response::create($tpl, $type)->assign($result)->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }
        throw new HttpResponseException($response);
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param mixed $data 返回的数据
     * @param string|null $url 跳转的URL地址
     * @param integer $wait 跳转等待时间
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function success(mixed $msg = '', mixed $data = '', ?string $url = null, int $wait = 3, array $header = []): void
    {
        if (!is_string($msg) && empty($data)) {
            $data = $msg;
            $msg = 'ok';
        }
        $this->ret($url, $msg, $data, $wait, $header);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed|string $msg 提示信息
     * @param null|string $data 返回的数据
     * @param string|null $url 跳转的URL地址
     * @param integer $wait 跳转等待时间
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function error(mixed $msg = '', null|string $data = '', ?string $url = null, int $wait = 3, array $header = []): void
    {
        $this->ret($url, $msg, $data, $wait, $header, 1);
    }

    /**
     * 闭站/模块提示
     * @param string $msg
     * @param string|null $url
     * @param int $wait
     * @return void
     */
    protected function closeSite(string $msg = '', ?string $url = null, int $wait = 3): void
    {
        $tpl = $this->app->config->get('app.dispatch_close_site_tmpl');
        $this->ret($url, $msg, [], $wait, [], 1, $tpl);
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    private function getResponseType(): string
    {
        return $this->request->isJson() || $this->request->isAjax() ? 'json' : 'html';
    }

    /**
     * 返回封装后的API数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param integer $code 返回的code
     * @param null|string $msg 提示信息
     * @param string $type 返回数据格式
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function json(mixed $data, int $code = 0, null|string $msg = '', string $type = 'json', array $header = []): void
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ];
        $response = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * URL重定向
     * @access protected
     * @param string $url 跳转的URL表达式
     * @param integer $code http code
     * @param array $with 隐式传参
     * @return void
     */
    protected function redirect($url, int $code = 302, $with = []): void
    {
        $response = Response::create($url, 'redirect');

        $response->code($code)->with($with);

        throw new HttpResponseException($response);
    }


}
