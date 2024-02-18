<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;

class AuthAdmin
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
            return redirect()->route('admin.index');
        }
        
        if ( $request->user()->isCustomer() || $request->user()->isVendor()) {
            Auth::logout();
            return redirect(RouteServiceProvider::ADMIN)->withError('Unauthorized! Not a valid Admin User.');
        }else{
            return $next($request);
        }
    }
}
