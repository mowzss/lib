<?php
declare (strict_types=1);


namespace happy\admin\libs;

use Throwable;
use think\Response;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\exception\HttpResponseException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];
    
    /**
     * 记录异常信息（包括日志或者其它方式记录）
     * 在框架默认日志基础上追加请求上下文（完整URL/方法/IP/参数等），便于按页面排查问题
     *
     * @access public
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        if ($this->isIgnoreReport($exception)) {
            return;
        }

        // 始终记录真实异常信息（show_error_msg 只控制浏览器输出，不应影响服务端日志）
        $log = sprintf(
            '[%s]%s[%s:%s]',
            $this->getCode($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        // 追加页面请求信息（最少记录完整URL）
        $log .= PHP_EOL . $this->buildRequestContext();

        if ($this->app->config->get('log.record_trace')) {
            $log .= PHP_EOL . $exception->getTraceAsString();
        }

        try {
            $this->app->log->record($log, 'error');
        } catch (Throwable) {
            // 日志记录失败不再抛出，避免产生二次异常
        }
    }

    /**
     * 构建请求上下文信息
     * @return string
     */
    protected function buildRequestContext(): string
    {
        try {
            if ($this->app->runningInConsole()) {
                return 'CLI: ' . implode(' ', $_SERVER['argv'] ?? []);
            }

            $request = $this->app->request;

            return implode(PHP_EOL, [
                'URL: ' . $request->url(true),
                'Method: ' . $request->method(),
                'IP: ' . $request->ip(),
                'UA: ' . (string)$request->header('user-agent'),
                'Referer: ' . (string)$request->header('referer'),
                'Get: ' . $this->formatParams($request->get()),
                'Post: ' . $this->formatParams($request->post()),
            ]);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * 过滤敏感参数并截断超长值，防止日志泄露或膨胀
     * @param array $params
     * @return string
     */
    protected function formatParams(array $params): string
    {
        $sensitive = ['password', 'pwd', 'passwd', 'token', 'secret'];
        foreach ($params as $key => &$value) {
            if (in_array(strtolower((string)$key), $sensitive, true)) {
                $value = '******';
                continue;
            }
            if (is_string($value) && strlen($value) > 500) {
                $value = substr($value, 0, 500) . '...';
            }
        }
        unset($value);

        return json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
    
    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 添加自定义异常处理机制
        
        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
