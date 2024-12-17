<?php

namespace mowzs\lib\filesystem\driver;

use Iidestiny\Flysystem\Oss\OssAdapter;
use OSS\Core\OssException;
use think\filesystem\Driver;

class Oss extends Driver
{
    /**
     *
     * @return AdapterInterface
     * @throws OssException
     * @author JaguarJack
     * @email njphper@gmail.com
     * @time 2020/1/25
     */
    protected function createAdapter(): AdapterInterface
    {
        // TODO: Implement createAdapter() method.
        $ossConfig = \config('filesystem.disks.oss');

        return new OssAdapter(
            $ossConfig['access_key'],
            $ossConfig['secret_key'],
            $ossConfig['end_point'],
            $ossConfig['bucket'],
            $ossConfig['is_cname'],
            $ossConfig['prefix']
        );
    }

}
