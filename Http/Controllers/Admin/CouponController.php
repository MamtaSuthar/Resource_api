<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CouponCode;
use App\Models\User;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $coupons = CouponCode::latest()
            ->with(['codes_used_by_users.user'])
            ->get();

        return view('admin.coupons.index', compact('coupons'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $users = User::all()->pluck('id','first_name');
        return view('admin.coupons.create',compact('users'));
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
            'title' => [
                'required','min:3','string',
                "unique:coupon_codes"
            ],
            'code' => ['required','min:8','max:8','unique:coupon_codes,code'],
            'type' => ['required'],
            'discount' => ['required', 'numeric'],
            'used_times' => ['required', 'integer'],
            'valid_for' => ['required', 'integer'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);
        $validated['code'] = strtoupper($validated['code']);
        # Create Coupon Data
        CouponCode::create($validated);

        return redirect()->route('admin.coupon.index')->withSuccess('Coupon Created Successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, CouponCode $coupon)
    {
        $users = User::all()->pluck('id','first_name');
        return view('admin.coupons.edit', compact('coupon','users'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CouponCode $coupon)
    {
        $validated = $request->validate([
            'title' => [
                'required','min:3'
            ],
            // 'code' => ['nullable','min:8','max:8',"unique:coupon_codes,code,{$coupon->id},id"],
            'type' => ['required'],
            'discount' => ['required', 'numeric'],
            'used_times' => ['required', 'integer'],
            'valid_for' => ['required', 'integer'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);
        // $validated['code'] = strtoupper($validated['code']);

        $coupon->update($validated);
        return redirect()->route('admin.coupon.index')->withSuccess('Coupon Updated Successfully');
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
}
