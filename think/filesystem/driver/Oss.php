<?php


namespace think\filesystem\driver;

use think\filesystem\Driver;
use think\db\exception\DbException;
use yzh52521\Flysystem\Oss\OssAdapter;
use League\Flysystem\FilesystemAdapter;
use think\db\exception\DataNotFoundException;
use League\Flysystem\WhitespacePathNormalizer;
use think\db\exception\ModelNotFoundException;

class Oss extends Driver
{
    /**
     * @var WhitespacePathNormalizer|null
     */
    protected ?WhitespacePathNormalizer $normalizer = null;
    
    /**
     * 获取文件访问地址
     * @param string $path 文件路径
     * @return string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function url(string $path): string
    {
        $path = $this->normalizer()->normalizePath($path);
        
        if (!empty(sys_config('oss_domain'))) {
            return $this->concatPathToUrl(sys_config('oss_domain'), $path);
        }
        return parent::url($path);
    }
    
    /**
     * @return WhitespacePathNormalizer|null
     */
    protected function normalizer(): WhitespacePathNormalizer|null
    {
        if (!$this->normalizer) {
            $this->normalizer = new WhitespacePathNormalizer();
        }
        return $this->normalizer;
    }
    
    /**
     * 浏览器直传使用的上传地址
     * @return string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUploadHost(): string
    {
        $bucket = (string)sys_config('oss_bucket');
        $endpoint = $this->endpointHost();
        if ($bucket === '' || $endpoint === '') {
            throw new \RuntimeException('OSS 配置不完整');
        }
        return 'https://' . $bucket . '.' . $endpoint;
    }
    
    /**
     * 生成 PostObject 直传签名
     *
     * @param string $dir 对象 key 前缀（需以 / 结尾）
     * @param int $maxSize 允许的最大字节数
     * @param int $expire 有效期（秒）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getSignPolicy(string $dir, int $maxSize, int $expire = 600): array
    {
        $accessId = (string)sys_config('oss_accesskeyid');
        $secret = (string)sys_config('oss_accesskeysecret');
        if ($accessId === '' || $secret === '') {
            throw new \RuntimeException('OSS 配置不完整');
        }
        
        $expireAt = time() + max(60, $expire);
        $policy = base64_encode((string)json_encode([
            'expiration' => gmdate('Y-m-d\TH:i:s.000\Z', $expireAt),
            'conditions' => [
                ['content-length-range', 0, $maxSize],
                ['starts-with', '$key', $dir],
                ['eq', '$success_action_status', '200'],
            ],
        ], JSON_UNESCAPED_SLASHES));
        
        return [
            'host' => $this->getUploadHost(),
            'policy' => $policy,
            'signature' => base64_encode(hash_hmac('sha1', $policy, $secret, true)),
            'accessId' => $accessId,
            'expire' => $expireAt,
            'successActionStatus' => 200,
        ];
    }
    
    /**
     * 读取对象元信息（大小 / mime / md5）
     *
     * @param string $path
     * @return array|null
     */
    public function getObjectMeta(string $path): ?array
    {
        $accessId = (string)sys_config('oss_accesskeyid');
        $secret = (string)sys_config('oss_accesskeysecret');
        $bucket = (string)sys_config('oss_bucket');
        $endpoint = $this->endpointHost();
        if ($accessId === '' || $secret === '' || $bucket === '' || $endpoint === '') {
            return null;
        }
        
        try {
            $client = new \OSS\OssClient($accessId, $secret, 'https://' . $endpoint);
            $meta = $client->getObjectMeta($bucket, ltrim($path, '/'));
            
            $etag = trim((string)($meta['etag'] ?? ''), '"');
            return [
                'size' => (int)($meta['content-length'] ?? 0),
                'mime' => (string)($meta['content-type'] ?? ''),
                // 分片上传的 etag 不是 md5
                'md5' => preg_match('/^[0-9a-f]{32}$/i', $etag) ? strtolower($etag) : '',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * 通过图片处理接口读取图片宽高
     *
     * @param string $path
     * @return array|null
     */
    public function getImageInfo(string $path): ?array
    {
        $body = $this->httpGet($this->url($path) . '?x-oss-process=image/info');
        if ($body === '') {
            return null;
        }
        $json = json_decode($body, true);
        $width = (int)($json['ImageWidth']['value'] ?? 0);
        $height = (int)($json['ImageHeight']['value'] ?? 0);
        return ($width > 0 && $height > 0) ? ['width' => $width, 'height' => $height] : null;
    }
    
    /**
     * endpoint 主机名（不含协议与结尾斜杠）
     * @return string
     */
    protected function endpointHost(): string
    {
        $endpoint = trim((string)sys_config('oss_endpoint'));
        return rtrim(preg_replace('#^https?://#i', '', $endpoint), '/');
    }
    
    /**
     * 简单 GET 请求（用于读取图片信息）
     *
     * @param string $url
     * @return string
     */
    protected function httpGet(string $url): string
    {
        $ch = curl_init($url);
        if (!$ch) {
            return '';
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : '';
    }
    
    /**
     * @return OssAdapter
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function createAdapter(): FilesystemAdapter
    {
        // TODO: Implement createAdapter() method.
        $ossConfig = [
            'access_id' => sys_config('oss_accesskeyid'),
            'access_secret' => sys_config('oss_accesskeysecret'),
            'bucket' => sys_config('oss_bucket'),
            'endpoint' => sys_config('oss_endpoint'),
            'isCName' => false,
            'prefix' => '',
            'cdnUrl' => '',
        ];
        return new OssAdapter($ossConfig);
    }
}
