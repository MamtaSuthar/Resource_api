<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Monarobase\CountryList\CountryListFacade;
use Validator;
use Hash;
use Auth;

class ProfileController extends Controller
{
    //
    public function profile(){
         $countries =  CountryListFacade::getList('en');
        return view('customer.account.profile',compact('countries'));
    } 

    public function profileStore(Request $request){
         $validated = $request->validate([
            'full_name'=> ['nullable', 'max:191'],
            // 'email' => ['required','email','unique:users','max:25'],
          'country' => ['nullable'],
            'dob' => ['required','nullable', 'date_format:Y-m-d'],
            'gender' => ['required','nullable', 'in:m,f']
        ]);

   
        // $data = $validated->validated();

        $user = $this->user();

        if( !empty($validated) ) {
            $user->update($validated);
        }

        return redirect()->back()->withSuccess("Profile Updated successfully");
        // return ApiResponse::ok('Profile updated', [
        //     'user' => $user->format()
        // ]);

    }

    public function changePass(){
        return view('customer.account.change-password');
    }
    
    public function changePassword(Request $request)
    {
        // dd();
        # Get Logged In User Instance
        $user = $this->user();

        # Validate Form Inputs
        $request->validate([
            'old_password' => [
                'required',
                'string',
                'min:8',
                function ($attr, $value, $fail) use ($user){
                    if(! Hash::check($value, $user->password)){
                        $fail('old password is wrong');
                    }
                },
            ],
            'new_password' => [
                'required', 
                'string', 
                'min:8',
                'different:old_password',
                'confirmed'
            ] 
        ]);   

        # Update User Password and Logged Out
        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        # Logged out
        Auth::logout();
        return redirect()->route('login')->withSuccess('Password Changed Successfully');
    }
}
