<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BiMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BiController extends Controller
{
    public function index(Request $request, BiMetricsService $metrics): View
    {
        $refresh = $request->boolean('refresh');

        if ($refresh) {
            \Illuminate\Support\Facades\Cache::forget('bi.dashboard.v1');
        }

        return view('admin.bi', [
            'metrics' => $metrics->dashboard(),
        ]);
    }
}
