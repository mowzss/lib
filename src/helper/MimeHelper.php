<?php

namespace Mowzs\Lib\helper;

use Mowzs\Lib\Helper;

class MimeHelper extends Helper
{
    protected array $mimeTypes = [];

    /**
     * 初始化 MIME 类型映射
     */
    public function init(): void
    {
        if (empty($this->mimeTypes)) {
            // 假设 mimes.php 文件位于 config 目录下
            $path = $this->app->getAppPath() . 'common/extra/mimes.php';
            if (file_exists($path)) {
                $this->mimeTypes = require $path;
            } else {
                throw new \Exception('mimes.php 文件不存在');
            }
        }
    }

    /**
     * 根据扩展名获取 MIME 类型
     *
     * @param string $extension 文件扩展名
     * @return string|null MIME 类型
     * @throws \Exception
     */
    public function getMimeTypeByExtension(string $extension): ?string
    {
        $this->init();
        return $this->mimeTypes[$extension] ?? null;
    }

    /**
     * 根据 MIME 类型获取扩展名
     *
     * @param string $mimeType MIME 类型
     * @return string|null 文件扩展名
     * @throws \Exception
     */
    public function getExtensionByMimeType(string $mimeType): ?string
    {
        $this->init();

        // 反转数组以查找 MIME 类型对应的扩展名
        $reversedMimeTypes = array_flip($this->mimeTypes);

        return $reversedMimeTypes[$mimeType] ?? null;
    }
}
