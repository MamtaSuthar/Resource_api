<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use App\Api\ApiResponse;
use Carbon\Carbon;
use App\Models\User;
use Validator;
use DB;
use Str;
use Mail;
use Hash;

class ForgotPasswordController extends ApiController
{
    //

     public function sendresetlink(Request $request){
        // dd();
        $messages = [];
        $validator = Validator::make($request->all(), [
         
            'email'         => ['required'],
                   

        ], $messages);
       
        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $userdata = User::where('email',$request->email)->first();

        if( $userdata ){
            // if( empty($userdata->password) ) {
            //     return ApiResponse::unauthorized(__('Password reset not available for social emails'));
            // }
            
            $token=Str::random(10);
             $link = url("forgotpassword/$token");
            DB::table('password_resets')->insert(['email' => $request->email, 'token' => $token, 'created_at' => Carbon::now() ]);
            try{
            Mail::send('customer.email.forgetmaillink', ['token' => $token,'username'=>$userdata->name,'actionUrl' => $link], function ($message) use ($request)
            {
                $message->to($request->email);
                $message->subject('Reset Password Notification');
            });
            }catch(\Exception $e) {
                logger('Mail issue: '.$e->getMessage());
            }
            return response()->json([
                "STATUS" =>1,
                "MESSAGE"=>"We just emailed a password reset link",
            ]);

             // return ApiResponse::ok("We just emailed a password reset link");
        } 
        else{
            return response()->json([
                "STATUS" =>0,
                "MESSAGE"=>"No account was found with this email.",
            ]);
            // return ApiResponse::ok('No account was found with this email.');
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
        return view('customer.email.forgetupdatepassword', compact('tokens','email'));
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

        return redirect()->route('login')
            ->with('success', 'Your password has been changed!');


    }    
}
