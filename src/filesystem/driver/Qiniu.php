<?php

namespace mowzs\lib\filesystem\driver;

use League\Flysystem\AdapterInterface;
use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use think\filesystem\Driver;

class Qiniu extends Driver
{

    protected function createAdapter(): AdapterInterface
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
}
