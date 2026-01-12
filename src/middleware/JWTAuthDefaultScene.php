<?php

namespace mowzs\lib\middleware;

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
        if ($this->app->config->get('route.controller_layer') == 'api') {
            if ($request->controller() == 'index.Login') {
                return $next($request);
            }
            try {
                $token = JWTUtil::getToken($request);
                if ($token !== false && $this->jwt->verifyTokenAndScene('default', $token)) {
                    $jwtConfig = $this->jwt->getJwtSceneConfig();
                    $jwtConfig['user_model'] && $request->user = $this->jwt->getUser();
                    return $next($request);
                }
            } catch (\Exception $e) {
                return json(['code' => 401, 'msg' => '未授权']);
            }

        }
        return $next($request);
    }
}
