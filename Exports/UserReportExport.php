<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserReportExport implements FromCollection, WithHeadings, WithMapping
{
    public $users;
    public $type;
    public function __construct($users,$type)
    {
        $this->users = $users;
        $this->type = $type;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->users;
    }
    public function headings(): array
    {
        $heading = [
            'User Id',
            'First Name',
            'Last Name',
            'Email Id',
            'Varified',
            'Phone Number',
            'Country',
            'Date Of Birth',
            'Gender',
            'Status',
            'Joined At',
            'Role',
            ($this->type=='user')?'Refer Code':'',
            ($this->type=='vendor')?'Vendor status':'',
            
        ];
        return $heading;
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            ($user->verified==1)?'yes':'no',
            $user->phone,
            $user->country,
            $user->dob,
            $user->gender,
            ($user->status==1)?"Activated":'De-activated',
            $user->created_at,
            $user->role_name,
            ($this->type=='user')?$user->refer_code:'',
            ($this->type=='vendor')?((is_null($user->vendor_verified))?"Not Verified":(($user->isVendorVerified())?"Approved":"Dis-Approved")):'',
        ];
    }
}
