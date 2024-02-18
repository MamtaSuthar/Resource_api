<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CouponExport implements FromCollection, WithHeadings, WithMapping
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
            'Title',
            'Code',
            'Type',
            'Discount',
            'Used Times',
            'Valid For',
            'User Id',  
        ];

        return $heading;
    }

    public function map($item): array
    {

        return [
            $item->id,
            $item->title,
            $item->code,
            $item->type,
            $item->discount,
            $item->used_times,
            $item->valid_for,
            $item->user_id,
        ];
    }
}
