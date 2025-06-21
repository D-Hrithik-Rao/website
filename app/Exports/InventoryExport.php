<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Product::select('id', 'name', 'code', 'quantity', 'buying_price', 'selling_price', 'category_id', 'unit_id')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Code',
            'Quantity',
            'Buying Price',
            'Selling Price',
            'Category ID',
            'Unit ID',
        ];
    }
} 