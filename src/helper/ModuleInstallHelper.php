<?php
declare(strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class ModuleInstallHelper extends Helper
{
    /**
     * 扫描并读取指定目录下的所有 info.php 文件。
     *
     * @param string|array $directories 单个或多个目标目录路径。
     * @return array 返回包含文件路径及其内容的数组。
     */
    public function scanAndReadInfoPhpFiles(): array
    {
        $allFilesData = [];

        // 如果是单个目录，则将其转换为数组以便统一处理

        $directories = $this->app->getAppPath() . 'common/install';

        $files = $this->scanInfoPhpFiles($directories);
        foreach ($files as $file) {
            // 读取info.php文件内容
            $content = include $file;
            // 将文件路径与内容一并存储
            $allFilesData[] = [
                'path' => $file,
                'content' => $content
            ];
        }


        return $allFilesData;
    }

    /**
     * 扫描指定目录下的所有 info.php 文件。
     *
     * @param string $directory 目标目录路径。
     * @return array 返回找到的所有 info.php 文件的路径数组。
     */
    private function scanInfoPhpFiles(string $directory): array
    {
        $files = [];

        if (file_exists($directory) && is_dir($directory)) {
            $dirHandle = opendir($directory);

            while (($file = readdir($dirHandle)) !== false) {
                $path = $directory . DIRECTORY_SEPARATOR . $file;

                if ($file == '.' || $file == '..') continue;

                if (is_dir($path)) {
                    $files = array_merge($files, $this->scanInfoPhpFiles($path));
                } elseif (basename($path) == 'info.php') {
                    $files[] = $path;
                }
            }

            closedir($dirHandle);
        }

        return $files;
    }
}
