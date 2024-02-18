<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VendorSalesExport implements FromCollection, WithHeadings, WithMapping
{
    public $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
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
        return [
            'ID',
            'Type',
            'Title',
            'Sold for',
            'Unit Sold',
            'Category',
            'Publication'
        ];
    }

    public function map($item): array
    {
        return [
            $item->id,
            $item->type,
            $item->title,
            $item->price,
            $item->users_who_bought_count,
            $item->category->name,
            $item->publication->name
        ];
    }
}
