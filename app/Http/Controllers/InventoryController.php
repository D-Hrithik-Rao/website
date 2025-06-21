<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\InventoryImport;
use App\Exports\InventoryExport;

class InventoryController extends Controller
{
    public function export()
    {
        return Excel::download(new InventoryExport, 'inventory.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        Excel::import(new InventoryImport, $request->file('file'));
        return back()->with('success', 'Inventory imported successfully.');
    }
} 