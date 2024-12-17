<?php

namespace mowzs\lib\filesystem\driver;

use Iidestiny\Flysystem\Oss\OssAdapter;
use League\Flysystem\AdapterInterface;
use OSS\Core\OssException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\filesystem\Driver;

class Oss extends Driver
{
    /**
     *
     * @return AdapterInterface
     * @throws OssException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function createAdapter(): AdapterInterface
    {
        // TODO: Implement createAdapter() method.
        $ossConfig = [
            'oss_accesskeyid' => sys_config('oss_accesskeyid'),
            'oss_accesskeysecret' => sys_config('oss_accesskeysecret'),
            'oss_bucket' => sys_config('oss_bucket'),
            'oss_endpoint' => sys_config('oss_endpoint'),
            'oss_domain' => sys_config('oss_domain'),
            'prefix' => '',
        ];
        return new OssAdapter(
            $ossConfig['oss_accesskeyid'],
            $ossConfig['oss_accesskeysecret'],
            $ossConfig['oss_endpoint'],
            $ossConfig['oss_bucket'],
            $ossConfig['oss_domain'],
            $ossConfig['prefix']
        );
    }

}
