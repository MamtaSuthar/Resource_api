<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CouponUserExport implements FromCollection, WithHeadings, WithMapping
{
    public $collection;
    public $type;

    public function __construct($collection, $type)
    {
        $this->collection = $collection;
        $this->type = $type;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->collection;
    }

    public function headings(): array
    {
        $heading = [
            'ID',
            'Name',
            'Email',
            'Status',
        ];

        return $heading;
    }

    public function map($item): array
    {
        dd($item);
        return [
            $item->user_id,
            $item->user->first_name.' '.$item->user->last_name,
            $item->user->email,
            ($item->user->verified == 1)?'Verified':'Not Verified',
        ];
    }
}
