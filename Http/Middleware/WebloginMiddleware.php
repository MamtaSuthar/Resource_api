<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use Auth;

class WebloginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // return $next($request);
        // dd(Auth::user());
        if(! Auth::check()){
            return redirect()->route('login');
        }
        
        if ( ! $request->user()->isCustomer() ) {
            Auth::logout();
            return redirect(RouteServiceProvider::CUSTOMER)->withError('Unauthorized! Not a valid  User.');
        }else{
            // dd('shiv');
            return $next($request);
        }
    }
}
