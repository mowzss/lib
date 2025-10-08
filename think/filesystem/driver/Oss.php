<?php

namespace think\filesystem\driver;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\WhitespacePathNormalizer;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\filesystem\Driver;
use yzh52521\Flysystem\Oss\OssAdapter;

class Oss extends Driver
{
    /**
     * @var WhitespacePathNormalizer|null
     */
    protected ?WhitespacePathNormalizer $normalizer = null;

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
            'cdnUrl' => ''
        ];
        return new OssAdapter($ossConfig);
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
}
