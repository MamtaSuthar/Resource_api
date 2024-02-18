<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;

// #OBSOLETE - NOT IN USE
class UserImport implements ToModel, WithValidation, SkipsOnError
{
    use Importable,SkipsErrors;
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $data = date('Y-m-d',strtotime($row[3]));
        $user =  new User([
            'first_name'            => $row[0],
            'email'                 => $row[1],
            'phone'                 => $row[2],
            'dob'                   => $data, 
            'password'              => Hash::make($row[4]),
            'country'               => $row[5],
            'refer_code'            => $this->getReferralCode($row[0]),
        ]);
        
        // $user->info()->create([
        //     'dob'       => now()->parse($data)->format('Y-m-d'),
        //     'country'   => $row[5],
        // ]);
        $user->syncRoles([User::CUSTOMER]);
        return $user;
        
    }

    public function rules(): array
    {
        return [
            '1' => ['required','unique:users,email'],
            '2' => ['required','unique:users,phone'],
        ];
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

    public function getReferralCode($name){
        $subPart = substr($name,0,4).$this->randStr(4);

        if(User::where('refer_code','LIKE','%'.$subPart.'%')->exists()){
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
        return substr(str_shuffle($str),0, $no_of_char);
    }
    
}

