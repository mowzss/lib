<?php

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class SystemHelper extends Helper
{
    /**
     * 检测是否使用手机访问
     * @return bool 可重写判断 自定义全局使用wap检测规则
     */
    public function isMobile(): bool
    {
        if ($this->app->request->server('HTTP_VIA') && stripos($this->app->request->server('HTTP_VIA'), "wap") !== false) {
            return true;
        }
        
        if ($this->app->request->server('HTTP_ACCEPT') && str_contains(strtoupper($this->app->request->server('HTTP_ACCEPT')), "VND.WAP.WML")) {
            return true;
        }
        
        if ($this->app->request->server('HTTP_X_WAP_PROFILE') || $this->app->request->server('HTTP_PROFILE')) {
            return true;
        }
        
        if ($this->app->request->server('HTTP_USER_AGENT') && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_|OpenHarmony|Phone)/i', $this->app->request->server('HTTP_USER_AGENT'))) {
            return true;
        }
        
        return false;
    }
}
