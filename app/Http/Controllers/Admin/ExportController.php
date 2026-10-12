<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Admin\ExcelExport;
use App\Services\Admin\Queries;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function download(Request $r, string $kind, ExcelExport $excel, Queries $queries)
    {
        abort_unless(in_array($kind, ['all', 'students', 'scores', 'intentions'], true), 404);
        $filters = $queries->filters($r);
        unset($filters['page'],$filters['per_page']);
        $path = tempnam(storage_path('app/private'), 'export_');
        try {
            $counts = $excel->write($path, $kind, $filters);
            AuditLog::create(['admin_id' => $r->user()->id, 'action' => 'data_export', 'ip_address' => $r->ip(), 'details' => ['kind' => $kind, 'filters' => $filters, 'counts' => $counts], 'created_at' => now()]);
        } catch (\Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }throw $e;
        }

        return response()->download($path, 'growth-'.$kind.'-'.now('Asia/Shanghai')->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }
}
