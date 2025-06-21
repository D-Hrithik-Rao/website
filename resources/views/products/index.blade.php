@extends('layouts.tabler')

@section('content')
<div class="page-body">
    @if($products->isEmpty())
        <x-empty
            title="No products found"
            message="Try adjusting your search or filter to find what you're looking for."
            button_label="{{ __('Add your first Product') }}"
            button_route="{{ route('products.create') }}"
        />
    @else
        <div class="container container-xl">
            <x-alert/>

            <div class="mb-3 d-flex justify-content-end">
                <form action="{{ route('inventory.import') }}" method="POST" enctype="multipart/form-data" class="d-inline-block me-2">
                    @csrf
                    <input type="file" name="file" required class="form-control d-inline-block w-auto" style="display:inline-block; width:auto;">
                    <button type="submit" class="btn btn-primary">Import Inventory</button>
                </form>
                <a href="{{ route('inventory.export') }}" class="btn btn-success">Export Inventory</a>
            </div>

            @livewire('tables.product-table')
        </div>
    @endif
</div>
@endsection
