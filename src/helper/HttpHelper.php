<?php
declare(strict_types=1);

namespace mowzs\lib\helper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response as PsrResponse;
use mowzs\lib\Helper;

class HttpHelper extends Helper
{
    /**
     * 预定义的常用 User-Agent 列表
     */
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Mobile Safari/537.36'
    ];

    /**
     * 发起 HTTP 请求
     *
     * @param string $method 请求方法（GET、POST、PUT、DELETE 等）
     * @param string $url 请求 URL
     * @param array $options 请求选项，包括 headers、form_params、json、timeout 等
     * @return array 返回响应内容或解析后的 JSON 对象
     * @throws \Exception|\GuzzleHttp\Exception\GuzzleException 如果请求失败，抛出异常
     */
    public function request(string $method, string $url, array $options = []): array
    {
        try {
            // 创建 Guzzle HTTP 客户端
            $client = new Client();

            // 设置默认的请求头
            $headers = $this->getDefaultHeaders($options);

            // 合并用户传入的请求头
            if (isset($options['headers']) && is_array($options['headers'])) {
                $headers = array_merge($headers, $options['headers']);
            }

            // 构建请求配置
            $requestConfig = [
                'headers' => $headers,
                'timeout' => isset($options['timeout']) ? $options['timeout'] : 30,  // 默认超时时间为 30 秒
            ];

            // 处理请求体
            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                if (isset($options['form_params']) && is_array($options['form_params'])) {
                    $requestConfig['form_params'] = $options['form_params'];
                } elseif (isset($options['json'])) {
                    $requestConfig['json'] = $options['json'];
                }
            }

            // 发起请求
            $response = $client->request($method, $url, $requestConfig);

            // 解析响应
            $responseBody = $response->getBody()->getContents();
            $contentType = $response->getHeaderLine('Content-Type');

            // 如果是 JSON 响应，尝试解析为数组
            if (stripos($contentType, 'application/json') !== false) {
                $decoded = json_decode($responseBody, true);
                return ['status' => $response->getStatusCode(), 'data' => $decoded];
            }

            // 返回原始响应内容
            return ['status' => $response->getStatusCode(), 'data' => $responseBody];
        } catch (RequestException $e) {
            // 捕获请求异常，返回错误信息
            $response = $e->getResponse();
            if ($response instanceof PsrResponse) {
                $errorBody = $response->getBody()->getContents();
                return ['status' => $response->getStatusCode(), 'error' => $errorBody];
            }
            throw new \Exception("请求失败: " . $e->getMessage());
        }
    }

    /**
     * 获取默认的请求头
     *
     * @param array $options 请求选项
     * @return array 默认的请求头
     */
    private function getDefaultHeaders(array $options): array
    {
        $headers = [];

        // 设置 User-Agent
        if (isset($options['ua']) && $options['ua'] === 'random') {
            // 使用随机 User-Agent
            $headers['User-Agent'] = $this->getUserAgentRandom();
        } elseif (isset($options['ua']) && is_string($options['ua'])) {
            // 使用指定的 User-Agent
            $headers['User-Agent'] = $options['ua'];
        } else {
            // 使用当前客户端的 User-Agent
            $headers['User-Agent'] = $this->getCurrentClientUserAgent();
        }

        // 设置 Content-Type，默认为 application/json
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * 获取随机的 User-Agent
     *
     * @return string 随机的 User-Agent
     */
    private function getUserAgentRandom(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    /**
     * 获取当前客户端的 User-Agent
     *
     * @return string 当前客户端的 User-Agent
     */
    private function getCurrentClientUserAgent(): string
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    }

    /**
     * 发起 GET 请求
     *
     * @param string $url 请求 URL
     * @param array $options 请求选项
     * @return array 返回响应内容或解析后的 JSON 对象
     * @throws \Exception
     */
    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * 发起 POST 请求
     *
     * @param string $url 请求 URL
     * @param array $options 请求选项
     * @return array 返回响应内容或解析后的 JSON 对象
     * @throws \Exception
     */
    public function post(string $url, array $options = []): array
    {
        return $this->request('POST', $url, $options);
    }

    /**
     * 发起 PUT 请求
     *
     * @param string $url 请求 URL
     * @param array $options 请求选项
     * @return array 返回响应内容或解析后的 JSON 对象
     * @throws \Exception
     */
    public function put(string $url, array $options = []): array
    {
        return $this->request('PUT', $url, $options);
    }

    /**
     * 发起 DELETE 请求
     *
     * @param string $url 请求 URL
     * @param array $options 请求选项
     * @return array 返回响应内容或解析后的 JSON 对象
     * @throws GuzzleException
     */
    public function delete(string $url, array $options = []): array
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * 发起 PATCH 请求
     *
     * @param string $url 请求 URL
     * @param array $options 请求选项
     * @return array 返回响应内容或解析后的 JSON 对象
     * @throws GuzzleException
     */
    public function patch(string $url, array $options = []): array
    {
        return $this->request('PATCH', $url, $options);
    }
}
