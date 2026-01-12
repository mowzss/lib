<?php

namespace mowzs\lib\middleware;

use think\Request;
use yzh52521\Jwt\JWT;
use yzh52521\Jwt\Util\JWTUtil;

class JWTAuthDefaultScene
{
    /**
     * Construct
     * @param JWT $jwt
     */
    public function __construct(protected JWT $jwt)
    {
    }

    public function handle(Request $request, $next)
    {
        if ($this->app->config->get('route.controller_layer') == 'api') {
            $token = JWTUtil::getToken($request);
            if ($token !== false && $this->jwt->verifyTokenAndScene('default', $token)) {
                $jwtConfig = $this->jwt->getJwtSceneConfig();
                $jwtConfig['user_model'] && $request->user = $this->jwt->getUser();
                return $next($request);
            }
            return json(['code' => 401, 'msg' => '未授权']);
        }
        return $next($request);
    }
}
