<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = Carbon::parse($data['from'] ?? today()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? today()->toDateString())->startOfDay();

        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            return back()->withInput()->withErrors(['to' => 'Tarih aralığı geçersiz veya 366 günden uzun.']);
        }

        $hotelIds = $request->user()->hotels()->pluck('id');
        $toExclusive = $to->copy()->addDay()->toDateString();
        $base = Reservation::query()
            ->whereIn('hotel_id', $hotelIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereDate('check_in', '<', $toExclusive)
            ->whereDate('check_out', '>', $from->toDateString());

        $reservationCount = (clone $base)->count();
        $bookingValue = (float) ((clone $base)->sum('total_price') ?? 0);
        $paidValue = (float) ((clone $base)->where('payment_status', 'paid')
            ->selectRaw("COALESCE(SUM(GREATEST(total_price - COALESCE(early_departure_refund_amount, 0) - COALESCE((SELECT SUM(hfe.amount) FROM hotel_financial_entries hfe WHERE hfe.reservation_id = reservations.id AND hfe.type = 'income' AND hfe.status = 'pending' AND hfe.category LIKE 'Rezervasyon uzatma #%'), 0), 0)), 0) AS amount")
            ->value('amount') ?? 0);
        $bookedNights = (int) ((clone $base)
            ->selectRaw(DB::connection()->getDriverName() === 'sqlite' ? 'COALESCE(SUM(MAX(0, CAST(julianday(MIN(check_out, ?)) - julianday(MAX(check_in, ?)) AS INTEGER))), 0) AS nights' : 'COALESCE(SUM(GREATEST(DATEDIFF(LEAST(check_out, ?), GREATEST(check_in, ?)), 0)), 0) AS nights', [$toExclusive, $from->toDateString()])
            ->value('nights') ?? 0);

        $roomCount = DB::table('hotel_room_units')
            ->whereIn('hotel_id', $hotelIds)
            ->whereNotIn('status', ['maintenance', 'renovation'])
            ->count();
        $periodDays = (int) $from->diffInDays($to) + 1;
        $roomNightCapacity = $roomCount * $periodDays;
        $occupancy = $roomNightCapacity > 0 ? round(($bookedNights / $roomNightCapacity) * 100, 1) : null;

        $reservations = (clone $base)
            ->with(['hotel:id,name', 'room:id,name', 'pet:id,name', 'user:id,name'])
            ->orderBy('check_in')
            ->limit(100)
            ->get();

        $driver = DB::connection()->getDriverName();
        $monthSql = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $trendRows = Reservation::query()->whereIn('hotel_id', $hotelIds)
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->selectRaw("{$monthSql} AS period, status, COUNT(*) AS total")
            ->groupBy('period', 'status')->get()->groupBy('period');
        $monthlyTrend = collect();
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();
        while ($cursor->lte($lastMonth)) {
            $statuses = $trendRows->get($cursor->format('Y-m'), collect())->keyBy('status');
            $requested = (int) $statuses->sum('total');
            $booked = (int) ($statuses->get('confirmed')->total ?? 0) + (int) ($statuses->get('completed')->total ?? 0);
            $monthlyTrend->push(['label' => $cursor->format('m/Y'), 'requested' => $requested, 'booked' => $booked]);
            $cursor->addMonth();
        }
        $trendMax = max(1, (int) $monthlyTrend->max('requested'));
        $monthlyTrend = $monthlyTrend->map(function ($month) use ($trendMax) {
            $month['request_height'] = max(3, round(($month['requested'] / $trendMax) * 100));
            $month['booked_height'] = max(3, round(($month['booked'] / $trendMax) * 100));
            return $month;
        });
        $bookingStatuses = Reservation::query()->whereIn('hotel_id', $hotelIds)
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return view('panel.reports', compact(
            'from', 'to', 'reservationCount', 'bookingValue', 'paidValue',
            'bookedNights', 'roomCount', 'periodDays', 'occupancy', 'reservations', 'monthlyTrend', 'bookingStatuses'
        ));
    }
}
