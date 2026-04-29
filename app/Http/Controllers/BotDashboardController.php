<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\Trade;

class BotDashboardController extends Controller
{
    public function index()
    {
        $assets = Asset::all();
        $openTrades = Trade::whereNull('exit_price')->get();

        // 1. Fetch ALL closed trades chronologically for the Chart
        $allClosedTrades = Trade::whereNotNull('exit_price')
                                ->orderBy('updated_at', 'asc')
                                ->get();

        $cumulativeProfit = 0;
        $chartDates = [];
        $chartProfits = [];

        // Build the Equity Curve data points
        foreach ($allClosedTrades as $trade) {
            $profit = ($trade->exit_price - $trade->entry_price) * $trade->quantity;
            $cumulativeProfit += $profit;

            // Push data into arrays for the chart
            $chartDates[] = $trade->updated_at->format('M d, H:i');
            $chartProfits[] = round($cumulativeProfit, 2);
        }

        // 2. Fetch only the last 10 for the History Table (descending order)
        $closedTrades = Trade::whereNotNull('exit_price')
                           ->orderBy('updated_at', 'desc')
                           ->take(10)
                           ->get();

        $totalNetProfit = $cumulativeProfit;

        // 3. Pass the new arrays to the Blade view
        return view('bot.dashboard', compact(
            'assets', 
            'openTrades', 
            'closedTrades', 
            'totalNetProfit', 
            'chartDates', 
            'chartProfits'
        ));
    }
}