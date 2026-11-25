<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $attendance = Attendee::query()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(case when received then 1 end) as received')
            ->first();

        $total = (int) $attendance->total;
        $received = (int) $attendance->received;
        $pending = $total - $received;
        $percentage = $total === 0 ? 0 : (int) round(($received / $total) * 100);
        $staff = $request->user()->isAdmin()
            ? User::query()
                ->select(['id', 'name', 'email'])
                ->where('role', 'staff')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
            : collect();

        return view('dashboard', compact('total', 'received', 'pending', 'percentage', 'staff'));
    }
}
