<?php

namespace mowzs\lib\helper;


use think\facade\Config;

class CryptoHelper
{

    /**
     * Base64 编码
     *
     * @param string $data 要编码的数据
     * @return string 编码后的数据
     */
    public static function base64Encode(string $data): string
    {
        return base64_encode($data);
    }

    /**
     * Base64 解码
     *
     * @param string $encodedData 编码后的数据
     * @return string 解码后的数据
     */
    public static function base64Decode(string $encodedData): string
    {
        return base64_decode($encodedData, true) ?: '';
    }

    /**
     * AES 加密
     *
     * @param string $plaintext 明文数据
     * @param string|null $key 加密密钥，默认使用配置中的'default_encryption_key'
     * @param string $method 加密算法，默认为 'aes-256-cbc'
     * @return string|false 密文数据或false（失败时）
     */
    public static function aesEncrypt(string $plaintext, ?string $key = null, string $method = 'aes-256-cbc')
    {
        if ($key === null) {
            $key = Config::get('happy.default_encryption_key');
        }

        // 确保密钥长度符合所选加密方法的要求
        $keyLength = openssl_cipher_iv_length($method);
        if (strlen($key) !== $keyLength) {
            throw new \InvalidArgumentException("Encryption key must be {$keyLength} bytes long for method '{$method}'.");
        }

        // 生成一个初始化向量(iv)
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);

        // 加密数据
        $ciphertext = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return false;
        }

        // 返回 iv 和 ciphertext 的 base64 编码组合
        return self::base64Encode($iv . $ciphertext);
    }

    /**
     * AES 解密
     *
     * @param string $ciphertext 密文数据
     * @param string|null $key 加密时使用的密钥，默认使用配置中的'default_encryption_key'
     * @param string $method 加密算法，默认为 'aes-256-cbc'
     * @return string|false 解密后的明文数据或false（失败时）
     */
    public static function aesDecrypt(string $ciphertext, ?string $key = null, string $method = 'aes-256-cbc')
    {
        if ($key === null) {
            $key = Config::get('secure.default_encryption_key');
        }

        // 确保密钥长度符合所选加密方法的要求
        $keyLength = openssl_cipher_iv_length($method);
        if (strlen($key) !== $keyLength) {
            throw new \InvalidArgumentException("Decryption key must be {$keyLength} bytes long for method '{$method}'.");
        }

        // 解码并分离 iv 和 ciphertext
        $ciphertext = self::base64Decode($ciphertext);
        $ivlen = openssl_cipher_iv_length($method);
        $iv = substr($ciphertext, 0, $ivlen);
        $ciphertext = substr($ciphertext, $ivlen);

        // 解密数据
        return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
    }
}
