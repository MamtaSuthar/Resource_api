<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Str;
use Carbon\Carbon;
use DB;
use Mail;
use Hash;
class ForgotPasswordController extends Controller
{
   
    public function index()
    {
         return view('vendoruser/forgotpassword/forgotpasswordemail');
    }

    public function forgotpassword(Request $request)
    {

        $this->validate($request, [
            'email' => "required",
        ],
            [
                'email.required' => "Please Enter Email Address"
            ]
        );

        $userEmail = User::where('email', $request->email)->first();
        if(isset($userEmail)&& ! $userEmail->isVendor()){
            return back()->with('error','Unauthorized! Not a valid  Vendor.');
        }
        if ($userEmail) {

            $token = Str::random(30);
            // $userEmail->password_token = $token;
            // $userEmail->save();
            //  $link = config('app.url') . '/' . 'forgotpassword/' . $token; 
            $link = url("vendor/forgotpassword/$token");
              // $email=$this->forgetPasswordEmail('POST',$userEmail, $link);
            $email = $userEmail->email;
            DB::table('password_resets')->insert(['email' => $email, 'token' => $token, 'created_at' => Carbon::now() ]);
            try{
                Mail::send('vendoruser.forgotpassword.forgottemplate',
                    [
                        'username' => ucfirst($userEmail->name),
                        'actionUrl' => $link
                    ],
                    function ($m) use ($email) {
                        // $m->from('admin@magazine.com', 'Magazine GCGL');
                        $m->to($email)->subject('Forgot Password!');
                    });
            } catch (\Exception $e) {
                    $error_message = $e->getMessage();

                  }

            return redirect('/vendor')->with('success', "we have sent a reset password link to this email: $email");
        } else {

            return back()->with('error', "Sorry, this email not registered with us please try another.");
        }
    }

    public function checktoken($token){
   
        $tokens = $token;
        // dd($token);
        $change = DB::table('password_resets')->where('token', $token)->first();
        if(!empty($change)){
        $email=$change->email;
        }
        // dd($change);
        if (!$change) {
            echo "Password recovery link has expired, Please try again.";
            die;
            // return back()->with('error', 'Password recovery link has expired, Please try again.');
        }
        return view('vendoruser.forgotpassword.forgotupdatepassword', compact('tokens','email'));
    }

    public function forgetpasswordUpdate(Request $request){
        $this->validate($request, [
            "new_password" => "required|min:6",
            "confirm_password" => "required|min:6|same:new_password",
        ], [
            "new_password.required" => "Please Enter Password",
            "confirm_password.required" => "Please Confirm Password",

        ]);
        $email = $request['email'];
        $token = $request['password_token'];
        $am = $request['new_password'];
        $new = $request['confirm_password'];

        $updatePassword = DB::table('password_resets')->where(['email' => $email, 'token' => $token])
            ->first();

        //print_r($updatePassword);exit;
        if (!$updatePassword) return back()->withInput()
            ->with('error', 'Invalid token!');

        $user = User::where('email', $email)
            ->update(['password' => Hash::make($new) ]);

        DB::table('password_resets')
            ->where(['email' => $email])
            ->delete();

        return redirect('/vendor')
            ->with('success', 'Your password has been changed!');


    }   
}
