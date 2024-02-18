<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\UserInfo;
use Illuminate\Http\Request;
use Monarobase\CountryList\CountryListFacade;
use Validator;
use Hash;
use Auth;
class WebProfileController extends Controller
{
    //

     public function profile(){
        $prefs = Category::active()->latest()->get();
        if( $userInfo = UserInfo::where('user_id',$this->user()->id)->first() ) {
            $selected_pref = \json_decode($userInfo->favourite_topics, true);
        } else {
            $selected_pref = [];
        }
        $selected_pref = isset($selected_pref) ? $selected_pref : [];
        $countries =  CountryListFacade::getList('en');
        return view('customer.account.profile',compact('countries','prefs','selected_pref'));
    } 

    public function getTags($id){
        # Get Magazines
        $tags = Category::active()->latest()->get();
        // $tags = TagResource::collection($tags);
        $ft = UserInfo::where('user_id',$id)->first()->favourite_topics ?? [];
        $topics = !empty($ft)?json_decode($ft):[];
        // dd($topics);
        $tags = $tags->map(function($item) use ($topics){
            $item['selected'] = (in_array($item['id'],$topics))?true:((empty($topics))?true:false);
            unset($item['created_at'],$item['updated_at']);
            return $item;
        });

        // dd($tags);
        return $tags;
    }

    public function profileStore(Request $request){
         $validated = $request->validate([
            'full_name'=> ['nullable', 'max:191'],
            // 'email' => ['required','email','unique:users','max:25'],
          'country' => ['nullable'],
            'dob' => ['required','nullable', 'date_format:Y-m-d'],
            'phone' => ['nullable', 'digits_between:8,12'],
            'gender' => ['required','nullable', 'in:m,f,o']
        ]);

   
        // $data = $validated->validated();

        $user = $this->user();

        if( $user->phone ) {
            unset($validated['phone']);
        }

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

    public function savePreferences(Request $request)
    {
        // dd();
        # Get Logged In User Instance
        $user = $this->user();
        $prefs = $request->except('_token');
        if(isset($prefs['pref'])){
            UserInfo::where('user_id',$user->id)->update(['favourite_topics'=>json_encode($prefs['pref'])]);
            return redirect()->back()->withSuccess("Profile Updated successfully");
        }else{
            return redirect()->back()->with('error',"Please select atleast one Category for Preference");
        }
        // dd($prefs);
        
        // dd($request->all());

        # Update User Password and Logged Out
        
        
    }
}
