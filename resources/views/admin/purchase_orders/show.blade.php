@extends('layouts.app')

@section('title', 'Purchase Order #' . $purchaseOrder->po_number . ' - Bakery Management System')

@section('content')
<script>
    window.location.replace("{{ route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id]) }}");
</script>
<noscript>
    <meta http-equiv="refresh" content="0;url={{ route('admin.purchase-orders.index', ['open_po' => $purchaseOrder->id]) }}">
</noscript>
<div style="display: flex; justify-content: center; align-items: center; min-height: 400px; color: var(--text-muted, #71717a); font-size: 0.95rem;">
    Redirecting to Purchase Orders...
</div>
@endsection
