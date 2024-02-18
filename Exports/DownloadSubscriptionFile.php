<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DownloadSubscriptionFile implements FromCollection, WithHeadings, WithMapping
{

    public $data;
    public $type;
    public function __construct($data,$type)
    {
        $this->data = $data;
        $this->type = $type;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        $hArr = [];
        if($this->type=='main'){
            $hArr = ['Title','Type','Status','No.Of Publictions','No.Of Subscriptions'];
        }else{
            $hArr = ['User Name','User Email','User phone','Plan Price','Start Date','Expire Date'];
        }
        $heading = $hArr;
        return $heading;
    }

    public function map($data): array
    {
        $arr = [];
        if($this->type=='main'){
            $arr = [
                $data->title,
                $data->type,
                ($data->status != 0)?"Active":"De-active",
                $data->publications()->count(),
                $data->getUserSubscriptions()->where('pay_status',1)->count(),
            ];
            return $arr;
        }else{
            $arr = [
                $data->user->name,
                $data->user->email,
                $data->user->phone,
                $data->purchased_at,
                $data->subscribed_at,
                $data->expires_at,
            ];
            return $arr;
        }
    }

}
