<?php

namespace App\Http\Controllers\Api;

use App\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ApiController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\UserVerify;
use App\Models\HeardFrom;
use App\Models\VerifyUser;
use App\Traits\ManageUserTrait;
use Mail;
use Str;
use JWTAuth;
class AuthController extends ApiController
{
    use ManageUserTrait;
    public function registerCustomer(Request $request)
    {
        
        ## Validate Request Inputs
        $messages = [];

        $validator = Validator::make($request->all(), [
            'name'    => ['required', 'string', 'min:3','max:60'],
            'email'         => ['required', 'email', 'unique:users'],
            'password'      => ['required', 'string', 'min:8'],
            'phone'         => ['required', 'numeric', 'min:10', 'unique:users'],
            'dob'           => ['nullable', 'date'],
            'gender'        => ['nullable', 'in:m,f'],
            'country'       => ['required', 'string'],
            'device_id'     => ['bail', 'nullable', 'max:191'],
            'referred_from'   => ['nullable', 'max:1000'],
            'refer_code'   => ['nullable', 'max:8']

        ], $messages);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        try {
            DB::beginTransaction();
            # Store Validated Inputs
            $validated = $validator->validated();

            $dob = $request->get('dob');

            if( $dob && ($dob = strtotime($dob)) ) {
                $dob = date('Y-m-d', $dob);
            }
            # Create User and store its Information
            $user = User::create([
                'first_name'=> $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'phone'     => $validated['phone'],
                'dob'       => $dob ?? null,
                'gender'    => $validated['gender'] ?? null,
                'type'      => User::CUSTOMER,
                'referred_from' => $validated['referred_from'] ??'',
                'refer_code' => $this->getReferralCode($validated['name']),
                'refer_by' =>(isset($validated['refer_code']))?$this->getUserByRefercode($validated['refer_code']):0,
                'country'  => $validated['country'],
            ]);    
            // dump(isset($validated['refer_code']),$this->getUserByRefercode($validated['refer_code']));
            // dd(isset($validated['refer_code']) && $this->getUserByRefercode($validated['refer_code'])!=0);
            if(isset($validated['refer_code']) && $this->getUserByRefercode($validated['refer_code'])==0){
                return ApiResponse::error('Refer code is Not valid');
            }        

            # Assign Role
            // $user->assignRole(User::CUSTOMER);
            $user->syncRoles([User::CUSTOMER]);

            # Create User Info
            $user->info()->create([
                'dob'       => now()->parse($request->dob)->format('Y-m-d'),
                'country'   => $validated['country'],
            ]);

            if( $device_id = $request->get('device_id') ) {
                $user->devices()->create([
                    'device_id' => $device_id
                ]);

                $user->active_device_id = $device_id;
            }
            
            if(! $token = $this->auth->fromUser($user)){
                // return ApiResponse::unauthorized('Bad Credentials');
                throw new \Exception('JWT could not generate token: 3309');
            }

            $user->session_id = $token;
            $user->save();

            DB::commit();
            
            /*send mail function*/
            try {
                $this->sendverifyMail($user,$user->id);
            } catch(\Exception $e) {
                logger('Signup issue: '.$e->getMessage());
            }
            if(isset($validated['refer_code'])){
                $this->generateCouponCode($user->refer_by);
            }
            # Return Resonse with Token
            // return ApiResponse::ok(
            //     'Registered Successfully & Logged In', 
            //     $this->getUserWithToken($token, $user)
            // );
            return ApiResponse::Notverify2('Verification Link sent to your Email Id. Check your email to verify & Login');
        } catch (\Exception $e) {
            DB::rollBack();
            // dd($e->getMessage());
            logger($e->getMessage());
        }

        return ApiResponse::error('Something went wrong!');
        
    }

    public function heard_from_list()
    {
        $list = HeardFrom::query()
            ->where('status', 1)
            ->get()
            ->pluck(['title'])
            ->toArray();

        $list[] = 'Others';

        return ApiResponse::ok('Heard From List', $list);
    }


    #social Login
    public function social_login(Request $request){
        $messages = [];

        $validator = Validator::make($request->all(), [
            'device_id'    => ['required'],
            'social_id'         => ['required'],
            // 'device_id'     => ['bail', 'nullable', 'max:191', 'unique:user_devices,device_id']

        ], $messages);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $device_platform =$request->platform;

        // $socialDatas = User::where('social_id', $request->get('social_id'))
        //     ->first();

        // if( $socialDatas ) {
        //     $errors = [];

        //     if( empty($socialDatas->dob) && empty($request->get('dob')) ) {
        //         $errors['dob_required'] = null;
        //         $errors['gender_required'] = $socialDatas->gender ?? $request->get('gender');
        //     }

        //     if( empty($socialDatas->gender) && empty($request->get('gender')) ) {
        //         $errors['dob_required'] = $socialDatas->dob ?? $request->get('dob');
        //         $errors['gender_required'] = null;
        //     }

        //     if( !empty($errors) ) {
        //         return ApiResponse::ok(
        //             'Dob or gender not filled',
        //             [
        //                 'dob_required' => $errors['dob_required'],
        //                 'gender_required' => $errors['gender_required']
        //             ]
        //         );
        //     }
        // }
  
        if($device_platform == "android"){
            // $first_name=$request->first_name;
            // $last_name=$request->last_name;
            $email=$request->email;
            $social_id=$request->social_id;
            $socialDatas = User::where('social_id',$social_id)->where('platform','android')->where('email',$email)->first();
            if($socialDatas){
                // $userDatas = User::find($socialDatas->id);
                // dd($userDatas);
                $updates = ['dob', 'country', 'gender',
                    'phone', 'refer_code', 'referred_from'];
                $updated = [];

                foreach( $updates as $val ) {
                    $updated[$val] = !empty($socialDatas->{$val})
                        ? $socialDatas->{$val} : $request->get($val);
                }

                $socialDatas->fill($updated);

                if( $socialDatas->isDirty() ) {
                    $socialDatas->save();
                }
                $token = JWTAuth::fromUser($socialDatas);
                $socialDatas->social_login = true;
                if( empty($socialDatas->refer_code) ) {
                    $socialDatas->refer_code = $this->getReferralCode($socialDatas->first_name);
                    $socialDatas->update();
                }
                return ApiResponse::ok(
                'Login Successful', 
                $this->getUserWithSocialToken($token, $socialDatas)
                );
            }else{
                $socialDatas = User::where('email',$email)->first();
                if($socialDatas){
                    // return response()->json([
                    //         "STATUS"=>0,
                    //         "MESSAGE" => "Email already exists",
                    //         "DATA"=>(object)[]
                    // ]);
                    $userDatas = User::find($socialDatas->id);
                   // dd($userDatas);
                    $token = JWTAuth::fromUser($userDatas);
                    if( empty($socialDatas->refer_code) ) {
                        $socialDatas->refer_code = $this->getReferralCode($socialDatas->first_name);
                        $socialDatas->update();
                    }
                    return ApiResponse::ok(
                    'Login Successful', 
                    $this->getUserWithSocialToken($token, $userDatas)
                    );
                }
                $users=new User();
                $users->verified = 1;
                $users->first_name=$request->input('name');
                $users->last_name=$request->input('name');
                $users->email=$request->input('email');
                $users->email_verified_at=now();
                $users->remember_token=Str::random(10);
                $users->social_login_with="gmail";
                $users->platform=$request->input('platform');
                $users->social_id=$request->input('social_id');
                $users->dob=$request->input('dob');
                $users->country=$request->input('country') ? $request->input('country') : 'GH' ;
                $users->gender=$request->input('gender');
                $users->refer_code = $this->getReferralCode($request->input('name'));
                $users->phone = $request->input('phone');
                $users->referred_from = $request->input('referred_from');
            
                $users->save();
                $users->syncRoles([User::CUSTOMER]);

                # Create User Info
                $users->info()->create([
                    'dob'       => now()->parse($request->input('dob'))->format('Y-m-d'),
                    'country'   => $request->input('country'),
                ]);
                $token = JWTAuth::fromUser($users);
            
                $datas=array('user'=>$users,'token'=>$token);
                $users->social_login = true;
                // dd($users);
                if( empty($users->refer_code) ) {
                    $users->refer_code = $this->getReferralCode($users->first_name);
                    $users->update();
                }
                return ApiResponse::ok(
                'Registered Successfully & Logged In', 
                $this->getUserWithSocialToken($token, $users)
                );
            
            }

        }
        else if($device_platform == "ios"){
            // dd();

            // $first_name=$request->first_name;
            // $last_name=$request->last_name;
            $email=$request->email;
            $social_id=$request->social_id;
            // $socialDatas = User::where('social_id',$social_id)->where('platform','ios')->where('email',$email)->first();
            $socialDatas = User::where('social_id',$social_id)->where('platform','ios')->first();
            if($socialDatas){
                // $userDatas = User::find($socialDatas->id);
                // dd($userDatas);
                $updates = ['dob', 'country', 'gender',
                    'phone', 'refer_code', 'referred_from'];
                $updated = [];

                foreach( $updates as $val ) {
                    $updated[$val] = !empty($socialDatas->{$val})
                        ? $socialDatas->{$val} : $request->get($val);
                }

                $socialDatas->fill($updated);

                if( $socialDatas->isDirty() ) {
                    $socialDatas->save();
                }
                $token = JWTAuth::fromUser($socialDatas);
                $socialDatas->social_login = true;
                if( empty($socialDatas->refer_code) ) {
                    $socialDatas->refer_code = $this->getReferralCode($socialDatas->first_name);
                    $socialDatas->update();
                }
                return ApiResponse::ok(
                'Login Successful', 
                $this->getUserWithSocialToken($token, $socialDatas)
                );

            }else{
                // dd();
                $socialEmail = User::where('email',$email)->first();
                if($socialEmail){
                    // return response()->json([
                    //         "STATUS"=>409,
                    //         "MESSAGE" => "Email Has Already Been Taken",
                    //         "DATA"=>(object)[]
                    // ]);
                    $userDatas = User::find($socialEmail->id);
                    // dd($userDatas);
                    $token = JWTAuth::fromUser($userDatas);
                    $userDatas->social_login = true;
                    if( empty($userDatas->refer_code) ) {
                        $userDatas->refer_code = $this->getReferralCode($userDatas->first_name);
                        $userDatas->update();
                    }
                    return ApiResponse::ok(
                    'Login Successful', 
                    $this->getUserWithSocialToken($token, $userDatas)
                    );
                }
                // dd($socialDatas);
                $users=new User();
                $users->verified = 1;
                $users->first_name=$request->input('name');
                $users->last_name=$request->input('name');
                $users->email=$request->input('email');
                $users->email_verified_at=now();
                $users->remember_token=Str::random(10);
                $users->social_login_with="Apple";
                $users->platform=$request->input('platform');
                $users->social_id=$request->input('social_id');
                $users->dob=$request->input('dob');
                $users->country=$request->input('country') ? $request->input('country') : 'GH' ;
                $users->gender=$request->input('gender');
                $users->refer_code = $this->getReferralCode($request->input('name'));
                $users->phone = $request->input('phone');
                $users->referred_from = $request->input('referred_from');
            
                $users->save();
                $users->syncRoles([User::CUSTOMER]);

                # Create User Info
                $users->info()->create([
                    'dob'       => now()->parse($request->input('dob'))->format('Y-m-d'),
                    'country'   => $request->input('country'),
                ]);
                $token = JWTAuth::fromUser($users);
                //$token=""
                $datas=array('user'=>$users,'token'=>$token);
                $users->social_login = true;
                if( empty($users->refer_code) ) {
                    $users->refer_code = $this->getReferralCode($users->first_name);
                    $users->update();
                }
                return ApiResponse::ok(
                'Registered Successfully & Logged In', 
                $this->getUserWithSocialToken($token, $users)
                );
            
            }

        }
    }

    public function login(Request $request)
    {
        ## Validate Request Inputs
        $messages = [];

        $validator = Validator::make($request->all(), [
            'email'         => ['required', 'email'],
            'password'      => ['required', 'string'],
        ], $messages);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $validated = $validator->validated();

        if(! $token = $this->auth->attempt($validated)){
            return ApiResponse::unauthorized('You have entered an invalid username or password.');
        }
        # Get the User
        $user = $this->user();
        if($user->verified==0){
            return ApiResponse::Notverify2("For Login Verify your email first.");
            // return ApiResponse::Notverify2("For Login Verify your email first.");
        }
        # Validate for the customer only
        if(! $user->isCustomer()){
            return ApiResponse::unauthorized('Invalid Customer');
        }else{
            if( $user->active_device_id &&
                $request->has('device_id') &&
                $user->active_device_id !== $request->get('device_id') ) {

                if( ! $request->has('no_override') && false ) {
                    return ApiResponse::unauthorized(
                        __("You're already logged into another device")
                    );
                }
            }

            try {
                if( $user->session_id ) {
                    $this->auth->setToken($user->session_id)->invalidate(true);
                }
            } catch(\Exception $e) {
                logger($e->getMessage());
            }

            $user->active_device_id = $request->get('device_id');
            $user->session_id = $token;
            $user->update();
        }

        if( empty($user->refer_code) ) {
            $user->refer_code = $this->getReferralCode($user->first_name);
            $user->update();
        }

        # Return Resonse with Token
            return ApiResponse::ok(
                'Login Successful', 
                $this->getUserWithToken($token, $user)
            );


    }

    public function logout()
    {
        $user =auth()->user()->id ;
        User::where('id',$user)->update(['session_id'=>null]);
        $this->auth->logout();

        return ApiResponse::ok('Logged Out Successfully ');
    }

    public function getUserWithSocialToken($token, $user)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->auth->factory()->getTTL() * 60,
            // 'topics' => $this->getTags($user->id),
            'topics' => $this->getTags($user->id),
            'push_enabled' => $user->setting
                ? boolval($user->setting->push_notification)
                : false,
            'user' => $user
        ];
    }

    public function getUserWithToken($token, $user)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->auth->factory()->getTTL() * 60,
            // 'topics' => $this->getTags($user->id),
            'topics' => $this->getTags($user->id),
            'push_enabled' => $user->setting
                ? boolval($user->setting->push_notification)
                : false,
            'user' => $user->format()
        ];
    }

    # function usedfor sendmail using userverify mail
    public function sendverifyMail($user,$user_id){
        $verify_user = VerifyUser::create([
            'user_id'=>$user_id,
            'token'     => sha1(time())
                
        ]);
        // echo "<pre>";
        // print_r(User::with('verifyuser')->get());
        // die;
        Mail::to($user)->send(new UserVerify($user));

    }

    public function verifyLink($token){
      $verifyuser=VerifyUser::where('token',$token)->first();
        if(isset($verifyuser)){
            $user=$verifyuser->user;
            if(!$user->verified){
                $verifyuser->user->verified=1;
                  $verifyuser->user->save();
                  $email = $user->email;
                  try{
                   Mail::send('customer.email.confirmemail',
                     array(
                         'name' => $user->first_name." ".$user->last_name,
                         'email' => $user->email,
                         // 'subject' => $request->subject,
                         // 'phone_number' => $phone_number,
                         // 'user_message' => $request->feedback,
                     ), function($message) use ($email)
                       {
                          // $message->from("accounts@graphicnewsplus.com");
                          $message->to($email)->subject('Confirmation Email');
                       });
               }catch(\Exception $e){
                logger(' issue: '.$e->getMessage());
                }
                // return response()->json([
                //     'status'=>true,
                //     'message'=>'Your e-mail is verified. You can now login.',
                //     // "data"=>null
                // ],200);
                return redirect()->route('home')->with('success', "Your e-mail is verified. You can now login.");
            }
            else{
                // return response()->json([
                //     'status'=>true,
                //     'message'=>'Your e-mail is already verified. You can now login.',
                //     // "data"=>null
                // ],200);
                return redirect()->route('home')->with('success', "Your e-mail is already verified. You can now login.");
             }

        }else{
                return response()->json([
                'status'=>false,
                'message'=>'Your token is invalid',
                // "data"=>null
                 ],404);

        }
    }    

    public function addDevice(Request $request)
    {
        $messages = [];

        $validator = Validator::make($request->all(), [
            'device_id'    => ['bail', 'required', 'max:191', 'unique:user_devices,device_id']
        ], $messages);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $user = $this->user();

        $user->devices()->create([
            'device_id' => $request->get('device_id')
        ]);

        return ApiResponse::ok('Logged Out Successfully ');
    }
    public function changepassword(Request $request){
        // dd('shiv');
        // $userId=$request->user_id;
        $userId=$request->id;
        $result=User::where(["id"=>$userId])->first();
        // dd($result);
        if($result && Hash::check($request->oldPassword,$result->password))
        {
            
            $result->password=Hash::make($request->newPassword);
            $save=$result->save();
            if($save)
            {
                return ApiResponse::ok("The password has been changed successfully.");
            }
            else{
                return ApiResponse::bad_request("Error while changing password.");
            }
        }
        else
        {
           
              return ApiResponse::forbidden("Invalid old password.");
            
        }
      }

}
