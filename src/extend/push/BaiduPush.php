<?php

namespace mowzs\lib\extend\push;

use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;

/**
 * 百度搜索引擎链接主动推送（快速收录）客户端
 * 官方文档：https://ziyuan.baidu.com/college/courseinfo?id=267&page=2#h2_article_title5
 */
class BaiduPush
{
    protected mixed $site;           // 你的网站域名，如：www.example.com
    protected mixed $token;          // 百度搜索资源平台获取的 token
    protected string $apiUrl = 'http://data.zz.baidu.com/urls';

    public function __construct($site = null, $token = null)
    {
        try {
            $this->site = $site ?: sys_config('baidu_push_token');
            $this->token = $token ?: sys_config('baidu_push_token');
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            throw new Exception('百度推送配置 site 或 token 获取失败');
        }

        if (empty($this->site) || empty($this->token)) {
            throw new Exception('百度推送配置错误：缺少 site 或 token。');
        }
    }

    /**
     * 推送单个 URL
     *
     * @param string $url 完整的 URL，如：https://www.example.com/article/123
     * @return array ['success' => bool, 'msg' => string, 'result' => array]
     */
    public function push(string $url): array
    {
        return $this->pushBatch([$url]);
    }

    /**
     * 批量推送多个 URL
     *
     * @param array $urls URL 列表
     * @return array
     */
    public function pushBatch(array $urls): array
    {
        // 过滤空值和无效 URL
        $urls = array_filter($urls, function ($url) {
            return !empty($url) && filter_var($url, FILTER_VALIDATE_URL);
        });

        if (empty($urls)) {
            return ['success' => false, 'msg' => '没有有效的 URL 需要推送', 'result' => null];
        }

        // 正确拼接 URL：site 和 token 作为查询参数
        $apiUrl = $this->apiUrl . '?site=' . $this->site . '&token=' . $this->token;

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,                    // ✅ 包含 site 和 token
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => implode("\n", $urls), // ✅ 纯文本 POST 内容
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/plain',           // ✅ 必须是 text/plain
                    'Accept: application/json'            // 推荐接收 JSON 响应
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false, // 生产环境建议开启验证
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return [
                    'success' => false,
                    'msg' => 'CURL 错误: ' . $error,
                    'result' => null,
                    'post' => $apiUrl,
                    'urls' => $urls,
                ];
            }

            $result = json_decode($response, true);

            // 百度返回 200 并且包含 success 字段表示成功
            if ($httpCode === 200 && isset($result['success'])) {
                return [
                    'success' => true,
                    'msg' => "推送成功，成功提交 {$result['success']} 个链接。",
                    'result' => $result,
                    'post' => $apiUrl,
                    'urls' => $urls,
                ];
            } else {
                // 处理错误情况（如 token 错误、site 不匹配、配额超限等）
                $errorMsg = $result['error'] ?? $result['message'] ?? $response ?? '未知错误';
                return [
                    'success' => false,
                    'msg' => "推送失败：{$errorMsg}",
                    'result' => $result,
                    'post' => $apiUrl,
                    'urls' => $urls,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'msg' => '推送异常：' . $e->getMessage(),
                'result' => null,
                'post' => $apiUrl,
                'urls' => $urls,

            ];
        }
    }
}
