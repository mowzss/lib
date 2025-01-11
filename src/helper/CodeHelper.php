<?php
declare (strict_types=1);

namespace mowzs\lib\helper;

use mowzs\lib\Exception\RandomGenerationException;
use Random\RandomException;

class CodeHelper
{
    /**
     * @param int $timestamp 时间戳。
     * @return string
     */
    private function compressTimestamp(int $timestamp): string
    {
        $timestampStr = (string)$timestamp;
        if (strlen($timestampStr) < 2) {
            return $timestampStr;
        }
        $firstDigit = intval($timestampStr[0]);
        $secondDigit = intval($timestampStr[1]);
        $sumOfFirstTwoDigits = $firstDigit + $secondDigit;
        return strval($sumOfFirstTwoDigits) . substr($timestampStr, 2);
    }

    /**
     * 生成基于时间戳和随机数的唯一ID。
     *
     * @param int $length 总长度（包括前缀），最小为10。
     * @param string $prefix 前缀（可以为空）。
     * @return string 返回生成的唯一ID。
     * @throws RandomGenerationException
     * @throws RandomException
     */
    public function timestampBasedId(int $length = 10, string $prefix = ''): string
    {
        $length = max($length, 10);
        $timestamp = time();
        $compressedTimestamp = $this->compressTimestamp($timestamp);
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
            throw new RandomGenerationException('Failed to generate random number: ' . $e->getMessage());
        }
        return $prefix . $compressedTimestamp . $randomPart;
    }


    /**
     * 生成完全随机的唯一字符串（包括字母和数字）。
     *
     * @param int $length 总长度（包括前缀），最小为10。
     * @param string $prefix 前缀（可以为空）。
     * @return string 返回生成的唯一字符串。
     * @throws RandomException
     * @throws RandomGenerationException
     */
    public function randomString(int $length = 12, string $prefix = ''): string
    {
        $length = max($length, 10);
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $actualLength = $length - strlen($prefix);
        if ($actualLength < 0) {
            throw new \InvalidArgumentException('Prefix is too long for the specified length.');
        }
        try {
            $randomString = '';
            for ($i = 0; $i < $actualLength; $i++) {
                $randomString .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } catch (\Error $e) {
            throw new RandomGenerationException('Failed to generate random string: ' . $e->getMessage());
        }
        return $prefix . $randomString;
    }

    /**
     * 自动检测文本编码并转换为指定的目标编码，默认为UTF-8。
     *
     * @param string $text 需要转换编码的文本。
     * @param string|null $sourceEncoding 可选参数，指定文本的来源编码。
     * @param string $target 目标编码，默认为'UTF-8'。
     * @return string 返回转换为目标编码后的文本。
     */
    public function convertToTargetEncoding(string $text, ?string $sourceEncoding = null, string $target = 'UTF-8'): string
    {
        if (empty($text)) {
            return '';
        }
        if ($sourceEncoding !== null && mb_check_encoding($text, $sourceEncoding)) {
            if (strcasecmp($sourceEncoding, $target) === 0) {
                return $text;
            }
            return mb_convert_encoding($text, $target, $sourceEncoding);
        }
        $first4Bytes = substr($text, 0, 4);
        $encoding = null;
        switch (true) {
            case $first4Bytes === "\x00\x00\xFE\xFF":
                $encoding = 'UTF-32BE';
                break;
            case $first4Bytes === "\xFF\xFE\x00\x00":
                $encoding = 'UTF-32LE';
                break;
            case isset($first4Bytes[1]) && substr($first4Bytes, 0, 2) === "\xFE\xFF":
                $encoding = 'UTF-16BE';
                break;
            case isset($first4Bytes[1]) && substr($first4Bytes, 0, 2) === "\xFF\xFE":
                $encoding = 'UTF-16LE';
                break;
            default:
                $detectedEncoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'EUC-JP', 'SJIS'], true);
                if ($detectedEncoding !== false) {
                    $encoding = $detectedEncoding;
                }
        }
        if ($encoding === null || strcasecmp($encoding, $target) === 0) {
            return $text;
        }
        return mb_convert_encoding($text, $target, $encoding);
    }
}
