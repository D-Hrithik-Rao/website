<?php

namespace App\Http\Controllers\Purchase;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetails;
use App\Models\Supplier;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with(['supplier', 'createdBy', 'updatedBy', 'user'])
            ->latest()
            ->get();

        return view('purchases.index', [
            'purchases' => $purchases,
        ]);
    }

    public function approvedPurchases()
    {
        $purchases = Purchase::with(['supplier', 'details.product', 'createdBy', 'updatedBy', 'user'])
            ->where('status', PurchaseStatus::APPROVED)
            ->get();

        return view('purchases.approved-purchases', [
            'purchases' => $purchases,
        ]);
    }

    public function show(Purchase $purchase)
    {
        $purchase->loadMissing([
            'supplier', 
            'details.product', 
            'createdBy', 
            'updatedBy'
        ]);

        $products = $purchase->details()->with('product')->get();

        return view('purchases.details-purchase', [
            'purchase' => $purchase,
            'products' => $products
        ]);
    }

    public function edit(Purchase $purchase)
    {
        $purchase->load(['supplier', 'details', 'createdBy', 'updatedBy']);

        return view('purchases.edit', [
            'purchase' => $purchase,
        ]);
    }

    public function create()
    {
        return view('purchases.create', [
            'categories' => Category::select(['id', 'name'])->get(),
            'suppliers' => Supplier::select(['id', 'name'])->get(),
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        $purchase = Purchase::create([
            'supplier_id' => $request->supplier_id,
            'purchase_date' => $request->purchase_date ? Carbon::parse($request->purchase_date) : Carbon::now(),
            'purchase_no' => $request->purchase_no,
            'status' => $request->status ?? PurchaseStatus::PENDING,
            'total_amount' => $request->total_amount,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id()
        ]);

        // Ensure the date is properly set
        if (!$purchase->purchase_date) {
            $purchase->purchase_date = Carbon::now();
            $purchase->save();
        }

        /*
         * TODO: Must validate that
         */
        if (!empty($request->invoiceProducts)) {
            foreach ($request->invoiceProducts as $product) {
                $purchase->details()->create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'unitcost' => (float) $product['unitcost'],
                    'total' => (float) $product['total'],
                    'created_at' => Carbon::now()
                ]);
            }
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Purchase has been created!');
    }

    public function update(Purchase $purchase, Request $request)
    {
        // Load all necessary relationships before updating
        $purchase->load(['supplier', 'details.product', 'createdBy', 'updatedBy']);

        // Get all product IDs and quantities in one query
        $purchaseDetails = PurchaseDetails::with('product')
            ->where('purchase_id', $purchase->id)
            ->select('product_id', 'quantity')
            ->get();

        // Build an array of product updates
        $productUpdates = $purchaseDetails->map(function ($detail) {
            return [
                'id' => $detail->product_id,
                'quantity' => $detail->quantity
            ];
        })->toArray();

        // Update all products in a single query using DB::transaction
        DB::transaction(function () use ($productUpdates) {
            foreach ($productUpdates as $update) {
                Product::where('id', $update['id'])
                    ->increment('quantity', $update['quantity']);
            }
        });

        // Update the purchase status
        $purchase->update([
            'status' => PurchaseStatus::APPROVED,
            'updated_by' => auth()->user()->id,
            'purchase_date' => $purchase->purchase_date ?? Carbon::now()
        ]);

        // Ensure the date is properly set
        if (!$purchase->purchase_date) {
            $purchase->purchase_date = Carbon::now();
            $purchase->save();
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Purchase has been approved!');
    }

    public function destroy(Purchase $purchase)
    {
        $purchase->delete();

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Purchase has been deleted!');
    }

    public function dailyPurchaseReport()
    {
        $purchases = Purchase::with(['supplier', 'createdBy', 'updatedBy', 'user'])
            ->where('date', today()->format('Y-m-d'))
            ->get();

        return view('purchases.daily-report', [
            'purchases' => $purchases,
        ]);
    }

    public function getPurchaseReport()
    {
        return view('purchases.report-purchase');
    }

    public function exportPurchaseReport(Request $request)
    {
        $rules = [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
        ];

        $validatedData = $request->validate($rules);

        $sDate = $validatedData['start_date'];
        $eDate = $validatedData['end_date'];

        // Add supplier name to the query
        $purchases = DB::table('purchase_details')
            ->join('products', 'purchase_details.product_id', '=', 'products.id')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.id')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->join('users', 'users.id', '=', 'purchases.created_by')
            ->whereBetween('purchases.purchase_date', [$sDate, $eDate])
            ->where('purchases.status', PurchaseStatus::APPROVED)
            ->select(
                'purchases.purchase_no',
                'purchases.purchase_date as date',
                'suppliers.name as supplier_name',
                'products.code',
                'products.name as product_name',
                'purchase_details.quantity',
                'purchase_details.unitcost',
                'purchase_details.total',
                'users.name as created_by'
            )
            ->get();

        $purchase_array[] = [
            'Date',
            'No Purchase',
            'Supplier',
            'Product Code',
            'Product',
            'Quantity',
            'Unitcost',
            'Total',
            'Created By'
        ];

        foreach ($purchases as $purchase) {
            $purchase_array[] = [
                'Date' => $purchase->date,
                'No Purchase' => $purchase->purchase_no,
                'Supplier' => $purchase->supplier_name,
                'Product Code' => $purchase->code,
                'Product' => $purchase->product_name,
                'Quantity' => $purchase->quantity,
                'Unitcost' => $purchase->unitcost,
                'Total' => $purchase->total,
                'Created By' => $purchase->created_by
            ];
        }

        return $this->exportExcel($purchase_array);
    }

    public function exportExcel($products)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '4000M');

        try {
            $spreadSheet = new Spreadsheet();
            $spreadSheet->getActiveSheet()->getDefaultColumnDimension()->setWidth(20);
            $spreadSheet->getActiveSheet()->fromArray($products);
            $Excel_writer = new Xls($spreadSheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="purchase-report.xls"');
            header('Cache-Control: max-age=0');
            ob_end_clean();
            $Excel_writer->save('php://output');
            exit();
        } catch (Exception $e) {
            return $e;
        }
    }
}
