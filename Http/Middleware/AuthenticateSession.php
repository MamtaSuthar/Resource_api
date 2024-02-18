<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;
use Laravel\Socialite\Facades\Socialite;

class AuthenticateSession extends BaseAuthenticateSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {
        // customer auto-login - coming from app
        if( $al_7624109 = $request->query('al_7624109') ) {

            try {
                if( $user_id = intval(decrypt($al_7624109)) ) {
                    if( $user = \App\Models\User::find($user_id) ) {
                        auth()->guard('web')->login($user);

                        return redirect( $request->fullUrlWithoutQuery(['al_7624109']) );
                    }
                }
            } catch(\Exception $e) {
                logger($e->getMessage());
            }
        }

        // pdf-viewer download restriction mechanism
        if( $request->is(["pdf/*/viewer", "pdf/*/pdfviewer"]) ) {
            $is_magazine = $request->is("pdf/*/viewer");
            $id = intval($request->segment(2));

            $crf = (array)$request->session()->get('_content_read_fresh');
            $crf = \array_filter($crf);

            $ak = $is_magazine ? "m-{$id}": "n-{$id}";

            $crf[$ak] = \Illuminate\Support\Str::random(16);

            $request->session()->put('_content_read_fresh', $crf);
        }
        
        if (! $request->hasSession() || ! $request->user()) {
            return $next($request);
        }

        $session = $request->session();

        if( $session->has('social_login_jx') ) {

            $user = $request->user();

            if( $session->get('social_login_jx') != $user->social_session_id ) {
                $this->logout($request);
            }
        }

        return parent::handle($request, $next);
    }
}
