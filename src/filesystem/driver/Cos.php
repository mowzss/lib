<?php

namespace mowzs\lib\filesystem\driver;

use Overtrue\Flysystem\Cos\CosAdapter;
use mowzs\lib\db\exception\DataNotFoundException;
use mowzs\lib\db\exception\DbException;
use mowzs\lib\db\exception\ModelNotFoundException;
use mowzs\lib\filesystem\Driver;

class Cos extends Driver
{

    protected function createAdapter(): \League\Flysystem\AdapterInterface
    {
        // TODO: Implement createAdapter() method.
        $Config = ['type' => 'qcloud',
            'region' => '',
            'credentials' => [
                'appId' => '', // 域名中数字部分
                'secretId' => '',
                'secretKey' => '',
            ],
            'bucket' => 'test',
            'timeout' => 60,
            'connect_timeout' => 60,
            'scheme' => 'https',
            'read_from_cdn' => false,
            ];

        return new CosAdapter($this->config);
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
        $path = str_replace('\\', '/', $path);

        if (!empty(sys_config('cos_domain'))) {
            return $this->concatPathToUrl(sys_config('cos_domain'), $path);
        }
        return parent::url($path); // TODO: Change the autogenerated stub
    }
}
