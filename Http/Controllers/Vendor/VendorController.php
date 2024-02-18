<?php

namespace App\Http\Controllers\Vendor;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class VendorController extends Controller
{
    public function index()
    {
        $total = (object) [
            'magaznies' => $this->user()->magazines->count(),
            'newspapers'=>$this->user()->newspapers->count()
        ];
        // dd($total);
        return view('vendoruser.dashboard.index', compact('total'));
    }

    public function settings()
    {
        return view('vendoruser.settings');
    }

    public function changePassword(Request $request)
    {
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
        return redirect()->route('vendor.index')->withSuccess('Password Changed Successfully');
    }

    public function save_epub_blob(Request $request)
    {
        if( $fileBlob = $request->file('epub_file') ) {
            try {

                $user_id = auth()->id();

                $file = $user_id . md5(uniqid().time()) . '.epub';
                $path = storage_path('epub_temp');
    
                if( ! is_dir($path) ) {
                    mkdir($path, '1775', false);
                }
    
                $path .= '/'.$file;

                file_put_contents($path, $fileBlob->get());

                if( file_exists($path) ) {
                    return $file;
                }
            } catch(\Exception $e) {
                logger($e->getMessage());
            }
        }
    
        return false;
    }
}
