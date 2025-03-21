<?php

namespace mowzs\lib\extend\push;

use Exception;

class IndexNowPush
{
    /**
     * IndexNow接口
     * @var string
     */
    private string $indexNowUrl = 'https://api.indexnow.org/IndexNow';
    /**
     * 主机
     * @var string|array|false|int|mixed|null
     */
    private string $host;
    /**
     * 秘钥
     * @var string
     */
    private string $key;
    /**
     * 秘钥文件
     * @var string|mixed
     */
    private string $keyLocation;

    /**
     * @param string $domain 域名不以/结尾
     * @param string $key 秘钥
     * @param string $keyLocation 秘钥文本可为空
     */
    public function __construct(string $domain, string $key = '', string $keyLocation = '')
    {
        $this->host = format_url($domain, 'host');
        $this->key = $key ? md5($this->host . '-happyAdmin') : '';
        $keyLocation = $keyLocation ?: $domain . '/' . $this->key . '.txt';
        $key_file = public_path() . $this->key . '.txt';
        if (!file_exists($key_file)) {
            file_put_contents($key_file, $this->key);
        }
        $this->keyLocation = $keyLocation;
    }

    /**
     * @param array $urls 网址组
     * @return array code 1 推送成功 0 推送失败 message推送失败原因
     * @throws Exception
     */
    public function pushUrls(array $urls): array
    {
        // 构建请求体
        $data = [
            'host' => $this->host,
            'key' => $this->key,
            'keyLocation' => $this->keyLocation,
            'urlList' => $urls
        ];
        $dataJson = json_encode($data);

        // 发送 POST 请求
        $ch = curl_init($this->indexNowUrl);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Host: api.indexnow.org'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // 发送请求并获取响应
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // 检查是否有错误发生
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            throw new Exception("cURL Error: " . $error_msg);
        }

        // 关闭 cURL 句柄
        curl_close($ch);
        // 解析 HTTP 响应码
        $result = [];
        switch ($httpCode) {
            case 200:
                $result = ['code' => '1', 'status' => 'Ok', 'message' => 'URL submitted successfully', 'response' => $response];
                break;
            case 400:
                $result = ['code' => '0', 'status' => 'Bad request', 'message' => 'Invalid format', 'response' => $response];
                break;
            case 403:
                $result = ['code' => '0', 'status' => 'Forbidden', 'message' => 'In case of key not valid (e.g. key not found, file found but key not in the file)', 'response' => $response];
                break;
            case 422:
                $result = ['code' => '0', 'status' => 'Unprocessable Entity', 'message' => 'In case of URLs don’t belong to the host or the key is not matching the schema in the protocol', 'response' => $response];
                break;
            case 429:
                $result = ['code' => '0', 'status' => 'Too Many Requests', 'message' => 'Too Many Requests (potential Spam)', 'response' => $response];
                break;
            default:
                $result = ['code' => '0', 'status' => 'Unknown Error', 'message' => 'Unknown HTTP response code', 'response' => $response];
                break;
        }

        // 返回结果
        return $result;
    }
}
