<?php

namespace App\Imports;

use App\Models\User;
use App\Vars\HSP;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Monarobase\CountryList\CountryList;

class UserImportByCollection implements ToCollection, WithValidation, WithStartRow, SkipsOnError
{
    use Importable, SkipsErrors;
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {

        $request = request();

        try {

            // get all plans and plansdurations
            $plans = $this->get_plans($collection);

            foreach ($collection as $row) {
                $data = date('Y-m-d', strtotime($row[3]));

                $user = User::firstWhere('email', $row[1]);

                if( $user ) {
                    continue;
                }

                $country_code = app(CountryList::class)->has($row[5] ?? 'GH');
                $country_code = $country_code ? $row[5] : 'GH';

                $user = User::create([
                    'first_name'            => $row[0],
                    'email'                 => $row[1],
                    'email_verified_at'     => date('Y-m-d'),
                    'verified'              => 1,
                    'phone'                 => $row[2],
                    'dob'                   => $data,
                    'password'              => Hash::make($row[4]),
                    'country'               => $country_code,
                    'refer_code'            => $this->getReferralCode($row[0]),
                ]);
                
                $user->info()->create([
                    'dob'       => now()->parse($data)->format('Y-m-d'),
                    'country'   => $country_code,
                ]);

                $user->syncRoles([User::CUSTOMER]);

                // subscribe to a plan
                if (isset($row[6]) && isset($row[7])) {

                    if( $plan = $plans->firstWhere('id', intval($row[6])) ) {
                        $plan_duration = $plan->plan_duration;

                        $sub = HSP::subscribe_user_to_a_plan($user, $plan, $plan_duration);

                        if( $sub ) {
                            // send mail to customer
                            // \App\Vars\SystemMails::customer_new_registration(
                            //     $user,
                            //     $plan->title
                            // );
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
        }
    }

    private function get_plans(Collection $collection)
    {
        $collection =  $collection->unique(1); // unique email
        $collection =  $collection->unique(2); // unique phone

        $plans = null;

        $_plans = [];
        $_plan_durations = [];

        $temp = [];

        foreach ($collection as $row) {
            if (isset($row[6]) && isset($row[7])) {
                $_plans[] = $row[6];
                $_plan_durations[] = $row[7];

                $temp[$row[6]] = [
                    $row[7],
                    ($row[5] == 'GH' || $row[5] == 'GHA') ? 'GHS' : 'USD'
                ];
            }
        }

        if (!empty($_plans) && !empty($_plan_durations)) {

            $plans = \App\Models\Plan::query()
                ->with(['durations'])
                ->findMany($_plans)
                ->map(function ($plan) use ($temp) {
                    $t = $temp[$plan->id] ?? null;

                    if ($t) {
                        list($code, $currency) = $t;
                        $duration = $plan->durations;
                        $duration = $duration->where('code', $code)
                            ->where('currency', $currency)
                            ->first();

                        if ($duration) {
                            $plan->setRelation("plan_duration", $duration);
                        }
                    }

                    return $plan;
                })
                ->filter(function ($plan) {
                    return !empty($plan->plan_duration);
                });
        }

        return $plans;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function rules(): array
    {
        // return [
        //     '1' => ['required','unique:users,email'],
        //     '2' => ['required','unique:users,phone'],
        // ];
        return [];
    }
    public function customValidationAttributes()
    {
        return [
            '1' => 'email',
            '2' => 'phone',
        ];
    }
    public function onError(\Throwable $e)
    {
        dd($e->getMessage());
        // Handle the exception how you'd like.
    }
    public function customValidationMessages()
    {
        return [
            '1.unique' => 'Duplicate email used in file',
            '2.unique' => 'Duplicate phone used in file',
        ];
    }

    public function getReferralCode($name)
    {
        $subPart = substr($name, 0, 4) . $this->randStr(4);

        if (User::where('refer_code', 'LIKE', '%' . $subPart . '%')->exists()) {
            $subPart =  $this->getReferralCode($name);
        }
        return strtoupper($subPart);
    }

    // This function will return a random
    // string of specified length
    public function randStr($no_of_char)
    {

        // String of all alphanumeric character
        $str = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        // Shuffle the $str_result and returns substring
        // of specified length
        return substr(str_shuffle($str), 0, $no_of_char);
    }
}
