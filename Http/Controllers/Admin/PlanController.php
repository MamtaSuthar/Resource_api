<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Publication;
use App\Vars\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\SubscriptionApplePrice;


class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $plans = Plan::latest()->get();
        return view('admin.plan.index', compact('plans'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $publications = Publication::active()->get();
    $subscription_apple_price = SubscriptionApplePrice::where('status',1)->get();
        return view('admin.plan.create', compact('publications','subscription_apple_price'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->merge([
            'apple_product_id' => \array_filter($request->get('apple_product_id') ?? []),
            'apple_family_product_id' => \array_filter($request->get('apple_family_product_id') ?? [])
        ]);

        $validated = $request->validate([
            'title' => ['required', 'max:191'],
            'desc' => ['required', 'max:5000'],
            'type' => ['required', 'in:bundle,custom,premium'],
            'pulications' => ['nullable', 'array'],
            'plan_duration_price_GHS' => ['required', 'array'],
            'plan_duration_price_USD' => ['required', 'array'],
            'plan_duration_family_price_GHS' => ['required', 'array'],
            'plan_duration_family_price_USD' => ['required', 'array'],
            'plan_duration_discount' => ['required', 'array'],
            'apple_product_id' => ['nullable', 'array'],
            'apple_product_id.*' => ['unique:plan_durations,apple_product_id'],
            'apple_family_product_id' => ['nullable', 'array'],
            'apple_family_product_id.*' => ['unique:plan_durations,apple_family_product_id'],
        ]);
        DB::beginTransaction();

        try {

            $durations = [];
            $durations_usd = [];

            $duration_prices_GHS    = \array_filter($validated['plan_duration_price_GHS']);
            $duration_prices_USD    = \array_filter($validated['plan_duration_price_USD']);
            $duration_family_prices_GHS    = \array_filter($validated['plan_duration_family_price_GHS']);
            $duration_family_prices_USD    = \array_filter($validated['plan_duration_family_price_USD']);
            $duration_discounts = (array)$validated['plan_duration_discount'];

            $apple_product_ids = \array_filter($validated['apple_product_id'] ?? []);
            $apple_family_product_id = \array_filter($validated['apple_family_product_id'] ?? []);

            foreach( $duration_prices_GHS as $key => $duration_price) {
                if( $d = Helper::get_plan_duration($key) ) {
                    $fp = $duration_family_prices_GHS[$key];

                    if( !empty($fp) ) {
                        $fp = \json_encode(\array_filter($fp));
                    }

                    $durations[] = [
                        'code' => $key,
                        'value' => $d['name'],
                        'price' => $duration_price,
                        'family_price' => $fp ?? null,
                        'discount' => $duration_discounts[$key] ?? 0,
                        'apple_product_id' => $apple_product_ids[$key] ?? null,
                        'apple_family_product_id' => $apple_family_product_id[$key] ?? null,
                    ];
                }
            }

            foreach( $duration_prices_USD as $key => $duration_price) {
                if( $d = Helper::get_plan_duration($key) ) {
                    $fp = $duration_family_prices_USD[$key];

                    if( !empty($fp) ) {
                        $fp = \json_encode(\array_filter($fp));
                    }
                    
                    $durations_usd[] = [
                        'code' => $key,
                        'value' => $d['name'],
                        'price' => $duration_price,
                        'family_price' => $fp ?? null,
                        'currency'=>'USD',
                        'discount' => $duration_discounts[$key] ?? 0,
                        'apple_product_id' => $apple_product_ids[$key] ?? null,
                        'apple_family_product_id' => $apple_family_product_id[$key] ?? null,
                    ];
                }
            }
            
            $plan = Plan::create([
                'title' => $validated['title'],
                'code' => Str::kebab($validated['title']).uniqid(),
                'desc' => $validated['desc'],
                'type' => $validated['type'],
                'duration_json' => \json_encode(\array_merge($durations,$durations_usd)),
                'display_order' => intval($request->get('display_order') ?? 0)
            ]);

            if( !empty($publications = (array)$request->get('pulications')) ) {
                $plan->publications()->sync($publications);
            }

            $durations = \array_map(function($duration) use($plan) {
                $duration['plan_id'] = $plan->id;
                return $duration;
            }, $durations);

            $plan->durations()->createMany($durations);

            $durations_usd = \array_map(function($duration) use($plan) {
                $duration['plan_id'] = $plan->id;
                return $duration;
            }, $durations_usd);

            $plan->durations()->createMany($durations_usd);

            DB::commit();

            return redirect()->route('admin.plans.index')
                ->withSuccess('Plan added successfully.');

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return back()
            ->withError('Something went wrong')
            ->withInput();
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
    public function edit(Request $request, Plan $plan)
    {
        $publications = Publication::active()->get();
        $publicationsSet = $plan->publications()->get()->pluck('id')->toArray();
        $durations = $plan->durations()->get();
        // dd($durations
        $subscription_apple_price = SubscriptionApplePrice::where('status',1)->get();
        return view('admin.plan.edit', compact('plan','publications','publicationsSet','durations','subscription_apple_price'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'title' => ['required', 'max:191'],
            'desc' => ['required', 'max:5000'],
            'type' => ['required', 'in:bundle,custom,premium'],
            'pulications' => ['nullable', 'array'],
            // 'plan_duration_price' => ['required', 'array'],
            'plan_duration_price_GHS' => ['required', 'array'],
            'plan_duration_price_USD' => ['required', 'array'],
            'plan_duration_family_price_GHS' => ['required', 'array'],
            'plan_duration_family_price_USD' => ['required', 'array'],
            'plan_duration_discount' => ['required', 'array'],
            'apple_product_id' => ['nullable', 'array'],
            'apple_family_product_id' => ['nullable', 'array'],
        ]);

        DB::beginTransaction();

        try {

            $durations = [];
            $durations_usd = [];

            $duration_prices_GHS    = \array_filter($validated['plan_duration_price_GHS']);
            $duration_prices_USD    = \array_filter($validated['plan_duration_price_USD']);
            $duration_family_prices_GHS    = \array_filter($validated['plan_duration_family_price_GHS']);
            $duration_family_prices_USD    = \array_filter($validated['plan_duration_family_price_USD']);
            // $duration_prices    = \array_filter($validated['plan_duration_price']);
            $duration_discounts = (array)$validated['plan_duration_discount'];

            $apple_product_ids = \array_filter($validated['apple_product_id'] ?? []);
            $apple_family_product_id = \array_filter($validated['apple_family_product_id'] ?? []);

            //Duration part for GHS Currency
            foreach( $duration_prices_GHS as $key => $duration_price) {
                if( $d = Helper::get_plan_duration($key) ) {
                    $fp = $duration_family_prices_GHS[$key];

                    if( !empty($fp) ) {
                        $fp = \json_encode(\array_filter($fp));
                    }

                    $_duration = [
                        'code' => $key,
                        'value' => $d['name'],
                        'price' => $duration_price,
                        'family_price' => $fp ?? null,
                        'currency'=>'GHS',
                        'discount' => $duration_discounts[$key] ?? 0,
                        'apple_product_id' => $apple_product_ids[$key] ?? null,
                        'apple_family_product_id' => $apple_family_product_id[$key] ?? null,
                    ];

                    $plan->durations()->updateOrCreate(
                        ['code' => $key, 'currency' => 'GHS'],
                        $_duration
                    );

                    $durations_usd[] = $_duration;
                }
            }
            //Duration part for USD Currency
            foreach( $duration_prices_USD as $key => $duration_price) {
                if( $d = Helper::get_plan_duration($key) ) {
                    $fp = $duration_family_prices_USD[$key];

                    if( !empty($fp) ) {
                        $fp = \json_encode(\array_filter($fp));
                    }

                    $_duration = [
                        'code' => $key,
                        'value' => $d['name'],
                        'price' => $duration_price,
                        'family_price' => $fp ?? null,
                        'currency'=>'USD',
                        'discount' => $duration_discounts[$key] ?? 0,
                        'apple_product_id' => $apple_product_ids[$key] ?? null,
                        'apple_family_product_id' => $apple_family_product_id[$key] ?? null,
                    ];

                    $plan->durations()->updateOrCreate(
                        ['code' => $key, 'currency' => 'USD'],
                        $_duration
                    );

                    $durations_usd[] = $_duration;
                }
            }
                
            $plan->title = $validated['title'];
            // $plan->code = Str::kebab($validated['title']).uniqid();
            $plan->desc = $validated['desc'];
            $plan->type = $validated['type'];
            $plan->duration_json = \json_encode(\array_merge($durations,$durations_usd));
            $plan->display_order = intval($request->get('display_order') ?? $plan->display_order);
            $plan->save();

            if( isset($validated['pulications']) && !empty($validated['pulications']) ) {
                $plan->publications()->sync($validated['pulications']);
            }

            DB::commit();

            return redirect()->route('admin.plans.index')
                ->withSuccess('Plan Updated successfully.');

        } catch(\Exception $e) {
            DB::rollBack();

            if( $e->getCode() == '23000' ) {
                return back()->withError('Make sure apple ids are unique')->withInput();
            }

            logger($e->getMessage());
        }

        return back()
            ->withError('Something went wrong')
            ->withInput();
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

    public function changestatus(Plan $plan)
    {
        # Change the status of the category
        $plan->status = $plan->status ? 0 : 1;
        $plan->save();
        $message = $plan->status 
            ? 'Plan Activated Successfully' 
            : 'Plan Deactivated Successfully';
        return back()->withSuccess($message);
    }
}
