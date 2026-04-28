<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Binance\API;
use App\Models\Asset;
use App\Models\Trade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
                Log::info("Monitoring {$asset->symbol} | Current RSI: " . round($rsi, 2));

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
                
                $stopLossPrice = $currentPrice * 0.95; 
                $takeProfitPrice = $currentPrice * 1.10; 

                Trade::create([
                    'symbol' => $asset->symbol,
                    'type' => 'BUY',
                    'entry_price' => $currentPrice,
                    'quantity' => $formattedQuantity,
                    'stop_loss' => $stopLossPrice,
                    'take_profit' => $takeProfitPrice,
                ]);

                // --- NEW TELEGRAM ALERT ---
                $alertMessage = "🟢 *NEW TRADE EXECUTED*\n\n"
                              . "🤖 *Pair:* {$asset->symbol}\n"
                              . "💰 *Price:* {$currentPrice} USDT\n"
                              . "⚖️ *Quantity:* {$formattedQuantity}\n"
                              . "🛡️ *Stop Loss:* {$stopLossPrice} USDT";

                $this->sendTelegramAlert($alertMessage);
                // --------------------------

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
        // --- 1. THE TRAILING STOP LOGIC ---
        // Define your trailing distance (e.g., 5% behind the peak price)
        $trailingPercentage = 0.05; 
        
        // Calculate where the stop loss SHOULD be based on the current live price
        $potentialNewStop = $currentPrice * (1 - $trailingPercentage);

        // If the potential new stop is HIGHER than the current saved stop loss, move it up!
        if ($potentialNewStop > $trade->stop_loss) {
            $trade->update([
                'stop_loss' => $potentialNewStop
            ]);
            
            $this->info("📈 Trail Up: Moved Stop-Loss to {$potentialNewStop} USDT.");
            Log::info("Trailing Stop for {$trade->symbol} raised to {$potentialNewStop}");
            
            // Note: We don't send a Telegram alert here, otherwise your phone 
            // will buzz every minute the coin goes up!
        }


        // --- 2. THE EXIT LOGIC ---
        $triggerType = null;
        $isProfitable = false;

        // Notice we removed the hard Take-Profit check. 
        // The bot will ONLY exit when the trailing stop is hit.
        if ($currentPrice <= $trade->stop_loss) {
            $triggerType = 'TRAILING STOP LOSS';
            $isProfitable = ($currentPrice > $trade->entry_price);
        }

        // 3. Execute Exit if Condition is Met
        if ($triggerType) {
            $this->warn("🚨 {$triggerType} triggered for {$trade->symbol} at {$currentPrice}!");

            try {
                // **********************************************************
                // WARNING: Uncomment to execute a real SELL!
                // **********************************************************
                // $order = $api->marketSell($trade->symbol, $trade->quantity);

                // Mocking response for testing:
                $order = ['status' => 'FILLED'];

                if (isset($order['status']) && $order['status'] == 'FILLED') {
                    
                    $trade->update(['exit_price' => $currentPrice]);

                    $pnlValue = ($currentPrice - $trade->entry_price) * $trade->quantity;
                    $pnlPercentage = (($currentPrice - $trade->entry_price) / $trade->entry_price) * 100;
                    $pnlFormatted = number_format($pnlPercentage, 2);

                    $emoji = $isProfitable ? "🚀" : "🛑";
                    $alertMessage = "{$emoji} *POSITION CLOSED: {$triggerType}*\n\n"
                                  . "🤖 *Pair:* {$trade->symbol}\n"
                                  . "🚪 *Exit Price:* {$currentPrice} USDT\n"
                                  . "📈 *P/L:* {$pnlFormatted}%\n"
                                  . "💵 *Net:* " . number_format($pnlValue, 2) . " USDT";

                    $this->sendTelegramAlert($alertMessage);

                    $this->info("Position closed successfully. PNL: {$pnlFormatted}%");
                    Log::info("SOLD {$trade->quantity} {$trade->symbol} @ {$currentPrice} ({$triggerType})");
                }

            } catch (\Exception $e) {
                $this->error("Failed to execute SELL: " . $e->getMessage());
                Log::error("Sell Order Error ({$trade->symbol}): " . $e->getMessage());
            }
        } else {
            $this->line("Holding {$trade->symbol} @ {$currentPrice}. Trailing Stop: {$trade->stop_loss}");
        }
    }

    /**
     * Sends a formatted message to your personal Telegram app
     */
    private function sendTelegramAlert($message)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) return; // Fail silently if not configured

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        try {
            Http::post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown', // Allows us to use bolding and emojis
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram Alert Failed: " . $e->getMessage());
        }
    }
}