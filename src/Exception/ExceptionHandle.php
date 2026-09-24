<?php
declare(strict_types=1);

namespace happy\admin\libs\Exception;

use Throwable;
use think\Response;
use think\facade\Log;
use think\facade\Request;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\exception\HttpResponseException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;

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
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 如果是不需要记录的异常类型，直接跳过
        if ($this->isIgnoreReport($exception)) {
            return;
        }
        
        // 构建详尽的日志上下文
        $context = [
            'url' => Request::url(true),               // 完整请求URL
            'method' => Request::method(),             // GET/POST等
            'ip' => Request::ip(),                     // 客户端IP
            'params' => Request::param(),              // 请求参数（注意脱敏）
            'file' => $exception->getFile(),           // 报错文件路径
            'line' => $exception->getLine(),           // 报错行号
        ];
        // 使用 error 级别记录，将上下文作为第二个参数传入
        Log::error($exception->getMessage() . implode(PHP_EOL, $context));
    }
    
    /**
     * Render an exception into an HTTP response.
     * @param \think\Request $request
     * @param Throwable $e
     * @return Response
     */
    public function render(\think\Request $request, Throwable $e): Response
    {
        // 保持原有的渲染逻辑不变，或根据需要自定义API返回格式
        return parent::render($request, $e);
    }
}
