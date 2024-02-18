<?php

namespace App\Http\Middleware;

use Closure;
use App\Api\ApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $_error = '';

        try {
            if( ! JWTAuth::parseToken()->authenticate() ) {
                throw new TokenInvalidException();
            }
        } catch (TokenInvalidException $e) {
            $_error = 'Token Is Invalid';
        } catch(TokenExpiredException $e) {
            $_error = 'Token Expired';
        } catch(\Exception $e) {
            // catch on "token not found"
            $routeName = $request->route()->getName();

            $_error = (!empty($routeName) && strpos($routeName, 'api.guest') === 0)
                ? '' : 'Token Not Found';
        }

        if( $_error !== '' ) {
            return ApiResponse::unauthorized($_error);
        }

        return $next($request);
    }
}
