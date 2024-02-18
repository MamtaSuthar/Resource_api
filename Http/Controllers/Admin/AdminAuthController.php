<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class AdminAuthController extends Controller
{
    
    public function index()
    {
        if(Auth::check()){
            return redirect('/admin/dashboard');
        }
        return view('admin.auth.index');
    }

    public function login(Request $request)
    {
        # Validate Form Requests
        $request->validate([
            'email'     => ['required','email'],
            'password'  => ['required']
        ]);

        # Check User Authentication using credentials
        //$user = User::where('email', $request->email)->first();

        // if (! $user || ! Hash::check($request->password, $user->password)) {
        //     throw ValidationException::withMessages([
        //         'email' => ['The provided credentials are incorrect.'],
        //     ]);
        // }
        $credentials = ['email' => $request->email, 'password' => $request->password];

        if (! Auth::attempt($credentials, $request->has('remember_me'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        # Authenticate User Login
        // Auth::login($user);
        return redirect()->route('admin.dashboard')
            ->withSuccess('Welcome to the Admin Dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('admin.index');

    }
}
