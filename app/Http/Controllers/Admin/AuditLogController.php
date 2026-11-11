<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::with('admin')->latest()->paginate(30);

        return view('admin.audit-logs.index', compact('logs'));
    }
}
