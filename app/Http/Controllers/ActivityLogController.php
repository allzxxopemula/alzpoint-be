<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->business_id, 403);

        $logs = ActivityLog::with('user:user_id,full_name,username')
            ->where('business_id', $request->user()->business_id)
            ->latest('created_at')
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $logs]);
    }
}