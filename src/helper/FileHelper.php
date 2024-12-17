<?php

namespace Mowzs\Lib\helper;

use Mowzs\Lib\Helper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHelper extends Helper
{
    /**
     * 扫描目录下文件
     * @param string $directory 目录
     * @param string $extension 后缀
     * @param int $depth 层级
     * @param bool $relativePath
     * @param string $baseDir
     * @return array
     */
    public function scanDirectory(string $directory, string $extension = '', int $depth = 1, bool $relativePath = false, string $baseDir = ''): array
    {
        $files = [];
        if (!is_dir($directory)) {
            return $files;
        }

        // 如果需要相对路径且没有指定基准路径，则使用 app 目录作为基准路径
        if ($relativePath && empty($baseDir)) {
            $baseDir = rtrim(dirname(realpath($directory)), '\\/');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!empty($extension) && pathinfo($file->getPathname(), PATHINFO_EXTENSION) !== $extension) {
                continue;
            }

            if ($depth > 0 && substr_count($file->getPath(), DIRECTORY_SEPARATOR) - substr_count(rtrim($directory, '\\/'), DIRECTORY_SEPARATOR) >= $depth) {
                continue;
            }

            if ($relativePath) {
                $filePath = str_replace(rtrim($baseDir, '\\/') . DIRECTORY_SEPARATOR, '', $file->getPathname());
            } else {
                $filePath = $file->getPathname();
            }

            $files[] = $filePath;
        }

        return $files;
    }
}
