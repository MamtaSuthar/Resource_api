<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;

class AuthVendor
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
        if(! Auth::check()){
            return redirect()->route('vendor.index');
        }
        
        if ( ! $request->user()->isVendor()) {
            Auth::logout();
            return redirect(RouteServiceProvider::VENDOR)->withError('Unauthorized! Not a valid Vendor User.');
        }else{
            return $next($request);
        }
    }
}
