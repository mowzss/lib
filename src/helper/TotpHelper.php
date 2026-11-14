<?php
declare (strict_types=1);

namespace happy\admin\libs\helper;

/**
 * TOTP（RFC 6238）动态口令辅助类
 *
 * 只用 PHP 内置函数实现（hash_hmac / random_bytes），不依赖第三方包，
 * 便于多站点共享使用。默认参数与 Google / Microsoft Authenticator 兼容：
 * SHA1、6 位、30 秒。
 */
class TotpHelper
{
    /** Base32 字母表（RFC 4648） */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * 生成随机密钥（Base32 字符串，默认 20 字节 = 160 bit）
     *
     * @param int $bytes 随机字节数
     * @return string
     * @throws \Exception
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * 生成指定时间片的动态口令
     *
     * @param string   $secret Base32 密钥
     * @param int|null $slice  时间片序号，null 表示取当前时间
     * @param int      $digits 位数，默认 6
     * @param int      $period 时间片长度（秒），默认 30
     * @return string
     */
    public static function code(string $secret, ?int $slice = null, int $digits = 6, int $period = 30): string
    {
        $key   = self::base32Decode($secret);
        $slice = $slice === null ? self::currentSlice($period) : $slice;

        // RFC 4226：以 8 字节大端整数作为消息
        $binary  = hash_hmac('sha1', pack('N*', 0, $slice), $key, true);
        $offset  = ord(substr($binary, -1)) & 0x0F;
        $segment = substr($binary, $offset, 4);
        $value   = unpack('N', $segment)[1] & 0x7FFFFFFF;

        return str_pad((string)($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * 校验动态口令，允许前后各偏移 $window 个时间片以容忍时钟误差
     *
     * @param string $secret Base32 密钥
     * @param string $code   用户提交的口令
     * @param int    $window 偏移窗口（时间片个数）
     * @param int    $digits 位数
     * @param int    $period 时间片长度（秒）
     * @return bool
     */
    public static function verify(string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '' || $secret === '' || strlen($code) !== $digits) {
            return false;
        }

        $now = self::currentSlice($period);
        for ($i = -$window; $i <= $window; $i++) {
            $expect = self::code($secret, $now + $i, $digits, $period);
            if (hash_equals($expect, $code)) {
                return true;
            }
        }

        // 兼容用户输入时把当前时间片当作上一片/下一片提交的情况
        return false;
    }

    /**
     * 计算当前时间片序号
     *
     * @param int $period 时间片长度（秒）
     * @return int
     */
    public static function currentSlice(int $period = 30): int
    {
        return intdiv(time(), $period);
    }

    /**
     * 生成 otpauth:// 绑定 URI（Authenticator 扫码/手动添加都用它）
     *
     * @param string $issuer  发行方（一般用站点名）
     * @param string $account 账号（用户名）
     * @param string $secret  Base32 密钥
     * @param int    $digits  位数
     * @param int    $period  时间片长度（秒）
     * @return string
     */
    public static function provisioningUri(string $issuer, string $account, string $secret, int $digits = 6, int $period = 30): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $query = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => $digits,
            'period'    => $period,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    /**
     * 二进制转 Base32
     *
     * @param string $binary
     * @return string
     */
    public static function base32Encode(string $binary): string
    {
        $bits  = '';
        $len   = strlen($binary);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out  .= self::ALPHABET[bindec($chunk)];
        }

        return $out;
    }

    /**
     * Base32 转二进制（忽略大小写、空格与补位符）
     *
     * @param string $b32
     * @return string
     */
    public static function base32Decode(string $b32): string
    {
        $b32  = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '');
        $bits = '';
        $len  = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $b32[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $out .= chr(bindec($byte));
        }

        return $out;
    }
}