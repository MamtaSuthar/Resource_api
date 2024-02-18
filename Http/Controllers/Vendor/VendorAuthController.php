<?php

namespace App\Http\Controllers\Vendor;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\CustomerVerify;
use App\Models\Content;
use App\Models\VerifyUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;


class VendorAuthController extends Controller
{
    
    public function index()
    {
        if(Auth::check()){
            return redirect('/vendor/dashboard');
        }
        return view('vendoruser.auth.index');
    }

    public function login(Request $request)
    {
        # Validate Form Requests
        $request->validate([
            'email'     => ['required','email'],
            'password'  => ['required']
        ]);

        # Check Vendor Authentication using credentials
        $vendor = User::where('email', $request->email)->first();

        if (! $vendor || ! Hash::check($request->password, $vendor->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        # Check Active Status 
        if (! $vendor->status ) {
            throw ValidationException::withMessages([
                'email' => ['Account Blocked! Please contact ADMIN.'],
            ]);
        }

        # Check Vendor Verified Status 
        if (! $vendor->vendor_verified ) {
            throw ValidationException::withMessages([
                'email' => ['Account Not Verified! Please contact ADMIN.'],
            ]);
        }

        # Authenticate Vendor Login
        Auth::login($vendor, $request->has('remember_me'));
        return redirect()->route('vendor.dashboard')
            ->withSuccess('Welcome to the Vendor Dashboard');
    }

    public function register()
    {
        return view('vendoruser.auth.register');
    }

    public function registered(Request $request)
    {
        $validated = $request->validate([
            'first_name'=> ['required','string','min:3','max:30'],
            'last_name' => ['required','string','min:3','max:30'],
            'email' => ['required','email','unique:users'],
            'password' => ['required','confirmed', Password::min(8)],
            'terms' => ['required']
        ]);

        try{
            # Update Query Data
            $validated['password'] = Hash::make($validated['password']);
            # Create Vendor Account
            $user = User::create(
                collect($validated)->except('terms')->toArray()
            );
            # Assign Role
            $user->syncRoles([User::VENDOR]);

             //  Send mail to admin 
            try{
                $this->sendverifyMail($user);

                \Mail::send('mail/vendor/email', array( 
                    'name' => $user['first_name'], 
                    'email' => $user['email'], 

                ), function($message) use ($user){ 
                    // $message->from('admin@magazine.com'); 
                    $message->to($user->email, 'Vendor')->subject("Vendor Registration"); 
                }); 
            } 
            catch(\Exception $e) {
                logger(' issue: '.$e->getMessage());
            }

            // send admin an email about this new vendor
            \App\Vars\SystemMails::admin_new_vendor($user);
                
            return back()->withSuccess('Account successfully created. Please visit your email to verify your account and wait for Admin\'s approval.');
        }
        catch(\Exception $e){
            logger($e->getMessage());
        }

        return back()->withSuccess('Something went wrong');
    }

    public function sendverifyMail($user) {
        VerifyUser::create([
            'user_id' => $user->id,
            'token'   => sha1(time())
        ]);

        Mail::to($user)->send(new CustomerVerify($user));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('vendor.index');

    }

    public function vendor_terms(){
        $content = Content::where('slug','vendor_terms')->first();
        
        return view('vendoruser.vendorTerms',compact('content'));
    }
}
