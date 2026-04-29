<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\Trade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    /**
     * Toggles the Master Kill Switch
     */
    public function toggleBot()
    {
        $currentStatus = Cache::get('bot_status', 'active');
        $newStatus = $currentStatus === 'active' ? 'paused' : 'active';
        
        Cache::put('bot_status', $newStatus);
        
        $action = $newStatus === 'paused' ? 'PAUSED' : 'RESUMED';
        Log::info("User manually {$action} the bot via Dashboard.");

        return back()->with('status', "Bot successfully {$newStatus}!");
    }

    /**
     * Forces an immediate market sell for a specific trade
     */
    public function panicSell($tradeId)
    {
        $trade = Trade::whereNull('exit_price')->findOrFail($tradeId);
        $asset = Asset::where('symbol', $trade->symbol)->first();
        
        // Grab the latest price from the diagnostics table we built earlier
        $currentPrice = $asset->current_price; 

        if (!$currentPrice) {
            return back()->with('error', "Cannot fetch live price. Wait for next bot cycle.");
        }

        // **********************************************************
        // WARNING: If live trading, you must call the Binance API here!
        // $api = new \Binance\API(env('BINANCE_API_KEY'), env('BINANCE_SECRET_KEY'));
        // $order = $api->marketSell($trade->symbol, $trade->quantity);
        // **********************************************************

        // 1. Close the trade in the database
        $trade->update(['exit_price' => $currentPrice]);

        // 2. Log it
        Log::info("🚨 PANIC SELL TRIGGERED via Dashboard: {$trade->symbol} at {$currentPrice}");

        return back()->with('status', "Panic Sell Executed for {$trade->symbol}!");
    }
}