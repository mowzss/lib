<?php

namespace mowzs\lib\log\handler;

use think\contract\LogHandlerInterface;
use think\facade\Queue;

class MailLogHandler implements LogHandlerInterface
{
    protected mixed $to;
    protected mixed $subject;

    public function __construct($to, $subject = '系统日志')
    {
        $this->to = $to ?: config('log.to');
        $this->subject = $subject ?: config('log.subject');
    }

    /**
     * 日志写入接口
     * @access public
     * @param array $log 日志信息
     * @return bool
     */
    public function save(array $log): bool
    {
        // 将日志消息转换为字符串
        $body = is_array($log) ? json_encode($log, JSON_UNESCAPED_UNICODE) : (string)$log;
        try {
            Queue::push(\app\job\system\SendEmailJob::class, ['to' => $this->to, 'subject' => $this->subject, 'body' => $body, 'isHtml' => true]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
