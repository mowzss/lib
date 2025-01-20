<?php

namespace mowzs\lib\baidu;

use mowzs\lib\helper\HttpHelper;
use think\Exception;

class BaseBaiduAip
{
    /**
     * AipAccessToken缓存名称
     * @var string
     */
    protected $CacheName = "BaiduAipAccessToken";
    /**
     * 参数配置
     * @var array|mixed
     */
    protected $config;
    /**
     * 获取Access_token网址
     * https://aip.baidubce.com/oauth/2.0/token
     * @var string
     */
    protected $accessTokenUrl = "https://aip.baidubce.com/oauth/2.0/token";
    /**
     * @var mixed
     */
    protected $client_id;
    /**
     * @var mixed
     */
    protected $client_secret;

    public function __construct($config = [])
    {
        $this->client_id = sys_config('baidu_tag_api');
        $this->client_secret = sys_config('baidu_tag_secret');

    }


    /**
     * @throws Exception
     */
    public function Auth($get = false, $config = [])
    {
        if (empty($get)) {
            $access_token = cache($this->CacheName);
        }


        if (empty($access_token)) {
            if (empty($this->client_id) || empty($this->client_secret)) {
                throw new Exception('未设置百度Token信息');
            }
            $post_data = [
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret
            ];
            $ret = HttpHelper::instance()->post($this->accessTokenUrl, [
                'form_params' => $post_data,
                'headers' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]);
            if (!empty($ret['data']['access_token'])) {
                cache($this->CacheName, $ret['data']['access_token'], $ret['data']['expires_in'] - 1000);
                $access_token = $ret['data']['access_token'];
            } else {
                return false;
            }
        }
        return $access_token;
    }
}
