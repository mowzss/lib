<?php
declare (strict_types=1);


namespace happy\admin\libs\helper;

use happy\admin\libs\Exception\LibsException;

class CodeHelper
{
    /**
     * 压缩时间戳。
     * @param int $timestamp 时间戳。
     * @return string
     */
    private static function compressTimestamp(int $timestamp): string
    {
        $timestampStr = (string)$timestamp;
        if (strlen($timestampStr) < 2) {
            return $timestampStr;
        }
        $firstDigit = (int)$timestampStr[0];
        $secondDigit = (int)$timestampStr[1];
        $sumOfFirstTwoDigits = $firstDigit + $secondDigit;
        return $sumOfFirstTwoDigits . substr($timestampStr, 2);
    }

    /**
     * 生成基于时间戳和随机数的唯一ID。
     *
     * @param int $length 总长度（包括前缀），最小为10。
     * @param string $prefix 前缀（可以为空）。
     * @return string 返回生成的唯一ID。
     * @throws LibsException|\Random\RandomException
     */
    public static function timestampBasedId(int $length = 10, string $prefix = ''): string
    {
        $length = max($length, 10);
        $timestamp = time();
        $compressedTimestamp = self::compressTimestamp($timestamp);
        $remainingLength = $length - strlen($prefix) - strlen($compressedTimestamp);
        if ($remainingLength < 0) {
            throw new \InvalidArgumentException('Prefix is too long for the specified length.');
        }
        try {
            $randomPart = '';
            for ($i = 0; $i < $remainingLength; $i++) {
                $randomPart .= random_int(0, 9);
            }
        } catch (\Error $e) {
            throw new LibsException('Failed to generate random number: ' . $e->getMessage());
        }
        return $prefix . $compressedTimestamp . $randomPart;
    }

    /**
     * 生成完全随机的唯一字符串（包括字母和数字）。
     *
     * @param int $length 总长度（包括前缀），最小为10。
     * @param string $prefix 前缀（可以为空）。
     * @return string 返回生成的唯一字符串。
     * @throws LibsException
     */
    public static function randomString(int $length = 12, string $prefix = ''): string
    {
        $length = max($length, 10);
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $actualLength = $length - strlen($prefix);
        if ($actualLength < 0) {
            throw new \InvalidArgumentException('Prefix is too long for the specified length.');
        }

        try {
            $randomString = '';
            $charLength = strlen($characters);
            $bytes = random_bytes($actualLength);

            for ($i = 0; $i < $actualLength; $i++) {
                $randomString .= $characters[ord($bytes[$i]) % $charLength];
            }
        } catch (\Exception $e) {
            throw new LibsException('Failed to generate random string: ' . $e->getMessage(), 0, $e);
        }

        return $prefix . $randomString;
    }
    
}
