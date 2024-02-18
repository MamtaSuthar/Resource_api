<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionApplePrice;
use Illuminate\Http\Request;

class ApplePlanController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
        $plans = SubscriptionApplePrice::latest()->get();
        return view('admin.appleplan.index', compact('plans'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.appleplan.create');
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
            'price' => ['required', 'string','unique:subscription_apple_price,price,{$request->price},price'],
            'status' => ['nullable','in:0,1'],
        ]);
        $validated['status'] = $validated['status'] ?? 0;

        $blog = SubscriptionApplePrice::create($validated);

        return redirect()->route('admin.appleplan.index')
            ->with('Apple Plan Added');
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $plansbyid = SubscriptionApplePrice::firstOrCreate(
            ['id' => $id],
        );

        return view('admin.appleplan.edit', compact('plansbyid'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $planUpdate = SubscriptionApplePrice::findOrFail($id);
        $validated = $request->validate([
            'price' => ['required', 'string'],
            'status' => ['nullable','in:0,1'],
        ]);
        $validated['status'] = $validated['status'] ?? 0;
        $planUpdate->update($validated);

        return back()->withSuccess('Apple Price updated');
    }
}
