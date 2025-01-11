<?php

namespace think\filesystem\driver;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathNormalizer;
use League\Flysystem\WhitespacePathNormalizer;
use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\filesystem\Driver;

class Qiniu extends Driver
{
    /**
     * @var PathNormalizer
     */
    protected $normalizer;

    protected function createAdapter(): FilesystemAdapter
    {
        // TODO: Implement createAdapter() method.

        $qiniuConfig = [
            'access_key' => sys_config('qiniu_access'),
            'secret_key' => sys_config('qiniu_secret'),
            'bucket' => sys_config('qiniu_bucket'),
            'domain' => sys_config('qiniu_domain'),
        ];


        return new QiniuAdapter($qiniuConfig['access_key'], $qiniuConfig['secret_key'], $qiniuConfig['bucket'], $qiniuConfig['domain']);
    }

    protected function normalizer()
    {
        if (!$this->normalizer) {
            $this->normalizer = new WhitespacePathNormalizer();
        }
        return $this->normalizer;
    }

    /**
     * @param string $path
     * @return string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function url(string $path): string
    {
        $path = $this->normalizer()->normalizePath($path);

        if (!empty(sys_config('qiniu_domain'))) {
            return $this->concatPathToUrl(sys_config('qiniu_domain'), $path);
        }
        return parent::url($path); // TODO: Change the autogenerated stub
    }
}
