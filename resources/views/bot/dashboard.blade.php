<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zahir Algo | Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-sans antialiased p-6">

    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-gray-700 pb-4">
            <div>
                <h1 class="text-3xl font-bold text-blue-400">Trading Command Center</h1>
                <p class="text-gray-400 text-sm mt-1">Live Triple-Confluence Algorithm</p>
            </div>
            <div class="text-right">
                <p class="text-gray-400 text-sm">Total Net Profit</p>
                <p class="text-2xl font-bold {{ $totalNetProfit >= 0 ? 'text-green-400' : 'text-red-400' }}">
                    {{ number_format($totalNetProfit, 2) }} USDT
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-lg">
                <h2 class="text-lg font-semibold mb-4 text-gray-300">📡 Active Watchlist</h2>
                <ul>
                    @foreach($assets as $asset)
                        <li class="flex justify-between items-center py-2 border-b border-gray-700 last:border-0">
                            <span class="font-medium">{{ $asset->symbol }}</span>
                            <span class="px-2 py-1 rounded text-xs font-bold {{ $asset->status == 'active' ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' }}">
                                {{ strtoupper($asset->status) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="md:col-span-2 bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-lg">
                <h2 class="text-lg font-semibold mb-4 text-gray-300">🔥 Open Positions</h2>
                @if($openTrades->isEmpty())
                    <p class="text-gray-500 italic mt-4">No active trades. Bot is hunting for setups...</p>
                @else
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-500 text-sm border-b border-gray-700">
                                <th class="pb-2">Pair</th>
                                <th class="pb-2">Entry Price</th>
                                <th class="pb-2">Quantity</th>
                                <th class="pb-2">Trailing Stop</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($openTrades as $trade)
                            <tr class="border-b border-gray-700 last:border-0 hover:bg-gray-750">
                                <td class="py-3 font-bold text-blue-300">{{ $trade->symbol }}</td>
                                <td class="py-3">{{ $trade->entry_price }}</td>
                                <td class="py-3">{{ $trade->quantity }}</td>
                                <td class="py-3 text-red-400 font-medium">{{ $trade->stop_loss }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="md:col-span-3 bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-lg mt-2">
                <h2 class="text-lg font-semibold mb-4 text-gray-300">📜 Recent Trade History</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="text-gray-500 text-sm border-b border-gray-700">
                                <th class="pb-2">Pair</th>
                                <th class="pb-2">Date</th>
                                <th class="pb-2">Entry</th>
                                <th class="pb-2">Exit</th>
                                <th class="pb-2">P/L (%)</th>
                                <th class="pb-2">Net (USDT)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($closedTrades as $trade)
                                @php
                                    $pnlValue = ($trade->exit_price - $trade->entry_price) * $trade->quantity;
                                    $pnlPercentage = (($trade->exit_price - $trade->entry_price) / $trade->entry_price) * 100;
                                    $isProfit = $pnlValue >= 0;
                                @endphp
                            <tr class="border-b border-gray-700 last:border-0">
                                <td class="py-3 font-medium">{{ $trade->symbol }}</td>
                                <td class="py-3 text-gray-400 text-sm">{{ $trade->updated_at->format('M d, H:i') }}</td>
                                <td class="py-3">{{ $trade->entry_price }}</td>
                                <td class="py-3">{{ $trade->exit_price }}</td>
                                <td class="py-3 font-bold {{ $isProfit ? 'text-green-400' : 'text-red-400' }}">
                                    {{ number_format($pnlPercentage, 2) }}%
                                </td>
                                <td class="py-3 font-bold {{ $isProfit ? 'text-green-400' : 'text-red-400' }}">
                                    {{ $isProfit ? '+' : '' }}{{ number_format($pnlValue, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    
    <meta http-equiv="refresh" content="60">
</body>
</html>