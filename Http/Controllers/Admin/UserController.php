<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\UserImport;
use App\Imports\UserImportByCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Mail;
use App\Mail\CustomerVerify;
use App\Models\Payment;
use App\Models\Plan;
use Monarobase\CountryList\CountryListFacade;
use Hash;
use Illuminate\Support\Facades\Redirect;
use Cookie;
use App\Models\UserInfo;
use App\Models\VerifyUser;
use App\Traits\ManageUserTrait;
use App\Vars\Helper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    use ManageUserTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        # User Query Instance
        $query = User::whereHas('roles', function($q){
            $q->where('name', '<>', User::SUPERADMIN);
        });
        # Handle Filter Queries
        if($request->has('subsc_type') && !is_null($request->subsc_type)){
            // $query->whereHas('subscription', function($q) use ($request){
                // $q->where('status', $request->input('subsc_type'));
            // });
            $status = strval($request->get('subsc_type'));
            if(in_array($status, ['1', '0'])){
                $query = $query->where('status', $status);
            }
        }
        # Filter Users By Type
        $type = $request->input('type');
        if(in_array($type, [User::VENDOR, User::CUSTOMER, User::COMPANY])){
            $query->role([$type]);
        }
        # Get Users Collection
        $users = $query->orderBy('id','DESC')->with(['referred_by'])->get();
        return view('admin.users.index', compact('users','type'));
    }

    

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $countries =  CountryListFacade::getList('en');

        $plans = Plan::query()
            ->with(['durations'])
            ->get();

        return view('admin.users.create',compact('countries', 'plans'));
    }
    public function importUsers(Request $request){
        try {
            Excel::import(new UserImportByCollection,$request->file('file'));
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $fail = collect($e->failures())->first();
            return back()->with('error',$fail->errors()[0].' at row number '.$fail->row());
        }

        return back()->withSuccess('Registration successfull');
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name'=> ['required','string','min:4','max:30'],
            'email' => ['required','email','unique:users','max:191'],
            'phone'         => ['required', 'digits_between:8,12', 'unique:users,phone'],
            'dob'           => ['nullable', 'date'], 
            'password' => ['required','confirmed', Password::min(8)],
            'password_confirmation'=>['required'],
            'country'       => ['required', 'string'],
            'user_role' => ['nullable', 'in:user,company']
        ]);
        // dd($validated);
        try{
            # Update Query Data
            $validated['password'] = Hash::make($validated['password']);
            $validated['first_name']=$validated['full_name'];
            $validated['refer_code']=$this->getReferralCode($validated['full_name']);
            
            // $validated['last_name']=$validated['full_name'];
            # Create User Account
            $user = User::create(
                collect($validated)->toArray()
            );
            # Assign Role
            if( $request->get('user_role') == 'company' ) {
                $user->syncRoles([User::COMPANY]);
            } else {
                $user->syncRoles([User::CUSTOMER]);
            }
            $user->info()->create([
                'dob'       => now()->parse($request->input('dob'))->format('Y-m-d'),
                'country'   => $request->input('country'),
            ]);

            if( $plan_id = intval($request->get('plan_id')) ) {
                
                if( $plan = Plan::find($plan_id) ) {
                    $this->subscribeToPlan($user, $request);

                    // send mail to customer
                    \App\Vars\SystemMails::customer_new_registration(
                        $user, $plan->title
                    );
                }
            }

            try {
                $this->sendverifyMail($user, $user->id);
            } catch(\Exception $e) { logger($e->getMessage()); }
            # Create User Info
            
            
            return redirect()->route('admin.users.index')->withSuccess('Account Successfully Created');
        }
        catch(\Exception $e){
            // dd($e);
            logger($e->getMessage());
        }
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
        Mail::to($user)->send(new CustomerVerify($user));

    }

    public function subscribeToPlan(User $user, Request $request)
    {
        if( $plan_id = $request->get('plan_id') ) {
            if( $plan_duration_code = $request->get('plan_duration_id') ) {

                try {
                    $plan = Plan::findOrFail($plan_id);
                    $plan_duration = $plan->durations()
                        ->where('code', $plan_duration_code)
                        ->get();

                    $plan_duration = $plan_duration
                        ->firstWhere('currency', $user->my_currency);

                    if( empty($plan_duration) ) {
                        return 0;
                    }

                    $uuid = \Illuminate\Support\Str::uuid();

                    $amount = \number_format($plan_duration->price, 2, '.', '');

                    // create new payment
                    $payment = Payment::create([
                        'user_id' => $user->id,
                        'currency' => $user->my_currency,
                        'amount' => $amount,
                        'payment_method' => 'OFFLINE',
                        'status' => 'SUCCESS',
                        'local_ref_id' => $uuid
                    ]);


                    // crate subscription
                    $now = date('Y-m-d H:i:s');

                    $referral_code = "MAG{$user->id}-{$plan->id}-".Helper::generate_random_code();

                    $expires_at = Helper::add_days(
                        now(),
                        Helper::get_days_plan_duration($plan_duration->code)
                    )
                    ->format('Y-m-d H:i:s');

                    $sub = $user->subscriptions()->create([
                        'plan_id' => $plan->id,
                        'plan_duration_id' => $plan_duration->id,
                        'payment_id' => $payment->id,
                        'purchased_at' => Helper::to_price($amount),
                        'is_family' => 0,
                        'pay_status' => 1,
                        'referral_code' => $referral_code,
                        'total_members' => 0,
                        'subscribed_at' => $now,
                        'expires_at' => $expires_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return $sub;

                } catch(\Exception $e) {
                    logger($e->getMessage());
                }
            }
        }

        return 0;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        $user->load(['subscriptions', 'bought_magazines', 'bought_newspapers']);

        $subscriptions =
            \App\Http\Resources\UserSubscription::collection($user->subscriptions)
                ->resolve();

        foreach( $subscriptions as &$subscription ) {
            $subscription = (object)$subscription->resolve();
        }

        return view('admin.users.detail', compact('user', 'subscriptions'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, User $user)
    {
        $countries =  CountryListFacade::getList('en');

        $my_subscriptions = $user->subscriptions()
            ->latest()
            ->with(['plan'])
            ->get()
            ->filter(function($subscription) {
                return !empty($subscription->plan_duration);
            });
        if($user->isReporter() || $user->isAdmin()){
            $permissions = Permission::all();
            return view('admin.users.edit', compact('user','countries', 'my_subscriptions','permissions'));
        }else{
            return view('admin.users.edit', compact('user','countries', 'my_subscriptions'));
        }
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        if($user->isReporter() || $user->isAdmin()){
            $request->validate([
                'status'        => ['required', 'in:0,1'],
                // 'email'         => ['required','email','max:25'],
                // 'full_name'     => ['required','string','regex:/^[a-zA-Z]+$/u','min:4','max:10'],
                'role'          =>['required','in:admin,reporter'],
            ]);

            $user->status = $request->input('status');
            $user->save();
            $user->syncRoles($request->role);
            if($request->has('permission')){
                $user->syncPermissions($request->permission);
            }
            
            return redirect()->route('admin.users.systemUsers')->withSuccess('Admin Updated Successfully');
        }else{
            if($request->status == "0" && $user->role_name =="vendor" ){
                $this->vendorDeactivated($user);
            }
            if($request->status == "0" && $user->role_name =="vendor" ){
                $this->vendorActivated($user);
            }
            # Handle Verify Vendor Action
            if($request->has('verify_vendor')){
                $verify_vendor = $request->input('verify_vendor');
                if($verify_vendor === 'approve'){
                    $user->vendor_verified = 1;
                    $message = 'Vendor Approved Successfully';
                    $email =$user->email;
                    try{
                    Mail::send('vendoruser.email.uniquelink',
                         array(
                             'name' => $user->first_name." ".$user->last_name,
                             'email' => $user->email,
                             // 'password' => $user->password,
                             // 'phone_number' => $phone_number,
                             // 'user_message' => $request->feedback,
                         ), function($message) use ($email)
                           {
                              // $message->from("admin@magazine.com");
                              $message->to($email)->subject('Vendor ');
                           });
                }catch(\Exception $e){
                    logger(' issue: '.$e->getMessage());
                    
                }
                }else{
                    $user->vendor_verified = 0;
                    $message = 'Vendor Disapproved Successfully';
                }
                # Save user records and return message
                $user->save();
                return redirect()->route('admin.users.index', ['type' => $user->type])
                    ->withSuccess($message);
            }
            # Handle User Update Action
            // dd($user);
            $request->validate([
                'status'        => ['required', 'in:0,1'],
                'phone'         => ['nullable', 'digits_between:8,12', 'unique:users,phone,'.$user->id],
                // 'email'         => ['required','email','max:25'],
                // 'full_name'     => ['required','string','regex:/^[a-zA-Z]+$/u','min:4','max:10'],
                'country'       => ['required', 'string'],
            ]);
            
            # Update User
            // $userdata = [
            //     'phone'=>$request->phone,
            //     'dob'=>$request->dob,
            //     'country'=>$request->country,
            //     'staus'=>$request->staus,
            // ];
            // dd($userdata);
            // $user->update($userdata);
            $user->phone = $request->phone;
            $user->dob = $request->dob;
            $user->country = $request->country;
            $user->status = $request->input('status');
            $user->save();
    
            if( !empty($expires_at_updates = $request->get('expires_at_update')) ) {
                $my_subscriptions = $user->subscriptions()
                    ->latest()
                    ->with(['plan'])
                    ->get()
                    ->filter(function($subscription) {
                        return !empty($subscription->plan_duration);
                    });
    
                foreach( (array)$expires_at_updates as $subcription_id => $expires_at ) {
                    $subscription = $my_subscriptions->find($subcription_id);
    
                    if( $subscription ) {
                        try {
                            $original = $subscription->expires_at->format('Y-m-d');
                            $new = Carbon::parse($expires_at)->format('Y-m-d');
    
                            if( $new !== $original ) {
                                $subscription->expires_at = $new;
                                $subscription->update();
                            }
    
                        } catch(\Exception $e) { logger($e->getMessage()); }
                    }
                }
            }
            
    
            if($user->isCustomer() || $user->isVendor()){
                return redirect()->route('admin.users.index', ['type' => $request->type])
                    ->withSuccess(ucfirst($user->type_text)." updated successfully");
            }else{
                return redirect()->route('admin.users.index')->withSuccess('Admin Updated Successfully');
            }
        }
        
    }

    public function vendorActivated($user){
        try{
            \Mail::send('mail/vendor/vendoractivated', array( 
                'name' => $user['first_name'],
            ), function($message) use ($user){ 
                $message->from('admin@magazine.com'); 
                $message->to($user->email, 'User')->subject("Vendor Activated"); 
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    } 

    public function vendorDeactivated($user){
        try{
            \Mail::send('mail/vendor/vendordeactivated', array( 
                'name' => $user['first_name'],
            ), function($message) use ($user){ 
                $message->from('admin@magazine.com'); 
                $message->to($user->email, 'User')->subject("Vendor DeActivated"); 
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function system_users(Request $request)
    {
        # User Query Instance
        $query = User::whereHas('roles', function($q){
            $q->whereNotIn('name',[User::SUPERADMIN,User::CUSTOMER,User::VENDOR]);
        });
        
        # Filter Users By Type
        $type = $request->input('type');
        if(in_array($type, [User::ADMIN, User::REPORTER])){
            $query->whereHas('roles', function($q) use ($type){
                $q->where(['name' => $type]);
            });
        }
        # Get Users Collection
        $users = $query->orderBy('id','DESC')->get();
        return view('admin.users.systemUsers', compact('users','type'));
    }

    public function createSystemUser()
    {
        // Permission::create('')
        // $rts = Route::getRoutes()->getRoutesByName();
        // dd($rts);
        $permissions = Permission::all();
        // dd($permissions);
        return view('admin.users.createSystemUser',compact('permissions'));
        
    }

    public function storesystemuser(Request $request){
        $validated = $request->validate([
            'full_name'=> ['required','string','min:4','max:30'],
            'email' => ['required','email','unique:users','max:25'],
            'password' => ['required','confirmed', Password::min(8)],
            'password_confirmation'=>['required']
        ]);

        try {
            $validated['password'] = Hash::make($validated['password']);
            $validated['first_name']=$validated['full_name'];

            # Create User Account
            $user = User::create(
                collect($validated)->toArray()
            );
            # Assign Role
            $user->syncRoles([$request->role]);
            $user->info()->create([
                'dob'       => now()->format('Y-m-d'),
                'country'   => 'GH',
            ]);
            $user->syncPermissions($request->permission);
            return redirect()->route('admin.users.systemUsers')->withSuccess('Account Successfully Created');
        } catch (\Throwable $th) {
            //throw $th;
            logger($th->getMessage());
        }
    }

    public function sendPasswordResetLink(Request $request)
    {

        try {

            $customer_controller =
                app(\App\Http\Controllers\Api\Customer\ForgotPasswordController::class);

            if( !$customer_controller ) {
                throw new \Exception('Class Controllers\Api\Customer not found');
            }

            $respone = $customer_controller->sendresetlink($request);

            $respone = $respone->getData();

            if( isset($respone->STATUS) && $respone->STATUS == 1 ) {
                return back()->withSuccess('Mail sent');
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return back()->withError('Mail could not be sent!');
    }

    public function verifyLinkFromAdmin($userid)
    {
        $verifyuser = VerifyUser::where('user_id', $userid)->first();
        
        if (isset($verifyuser)) {
            $user = $verifyuser->user;
            $message = '';
            $email = $user->email;
            $user_role = request()->query('smp');

            if (!$user->verified) {

                $verifyuser->user->verified = 1;
                $verifyuser->user->save();

                $message = 'Email is verified successfully';
            } else {
                $message = 'Email is not verified successfully';
            }

            return redirect()->back()->withSuccess($message);
        }

        return redirect()->back()
            ->with('error', 'Something Went Wrong.');
    }
}
