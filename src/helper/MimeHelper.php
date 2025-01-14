<?php
declare (strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

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
            $path = __DIR__ . '/mimes.php';
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
     * @return mixed|null
     * @throws \Exception
     */
    public function getExtensionByMimeType(string $mimeType): mixed
    {
        $this->init();

        // 反转数组以支持通过 MIME 类型获取扩展名
        foreach ($this->mimeTypes as $ext => $type) {
            $reversedMimeTypes[$type][] = $ext;
        }

        return $reversedMimeTypes[$mimeType] ?? null;
    }

    /**
     * 根据多个扩展名获取对应的 MIME 类型
     *
     * @param array $extensions 文件扩展名数组
     * @return array MIME 类型数组
     * @throws \Exception
     */
    public function getMimeTypesByExtensions(array $extensions): array
    {
        $mimeTypes = [];
        foreach ($extensions as $extension) {
            // 调用现有的方法获取单个扩展名的 MIME 类型
            $mime = $this->getMimeTypeByExtension($extension);
            if ($mime !== null) {
                $mimeTypes[$extension] = $mime;
            }
            // 如果想要包含找不到的扩展名, 请取消下面这行的注释
            // else {
            //     $mimeTypes[$extension] = null;
            // }
        }
        return $mimeTypes;
    }
}
