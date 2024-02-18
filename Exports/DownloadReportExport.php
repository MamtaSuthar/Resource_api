<?php

namespace App\Exports;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class DownloadReportExport implements FromCollection, WithHeadings, WithMapping
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
        $hArr = [];
        if($this->type=='main'){
            $hArr = ['User Name','User Email','No. of Magazines download','No. of Newspaper Download'];
        }else{
            $hArr = ['Title','Category','Publisher','Copyright Owner','Price','Published Date'];
        }
        $heading = $hArr;
        return $heading;
    }

    public function map($user): array
    {
        $arr = [];
        if($this->type=='main'){
            $arr = [
                $user->name,
                $user->email,
                ($user->magazine_downloads()->count()!=0)?$user->magazine_downloads()->count():'0',
                ($user->newspaper_downloads()->count()!=0)?$user->newspaper_downloads()->count():'0',
            ];
            return $arr;
        }else{
            $arr = [
                $user->title,
                $user->category->name,
                $user->publication->name,
                $user->copyright_owner,
                $user->price,
                date('Y-m-d',strtotime($user->published_date))
            ];
            return $arr;
        }
    }
}
