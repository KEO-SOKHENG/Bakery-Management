<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected ReportService $reportService;
    protected ReportExportService $exportService;

    public const ALLOWED_TYPES = [
        'overview',
        'sales',
        'revenue',
        'orders',
        'products',
        'inventory',
        'low-stock',
        'production',
        'customers',
        'purchases',
        'profit-loss',
    ];

    public function __construct(ReportService $reportService, ?ReportExportService $exportService = null)
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService ?? app(ReportExportService::class);
    }

    /**
     * Display the dynamic Report Management Dashboard.
     */
    public function index(Request $request)
    {
        $type = $request->get('type', 'overview');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'overview';
        }

        $dateInfo = $this->reportService->parseDateRange($request);
        $period    = $dateInfo['period'];
        $startDate = $dateInfo['startDate'];
        $endDate   = $dateInfo['endDate'];

        $reportData = match ($type) {
            'overview'    => [
                'sales'       => $this->reportService->getSalesReport($startDate, $endDate, $request, false),
                'revenue'     => $this->reportService->getRevenueReport($startDate, $endDate),
                'orders'      => $this->reportService->getOrderReport($startDate, $endDate, $request, false),
                'profitLoss'  => $this->reportService->getProfitLossReport($startDate, $endDate),
                'lowStock'    => $this->reportService->getLowStockReport($request, false),
            ],
            'sales'       => $this->reportService->getSalesReport($startDate, $endDate, $request, true),
            'revenue'     => $this->reportService->getRevenueReport($startDate, $endDate),
            'orders'      => $this->reportService->getOrderReport($startDate, $endDate, $request, true),
            'products'    => $this->reportService->getProductReport($startDate, $endDate, $request, true),
            'inventory'   => $this->reportService->getInventoryReport($request, true),
            'low-stock'   => $this->reportService->getLowStockReport($request, true),
            'production'  => $this->reportService->getProductionReport($startDate, $endDate, $request, true),
            'customers'   => $this->reportService->getCustomerReport($startDate, $endDate, $request, true),
            'purchases'   => $this->reportService->getSupplierPurchaseReport($startDate, $endDate, $request, true),
            'profit-loss' => $this->reportService->getProfitLossReport($startDate, $endDate),
        };

        $currency = \App\Services\SettingsService::getCurrencySymbol();
        $bakeryName = Setting::get('bakery_name', Setting::get('shop_name', 'Sweet Delights Bakery'));

        return view('admin.reports', compact(
            'type',
            'period',
            'startDate',
            'endDate',
            'reportData',
            'currency',
            'bakeryName'
        ));
    }

    /**
     * Export the active report as a styled PDF document.
     */
    public function exportPdf(Request $request)
    {
        $type = $request->get('type', 'sales');
        if ($type === 'overview' || !in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'sales';
        }

        $dateInfo = $this->reportService->parseDateRange($request);
        $period    = $dateInfo['period'];
        $startDate = $dateInfo['startDate'];
        $endDate   = $dateInfo['endDate'];

        // Get unpaginated data for full export
        $reportData = match ($type) {
            'sales'       => $this->reportService->getSalesReport($startDate, $endDate, $request, false),
            'revenue'     => $this->reportService->getRevenueReport($startDate, $endDate),
            'orders'      => $this->reportService->getOrderReport($startDate, $endDate, $request, false),
            'products'    => $this->reportService->getProductReport($startDate, $endDate, $request, false),
            'inventory'   => $this->reportService->getInventoryReport($request, false),
            'low-stock'   => $this->reportService->getLowStockReport($request, false),
            'production'  => $this->reportService->getProductionReport($startDate, $endDate, $request, false),
            'customers'   => $this->reportService->getCustomerReport($startDate, $endDate, $request, false),
            'purchases'   => $this->reportService->getSupplierPurchaseReport($startDate, $endDate, $request, false),
            'profit-loss' => $this->reportService->getProfitLossReport($startDate, $endDate),
        };

        $currency = \App\Services\SettingsService::getCurrencySymbol();
        $bakeryName = Setting::get('bakery_name', Setting::get('shop_name', 'Sweet Delights Bakery'));
        $bakeryAddress = Setting::get('bakery_address', Setting::get('shop_address', ''));
        $bakeryPhone = Setting::get('bakery_phone', Setting::get('shop_phone', ''));

        try {
            $pdf = Pdf::loadView('admin.reports.pdf', compact(
                'type',
                'period',
                'startDate',
                'endDate',
                'reportData',
                'currency',
                'bakeryName',
                'bakeryAddress',
                'bakeryPhone'
            ))->setPaper('a4', 'landscape');

            $startStr = $startDate ? $startDate->format('Ymd') : 'all';
            $endStr = $endDate ? $endDate->format('Ymd') : 'all';
            $filename = "bakery-{$type}-report-{$startStr}-{$endStr}.pdf";

            if ($request->has('stream') || $request->has('preview')) {
                return $pdf->stream($filename);
            }

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            return response()->view('admin.reports.pdf', compact(
                'type',
                'period',
                'startDate',
                'endDate',
                'reportData',
                'currency',
                'bakeryName',
                'bakeryAddress',
                'bakeryPhone'
            ));
        }
    }

    /**
     * Export the active report as a structured Excel/CSV file with UTF-8 BOM.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $type = $request->get('type', 'sales');
        if ($type === 'overview' || !in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'sales';
        }

        $dateInfo = $this->reportService->parseDateRange($request);
        $startDate = $dateInfo['startDate'];
        $endDate   = $dateInfo['endDate'];

        // Get unpaginated data for full export
        $reportData = match ($type) {
            'sales'       => $this->reportService->getSalesReport($startDate, $endDate, $request, false),
            'revenue'     => $this->reportService->getRevenueReport($startDate, $endDate),
            'orders'      => $this->reportService->getOrderReport($startDate, $endDate, $request, false),
            'products'    => $this->reportService->getProductReport($startDate, $endDate, $request, false),
            'inventory'   => $this->reportService->getInventoryReport($request, false),
            'low-stock'   => $this->reportService->getLowStockReport($request, false),
            'production'  => $this->reportService->getProductionReport($startDate, $endDate, $request, false),
            'customers'   => $this->reportService->getCustomerReport($startDate, $endDate, $request, false),
            'purchases'   => $this->reportService->getSupplierPurchaseReport($startDate, $endDate, $request, false),
            'profit-loss' => $this->reportService->getProfitLossReport($startDate, $endDate),
        };

        $startStr = $startDate ? $startDate->format('Ymd') : 'all';
        $endStr = $endDate ? $endDate->format('Ymd') : 'all';
        $filename = "bakery-{$type}-report-{$startStr}-{$endStr}.csv";
        return $this->exportService->exportCsv($type, $reportData, $filename);
    }
}
