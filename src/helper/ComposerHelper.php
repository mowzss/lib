<?php

namespace mowzs\lib\helper;

use Exception;

class ComposerHelper
{
    /**
     * 读取 composer.lock 文件并解析内容
     *
     * @param string $lockFilePath composer.lock 文件路径
     * @return array 解析后的数据
     * @throws Exception 如果文件不存在或解析失败
     */
    public static function readComposerLock(string $lockFilePath = 'vendor/composer/installed.json'): array
    {

        $lockFilePath = root_path() . $lockFilePath;
        if (!file_exists($lockFilePath)) {
            throw new Exception("Composer lock file not found at path: {$lockFilePath}");
        }

        $content = file_get_contents($lockFilePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode composer.lock file: " . json_last_error_msg());
        }

        return $data ?? [];
    }

    /**
     * 获取所有 Composer 扩展包信息
     *
     * @param string $lockFilePath composer.lock 文件路径
     * @return array 扩展包信息数组
     * @throws Exception
     */
    public static function getAllPackages(string $lockFilePath = 'vendor/composer/installed.json'): array
    {
        $data = self::readComposerLock($lockFilePath);
        $packages = [];

        foreach ($data['packages'] as $item) {
            $packages[] = [
                'name' => $item['name'] ?? 'Unknown',
                'version' => $item['version'] ?? 'Unknown',
                'type' => $item['type'] ?? 'Unknown',
                'description' => $item['description'] ?? 'Unknown',
                'license' => $item['license'] ?? 'Unknown',
                'homepage' => $item['homepage'] ?? 'Unknown',
            ];
        }

        return $packages;
    }

    /**
     * 获取指定类型的 Composer 扩展包信息
     *
     * @param string $type 包类型，例如 "library" 或 "happy-module"
     * @param string $lockFilePath composer.lock 文件路径
     * @return array 指定类型的扩展包信息数组
     * @throws Exception
     */
    public static function getPackagesByType(string $type = 'happy-module', string $lockFilePath = 'vendor/composer/installed.json'): array
    {
        $allPackages = self::getAllPackages($lockFilePath);
        return array_filter($allPackages, function ($package) use ($type) {
            return $package['type'] === $type;
        });
    }

    /**
     * 获取除指定类型外的所有 Composer 扩展包信息
     *
     * @param string $excludeType 排除的包类型，例如 "happy-module"
     * @param string $lockFilePath composer.lock 文件路径
     * @return array 排除指定类型后的扩展包信息数组
     * @throws Exception
     */
    public static function getPackagesExceptType(string $excludeType = 'happy-module', string $lockFilePath = 'vendor/composer/installed.json'): array
    {
        $allPackages = self::getAllPackages($lockFilePath);
        return array_filter($allPackages, function ($package) use ($excludeType) {
            return $package['type'] !== $excludeType;
        });
    }
}
