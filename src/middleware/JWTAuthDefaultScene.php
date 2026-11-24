<?php


namespace happy\admin\libs\middleware;

use think\App;
use think\Request;
use yzh52521\Jwt\JWT;
use yzh52521\Jwt\Util\JWTUtil;

class JWTAuthDefaultScene
{
    protected App $app;

    /**
     * Construct
     * @param App $app
     * @param JWT $jwt
     */
    public function __construct(App $app, protected JWT $jwt)
    {
        $this->app = $app;
    }

    public function handle(Request $request, $next)
    {
        if ($this->app->config->get('route.controller_layer') === 'api') {

            // 免登录白名单：登录入口 + 站点公开配置（Vue 前端首屏需要，且仅返回白名单内的非敏感配置）
//            $publicControllers = ['index.Login', 'site.Config'];
//            if (in_array($request->controller(), $publicControllers, true)) {
//                return $next($request);
//            }
//
            try {
                $token = JWTUtil::getToken($request);
                if ($token === false) {
                    return json(['code' => 401, 'msg' => '未授权']);
                }
                if (!$this->jwt->verifyTokenAndScene('default', $token)) {
                    return json(['code' => 401, 'msg' => '未授权']);
                }
                $jwtConfig = $this->jwt->getJwtSceneConfig();
                $jwtConfig['user_model'] && $request->user = $this->jwt->getUser();
                return $next($request);
            } catch (\Exception $e) {
                return json(['code' => 401, 'msg' => '未授权']);
            }

        }
        return $next($request);
    }
}
