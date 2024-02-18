<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class Affiliations extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->whereDoesntHave('roles', function($q) {
                $q->whereIn('name', ['admin', 'superadmin', 'vendor']);
            });

        if( $role = $request->query('role') ) {
            if( in_array($role, ['user', 'company']) ) {

                $users->whereHas('roles', function($q) use($role) {
                    $q->where('name', $role);
                });
            }
        }

        $users = $users
            ->with(['roles', 'referred_to'])
            ->has('referred_to')
            ->withCount('referred_to')
            ->orderBy('referred_to_count', 'desc')
            ->get();

        return view('admin.affiliation.index', compact('users'));
    }
}
