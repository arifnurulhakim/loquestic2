<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function index()
    {
        try {
            $wallets = DB::table('wallets')
                ->leftJoin('players', 'wallets.player_id', '=', 'players.id')
                ->select('wallets.*', 'players.username')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $wallets,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $playerId = Auth::id();
            $wallet = Wallet::create([
                'player_id' => $playerId,
                // 'currency_code' => $request->input('currency_code'),
                'currency_code' => "IDR",
                'amount' => $request->input('amount'),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $wallet,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $wallet = DB::table('wallets')
                ->leftJoin('players', 'wallets.player_id', '=', 'players.id')
                ->select('wallets.*', 'players.username')
                ->where('wallets.id', $id)
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => $wallet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wallet not found',
                'error_code' => 'WALLET_NOT_FOUND',
            ], 404);
        }
    }

    public function showbyplayerid($id)
    {
        try {
            $wallet = Wallet::where('wallets.player_id', $id)
                ->leftJoin('players', 'wallets.player_id', '=', 'players.id')
                ->select('wallets.*', 'players.username')
                ->get();

            if ($wallet) {
                return response()->json([
                    'status' => 'success',
                    'data' => $wallet,
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wallet not found',
                    'error_code' => 'WALLET_NOT_FOUND',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'currency_id' => 'required|string|max:255',
                'amount' => 'required|numeric',
                // Add more validation rules if necessary
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $wallet = Wallet::findOrFail($id);
            $wallet->update($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $wallet,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $wallet = Wallet::findOrFail($id);
            if ($wallet) {
                $wallet->delete();
                return response()->json([
                    'status' => 'success',
                    'message' => 'wallet deleted successfully',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'wallet not found',
                    'error_code' => 'wallet_NOT_FOUND',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // GAME
    public function showbyplayer()
    {
        try {
            $playerId = Auth::id();

            $total_wallet_IDR = Wallet::where('player_id', $playerId)
                ->where('currency_code', 'IDR')
                ->sum('amount');
            // $total_wallet_coin = Wallet::where('player_id', $playerId)
            //     ->where('currency_code', 'COIN')
            //     ->sum('amount');

            $wallet = Wallet::where('player_id', $playerId)->get();

            if ($wallet->count() > 0) {
                return response()->json([
                    'status' => 'success',
                    'total_wallet' => $total_wallet_IDR,
                    // 'total_wallet_coin' => $total_wallet_coin,
                    // 'data' => $wallet,
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wallet not found',
                    'error_code' => 'WALLET_NOT_FOUND',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function convertIDR(Request $request)
    {
        try {
            $playerId = Auth::id();
            $wallet = Wallet::where('player_id', $playerId)
                ->where('currency_code', 'COIN')
                ->selectRaw('SUM(amount) as total_amount')
                ->groupby('player_id')
                ->first();

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            if ($wallet->total_amount >= 0) {
                $conversionRate = 250; // 1 idr = 250 coin
                $amountreqidr = $request->amount;
                $convertedIDRs = floor($wallet->total_amount / $conversionRate);

                if ($amountreqidr > $convertedIDRs) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Coin kurang',
                        'error_code' => 'COIN_KURANG',
                    ], 422);
                } else if ($amountreqidr <= $convertedIDRs) {
                    $totalhargaidr = ($amountreqidr * $conversionRate) * -1;
                    // dd($totalhargaidr);
                    // dd($totalmincoin);
                    $newWalletEntryIdr = new Wallet();
                    $newWalletEntryIdr->player_id = $playerId;
                    $newWalletEntryIdr->currency_code = 'IDR';
                    $newWalletEntryIdr->amount = $amountreqidr;
                    $newWalletEntryIdr->label = "hasil conversi dari Coin jadi IDR";
                    $newWalletEntryIdr->save();

                    $newWalletMinCoin = new Wallet();
                    $newWalletMinCoin->player_id = $playerId;
                    $newWalletMinCoin->currency_code = 'COIN';
                    $newWalletMinCoin->amount = $totalhargaidr;
                    $newWalletMinCoin->label = "Menconversikan Coin jadi IDR";
                    $newWalletMinCoin->save();

                    // Lakukan query kembali untuk mendapatkan total wallet
                    $total_wallet = Wallet::where('player_id', $playerId)
                        ->where('currency_code', 'COIN')
                        ->selectRaw('SUM(amount) as total_amount')
                        ->first();

                    $total_idr = Wallet::where('player_id', $playerId)
                        ->where('currency_code', 'IDR')
                        ->selectRaw('SUM(amount) as total_amount')
                        ->first();

                    if ($total_wallet) {
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Conversion to idr successful',
                            'total_coin' => $total_wallet, // Ubah sesuai dengan kolom yang sesuai
                            'total_idr' => $total_idr, // Ubah sesuai dengan kolom yang sesuai

                        ]);
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Wallet not found',
                            'error_code' => 'WALLET_NOT_FOUND',
                        ], 404);
                    }
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Insufficient coins for conversion',
                        'error_code' => 'INSUFFICIENT_COINS',
                    ], 400);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid wallet entry found',
                    'error_code' => 'WALLET_NOT_FOUND',
                ], 404);
            }
        } catch (\Exception $e) {

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function convertCoin(Request $request)
    {
        try {
            $playerId = Auth::id();

            $walletIdr = Wallet::where('player_id', $playerId)
                ->where('currency_code', 'IDR')
                ->selectRaw('SUM(amount) as total_amount')
                ->first();

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            if ($walletIdr && $walletIdr->total_amount >= $request->amount) {
                $conversionRate = 250; // 1 IDR = 250 COIN
                $amountReqCoin = $request->amount;
                $convertedCoins = floor($amountReqCoin * $conversionRate);

                if ($convertedCoins <= 0) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid amount for conversion',
                        'error_code' => 'INVALID_AMOUNT',
                    ], 422);
                }

                $totalHargaCoin = $convertedCoins;

                $newWalletEntryCoin = new Wallet();
                $newWalletEntryCoin->player_id = $playerId;
                $newWalletEntryCoin->currency_code = 'COIN';
                $newWalletEntryCoin->amount = $convertedCoins;
                $newWalletEntryCoin->label = "hasil conversi dari IDR jadi COIN";
                $newWalletEntryCoin->save();

                $newWalletMinIDR = new Wallet();
                $newWalletMinIDR->player_id = $playerId;
                $newWalletMinIDR->currency_code = 'IDR';
                $newWalletMinIDR->amount = -$amountReqCoin;
                $newWalletMinIDR->label = "Menconversikan IDR jadi COIN";
                $newWalletMinIDR->save();

                // Query again to get the updated total wallet
                $totalWalletCoin = Wallet::where('player_id', $playerId)
                    ->where('currency_code', 'COIN')
                    ->selectRaw('SUM(amount) as total_amount')
                    ->first();

                $totalWalletIDR = Wallet::where('player_id', $playerId)
                    ->where('currency_code', 'IDR')
                    ->selectRaw('SUM(amount) as total_amount')
                    ->first();

                if ($totalWalletCoin && $totalWalletIDR) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Conversion to coin successful',
                        'total_coin' => $totalWalletCoin->total_amount,
                        'total_idr' => $totalWalletIDR->total_amount,
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Wallet not found',
                        'error_code' => 'WALLET_NOT_FOUND',
                    ], 404);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid IDR wallet entry found or insufficient funds',
                    'error_code' => 'WALLET_NOT_FOUND',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function leaderboardplayer()
    {
        try {
            $playerId = Auth::id();

            $perPage = 10; // You can adjust the number of items per page as needed

            $rank = Wallet::select('player_id')
                ->selectRaw('SUM(amount) as total_amount')
                ->where('currency_code', 'IDR')
                ->groupBy('player_id')
                ->orderByDesc('total_amount')
                ->get();

            // Find the rank for a specific player
            $playerRank = $rank->search(function ($item) use ($playerId) {
                return $item['player_id'] == $playerId;
            });

            // Add rank to each record
            $rankedData = $rank->map(function ($item, $key) {
                $item['rank'] = $key + 1; // Rank starts from 1
                return $item;
            });

// If player is found, add details to $playerRank
            if ($playerRank !== false) {
                $playerRankData = $rankedData[$playerRank];
                $playerRankData['player_id'] = $playerId;
                $playerRank = $playerRankData;
            } else {
                // Handle the case where the player is not found
                $playerRank = null;
            }

            // Fetch the leaderboard with total amounts and ranks
            $leaderboard = Wallet::select('player_id')
                ->selectRaw('SUM(amount) as total_amount')
                ->where('currency_code', 'IDR')
                ->groupBy('player_id')
                ->orderByDesc('total_amount')
                ->paginate($perPage)
                ->withQueryString(); // Add withQueryString()

            // Calculate the cumulative rank for previous pages
            $previousRankOffset = ($leaderboard->currentPage() - 1) * $leaderboard->perPage();

            // Add ranks to the leaderboard data
            $leaderboardWithRank = $leaderboard->map(function ($item, $index) use ($previousRankOffset) {
                $item['rank'] = $index + 1 + $previousRankOffset;
                return $item;
            });

            // // Find the rank of the current player in the leaderboard
            // $selfRank = $rank->map(function ($item) use ($leaderboardWithRank) {
            //     $matchingItem = $leaderboardWithRank->where('player_id', $item['player_id'])->first();
            //     $item['rank'] = $matchingItem ? $matchingItem['rank'] : null;
            //     return $item;
            // });

            $response = [
                'status' => 'success',

                'peringkat' => $playerRank,
                'current_page' => $leaderboard->currentPage(),
                'last_page' => $leaderboard->lastPage(),
                'next_page_url' => $leaderboard->nextPageUrl(),
                'prev_page_url' => $leaderboard->previousPageUrl(),
                'per_page' => $leaderboard->perPage(),
                'total' => $leaderboard->total(),
                'data' => $leaderboardWithRank->toArray(),

            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // public function convertCoin(Request $request)
    // {
    //     try {
    //         $playerId = Auth::id();
    //         $wallet = Wallet::where('player_id', $playerId)
    //             ->where('currency_code', 'IDR')
    //             ->where('amount', '>', 0)
    //             ->first();

    //         if ($wallet) {
    //             $conversionRate = 250; // 1 idr = 250 coin
    //             $convertedCoins = floor($wallet->amount * $conversionRate);

    //             if ($convertedCoins >= 0) {
    //                 // Begin Transaction
    //                 // DB::beginTransaction();

    //                 // Ubah nilai amount pada wallet menjadi negatif
    //                 $wallet->amount = -$wallet->amount;
    //                 $wallet->save();

    //                 $wallet_cnn = Wallet::where('player_id', $playerId)
    //                     ->where('currency_code', 'COIN')
    //                     ->where('amount', '>', 0)
    //                     ->first();

    //                 if ($wallet_cnn) {
    //                     $wallet_mincnn = Wallet::where('player_id', $playerId)
    //                         ->where('currency_code', 'IDR')
    //                         ->where('amount', '<', 0)
    //                         ->first();
    //                     if ($wallet_mincnn) {
    //                         $wallet_mincnn->amount = -$wallet_mincnn->amount;
    //                         $wallet_mincnn->save();
    //                     }
    //                 } else {
    //                     // Tambahkan entri baru ke tabel wallet
    //                     $newWalletEntry = new Wallet();
    //                     $newWalletEntry->player_id = $playerId;
    //                     $newWalletEntry->currency_code = 'COIN';
    //                     $newWalletEntry->amount = $convertedCoins;
    //                     $newWalletEntry->label = "Menconversikan IDR jadi Coin";
    //                     $newWalletEntry->save();
    //                 }
    //                 // Lakukan query kembali untuk mendapatkan total wallet
    //                 $total_wallet = Wallet::where('player_id', $playerId)
    //                     ->where('currency_code', 'IDR')
    //                     ->where('amount', '>', 0)
    //                     ->sum('amount');
    //                 $total_cnn = Wallet::where('player_id', $playerId)
    //                     ->where('currency_code', 'COIN')
    //                     ->where('amount', '>', 0)
    //                     ->sum('amount');

    //                 if ($total_wallet) {
    //                     return response()->json([
    //                         'status' => 'success',
    //                         'message' => 'Conversion to coin successful',
    //                         'total_coin' => $total_cnn->amount, // Ubah sesuai dengan kolom yang sesuai
    //                         'total_idr' => $total_wallet->amount, // Ubah sesuai dengan kolom yang sesuai
    //                     ]);
    //                 } else {
    //                     return response()->json([
    //                         'status' => 'error',
    //                         'message' => 'Wallet not found',
    //                         'error_code' => 'WALLET_NOT_FOUND',
    //                     ], 404);
    //                 }
    //             } else {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Insufficient idrs for conversion',
    //                     'error_code' => 'INSUFFICIENT_COINS',
    //                 ], 400);
    //             }
    //         } else {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'No valid wallet entry found',
    //                 'error_code' => 'WALLET_NOT_FOUND',
    //             ], 404);
    //         }
    //     } catch (\Exception $e) {

    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

}
