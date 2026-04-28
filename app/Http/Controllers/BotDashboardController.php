<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\Trade;

class BotDashboardController extends Controller
{
    public function index()
    {
        // 1. Fetch the assets the bot is currently monitoring
        $assets = Asset::all();

        // 2. Fetch active positions (trades that haven't been sold yet)
        $openTrades = Trade::whereNull('exit_price')->get();

        // 3. Fetch trade history (last 10 closed trades)
        $closedTrades = Trade::whereNotNull('exit_price')
                           ->orderBy('updated_at', 'desc')
                           ->take(10)
                           ->get();

        // 4. Calculate Total Net Profit (USDT)
        $totalNetProfit = $closedTrades->sum(function ($trade) {
            return ($trade->exit_price - $trade->entry_price) * $trade->quantity;
        });

        return view('bot.dashboard', compact('assets', 'openTrades', 'closedTrades', 'totalNetProfit'));
    }
}