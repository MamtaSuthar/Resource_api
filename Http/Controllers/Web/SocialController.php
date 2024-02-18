<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SocialController extends Controller
{
    public function loginWithGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callbackFromGoogle(Request $request)
    {
        try {
            $user = Socialite::driver('google')->user();

            // Check Users Email If Already There
            $is_user = User::where('email', $user->getEmail())->first();
            if(!$is_user){

                $saveUser = User::updateOrCreate([
                    'social_id' => $user->getId(),
                ],[
                    'first_name' => $user->getName(),
                    'email' => $user->getEmail(),
                    // 'password' => Hash::make($user->getName().'@'.$user->getId())
                ]);
            }else{
                $saveUser = User::where('email',  $user->getEmail())->update([
                    'social_id' => $user->getId(),
                ]);
                $saveUser = User::where('email', $user->getEmail())->first();
            }
            $saveUser->syncRoles([User::CUSTOMER]);

            Auth::login($saveUser);
            // Auth::loginUsingId($saveUser->id);

            $saveUser->social_session_id = \Illuminate\Support\Str::random();
            $saveUser->verified = 1;
            $saveUser->email_verified_at = date('Y-m-d');

            $saveUser->save();

            $request->session()->put(['social_login_jx' => $saveUser->social_session_id]);

            if ($redirect_route = $request->session()->pull('redirect_route')) {

                return redirect($redirect_route)
                    ->withSuccess('Login Successfully');
            }
            
            // return redirect()->route('home');
            return redirect()->intended('/')
                ->withSuccess('Login Successfully');
                
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
