<?php

namespace mowzs\lib\log\driver;

use think\contract\LogHandlerInterface;
use think\facade\Config;
use think\facade\Log;
use think\facade\Queue;

class Email implements LogHandlerInterface
{
    protected mixed $to;
    protected mixed $subject;

    public function __construct($to, $subject = '系统日志')
    {
        $config = Config::get('log.channels.email');
        $this->to = $to ?: $config['to'];
        $this->subject = $subject ?: $config['subject'];
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
            Log::error('发件人：' . $this->to . '，主题：' . $this->subject . '，内容：' . $body);
            Queue::push(\app\job\system\SendEmailJob::class, ['to' => $this->to, 'subject' => $this->subject, 'body' => $body, 'isHtml' => false]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
