<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Binance\API;
use App\Models\Asset;
use App\Models\Trade;
use Illuminate\Support\Facades\Log;

class RunTradingBot extends Command
{
    protected $signature = 'bot:run';
    protected $description = 'Executes the continuous Binance trading loop';

    public function handle()
    {
        $api = new API(env('BINANCE_API_KEY'), env('BINANCE_SECRET_KEY'));
        Log::info("Bot cron executed. Checking markets...");

        // Fetch active assets
        $assets = Asset::where('status', 'active')->get();

        foreach ($assets as $asset) {
            try {
                $ticks = $api->candlesticks($asset->symbol, "15m");
                
                if (isset($ticks['code']) || empty($ticks)) {
                    Log::error("Binance API Error or Empty Response for {$asset->symbol}");
                    continue; 
                }

                $closes = $this->extractClosingPrices($ticks);
                $rsi = $this->calculateRSI($closes, 14);

                $openTrade = Trade::where('symbol', $asset->symbol)
                                  ->whereNull('exit_price')
                                  ->first();

                if (!$openTrade) {
                    if ($rsi < 30) {
                        $this->executeBuyOrder($api, $asset);
                    }
                } else {
                    $currentPrice = end($closes);
                    $this->manageOpenPosition($api, $openTrade, $currentPrice);
                }

            } catch (\Exception $e) {
                Log::error("Bot Error ({$asset->symbol}): " . $e->getMessage());
            }
        }
        
        // No sleep() needed. The script finishes and closes until the next minute.
    }

    /**
     * Helper: Extract closing prices from Binance candlestick data
     */
    private function extractClosingPrices($ticks)
    {
        $closes = [];
        foreach ($ticks as $tick) {
            // In jaggedsoft/php-binance-api, index 'close' holds the closing price
            $closes[] = (float) $tick['close']; 
        }
        return $closes;
    }

    /**
     * Helper: Calculate Relative Strength Index (RSI)
     * (A basic native PHP implementation so you don't need external C-extensions)
     */
    private function calculateRSI($closes, $period = 14)
    {
        if (count($closes) < $period + 1) return 50; // Default if not enough data

        $gains = 0;
        $losses = 0;

        // Calculate initial average gain/loss
        for ($i = 1; $i <= $period; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            if ($change > 0) $gains += $change;
            else $losses += abs($change);
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        // Smooth the rest of the data
        for ($i = $period + 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gain = ($change > 0) ? $change : 0;
            $loss = ($change < 0) ? abs($change) : 0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss == 0) return 100;
        
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    /**
     * Executes the actual Buy Order with Risk Management
     */
    private function executeBuyOrder($api, $asset)
    {
        $this->warn("Initiating BUY sequence for {$asset->symbol}...");

        try {
            // 1. Fetch USDT Balance
            // The API returns an array of all balances. We extract the 'available' USDT.
            $balances = $api->balances();
            $usdtBalance = $balances['USDT']['available'] ?? 0;

            // 2. Apply the 1% Risk Rule
            $riskPercentage = 0.01; 
            $tradeValueUsdt = $usdtBalance * $riskPercentage;

            // 3. Enforce Binance Minimums (Hardcoded to $11 to be safe against fees/fluctuations)
            if ($tradeValueUsdt < 11) {
                if ($usdtBalance >= 11) {
                    $this->line("1% risk is less than minimum. Defaulting to 11 USDT trade.");
                    $tradeValueUsdt = 11;
                } else {
                    $this->error("Insufficient total USDT balance ({$usdtBalance}). Aborting.");
                    return;
                }
            }

            // 4. Get the Current Live Price
            $currentPrice = $api->price($asset->symbol);

            // 5. Calculate Quantity to Buy
            $rawQuantity = $tradeValueUsdt / $currentPrice;

            // 6. Format Quantity (Handling LOT_SIZE)
            // For SOL and most major altcoins, rounding down to 2 decimal places is safe.
            // We use floor() to round down so we don't accidentally try to spend more USDT than allocated.
            $formattedQuantity = floor($rawQuantity * 100) / 100;

            $this->info("Calculated Trade: {$formattedQuantity} {$asset->symbol} at ~{$currentPrice} USDT.");

            // 7. EXECUTE THE MARKET ORDER
            // **********************************************************
            // WARNING: Uncomment the line below to spend real money!
            // **********************************************************
            
            // $order = $api->marketBuy($asset->symbol, $formattedQuantity);

            // Mocking a successful order response for testing purposes:
            $order = [
                'status' => 'FILLED',
            ];

            // 8. Log the Open Position in the Database
            if (isset($order['status']) && $order['status'] == 'FILLED') {
                
                // Calculate our exit parameters automatically
                $stopLossPrice = $currentPrice * 0.95; // 5% drop
                $takeProfitPrice = $currentPrice * 1.10; // 10% gain

                Trade::create([
                    'symbol' => $asset->symbol,
                    'type' => 'BUY',
                    'entry_price' => $currentPrice,
                    'quantity' => $formattedQuantity,
                    'stop_loss' => $stopLossPrice,
                    'take_profit' => $takeProfitPrice,
                ]);

                $this->info("SUCCESS! Trade recorded in database. Stop Loss set at {$stopLossPrice}.");
                Log::info("BOUGHT {$formattedQuantity} {$asset->symbol} @ {$currentPrice}");
            }

        } catch (\Exception $e) {
            $this->error("Failed to execute BUY: " . $e->getMessage());
            Log::error("Buy Order Error ({$asset->symbol}): " . $e->getMessage());
        }
    }

    /**
     * Manages an open position (Stop Loss / Take Profit)
     */
    private function manageOpenPosition($api, $trade, $currentPrice)
    {
        // TODO: Logic to check if current price hit stop_loss or take_profit
        // If yes, execute $api->marketSell() and update Trade record in DB
    }
}