<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InventoryImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Product([
            'name' => $row['name'],
            'code' => $row['code'],
            'quantity' => $row['quantity'],
            'buying_price' => $row['buying_price'],
            'selling_price' => $row['selling_price'],
            'category_id' => $row['category_id'],
            'unit_id' => $row['unit_id'],
        ]);
    }
} 