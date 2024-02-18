<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MagazineNewspaperExport implements FromCollection, WithHeadings, WithMapping
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
            'Price',
            'Uploaded By',
            'Is Free',
            'Category',
            'Publication',
            'Copyright Owner',
            'Edition Number',
            'File Type',
            'Publication Date',
            'Created At'  
        ];

        return $heading;
    }

    public function map($item): array
    {
        $price = $this->type == 'newspaper'
            ? $item->publication->newspaper_price_ghs
            : $item->price;

        return [
            $item->id,
            $item->title,
            $price,
            $item->vendor->email,
            ($item->is_free)?'Yes':'No',
            $item->category->name,
            $item->publication->name,
            $item->copyright_owner,
            $item->edition_number,
            $item->file_type,
            $item->published_date->format('Y/m/d'),
            $item->created_at->format('Y/m/d'),
        ];
    }
}
