<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Wallet;
use App\Models\WalletKey;
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
    public function store(Request $request, $delivery)
    {
        try {

            $playerId = Auth::id();

            $player = Player::find($playerId);
                if($delivery !== 'order' && $delivery !== 'success'){
                    return response()->json([
                        'status' => 'error',
                        'message' => 'param not valid',
                    ], 422);
                }

                $validator = Validator::make($request->all(), [
                    'signature' => 'required',
                     'key' => 'required'
                ]);

                if ($validator->fails()) {
                    $errors = $validator->errors();
                    $formattedErrors = [];

                    foreach ($errors->keys() as $key) {
                        $formattedErrors[] = [
                            'key' => $key,
                            'value' => $errors->first($key),
                        ];
                    }

                    return response()->json([
                        'status' => 'error',
                        'message' => $formattedErrors,
                        'error_code' => 'INPUT_VALIDATION_ERROR',
                    ], 422);
                }
                    $signature = $this->decrypt($request->signature);
                    // dd($signature);

                    if ($signature === false) {
                        return response()->json([
                            'status' => 'error',
                            'message' => [['status' => 'error', 'value' => 'access denied']],
                            'error_code' => 'ACCESS_DENIED',
                        ], 404);
                    } else {
                        $amount = (int)$signature;
                        if($request->delivery == "order") {
                            // Cek jika amount tidak minus (positif atau 0)
                            if($amount >= 0) {
                                return response()->json([
                                    'error' => 'Amount harus bernilai negatif (minus).'
                                ], 400); // Mengembalikan HTTP status code 400 (Bad Request)
                            }else{
                                $key = md5($request->key);

                                $codeExist = WalletKey::where('key', $key)->first();

                                if (!$codeExist) {
                                    $storeKey = WalletKey::create([
                                        'player_id' => $playerId,
                                        'key' => $key
                                    ]);
                                    $wallet = Wallet::create([
                                        'player_id' => $playerId,
                                        'amount' => $amount,
                                        'currency_code' => "IDR",
                                        // 'is_play' => false,
                                    ]);

                                    return response()->json([
                                        'status' => 'success',
                                        'message' => [['status' => 'success', 'value' => 'sucessfully order']]
                                        // 'data' => $wallet,
                                    ], 201);
                                } else {
                                    return response()->json(['error' => 'udah ada orderan'], 400);
                                }

                            }

                            // Lanjutkan jika amount valid (minus)
                        }else{
                            if (!$player || $request->signature == null || $player->is_play == null) {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => [['status' => 'error', 'value' => 'you not playing, please play first']],
                                    'error_code' => 'NOT_PLAYING',
                                ], 404);
                            }
                            if($amount <= 0) {
                                return response()->json([
                                    'error' => 'Amount harus bernilai positif'
                                ], 400); // Mengembalikan HTTP status code 400 (Bad Request)
                            }else{
                                $receivedHash = $request->key;

                                // Plaintext yang ingin Anda verifikasi
                                $plaintext = $player->is_play;

                                // Menghasilkan hash MD5 dari plaintext yang Anda miliki
                                $expectedHash = $plaintext;

                                // Membandingkan hash yang diterima dengan hash yang diharapkan
                                if ($receivedHash === $expectedHash) {
                                    $player = Player::where('id', $playerId)->update([
                                        'is_play' => null,
                                    ]);
                                    $wallet = Wallet::create([
                                        'player_id' => $playerId,
                                        'amount' => $amount,
                                        'currency_code' => "IDR",
                                        // 'is_play' => false,
                                    ]);
                                    $codeExist = WalletKey::where('key', $request->key)->first();
                                    $codeExist->delete();

                                    return response()->json([
                                        'status' => 'success',
                                        'message' => [['status' => 'success', 'value' => 'delivery has been successfull']]
                                        // 'data' => $wallet,
                                    ], 201);

                                } else {
                                    return response()->json([
                                        'status' => 'error',
                                        'message' => [['status' => 'error', 'value' => 'access denied']],
                                        'error_code' => 'ACCESS_DENIED',
                                    ], 404);
                                }
                        }



                    }

                }

        } catch (\Exception $e) {
            dd($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function decrypt($request)
    {

        // Validate the request
        // $request->validate([
        //     'signature' => 'required|string',
        // ]);

        // Retrieve the encrypted string from the request
        // $encryptedString = $request;

        // Specify the secret key and initialization vector
        $key = '^%$C1eT$3Vq0L50p'; // Plain text key
        $iv = 'p05L0qV3$Te1C$%^';

        // Decrypt the text using AES decryption
        $decryptedString = $this->decryptAES($request, $key, $iv);
        // Return the decrypted string as JSON
        return $decryptedString;
    }

    private function decryptAES($encryptedString, $key, $iv)
    {
        return openssl_decrypt($encryptedString, 'AES-128-CBC', $key, 0, $iv);
    }

    public function play(Request $request)
    {
        try {
            $playerId = Auth::id();
            $player = Player::find($playerId);
            // dd($playerId);
            $key = md5($request->key);
            // Perbarui pemain dengan ID yang sesuai


            $codeExist = WalletKey::where('key', $key)->first();

            if ($codeExist) {
                if( $player->is_play != null){
                    return response()->json(['error' => 'udah main'], 400);
                }else{

                $updatePlay = Player::where('id', $playerId)->update([
                    'is_play' => $key,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => $player->username . ' playing',
                ], 200); // Mengubah status menjadi 200
            }

            } else {

                return response()->json(['error' => 'belum order'], 400);

            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function gameOver(Request $request)
    {
        try {
            $playerId = Auth::id();

            // Dapatkan total wallet IDR yang ada (bisa positif, nol, atau minus)
            $total_wallet_IDR = Wallet::where('player_id', $playerId)
                ->where('currency_code', 'IDR')
                ->sum('amount');
                if($total_wallet_IDR >= 50000){
                    return response()->json([
                        'status' => 'error',
                        'message' => [['status' => 'error', 'value' => 'belum game over']],
                    ], 404);
                }

            // Hitung berapa yang harus ditambahkan agar totalnya menjadi 500000
            $desired_total = 500000;
            $amount = $desired_total - $total_wallet_IDR; // Hitung selisih

            // Buat wallet baru atau tambahkan entry ke wallet
            $wallet = Wallet::create([
                'player_id' => $playerId,
                'currency_code' => "IDR",
                'amount' => $amount, // amount sudah dihitung di atas
            ]);
            $total_wallet_update = Wallet::where('player_id', $playerId)
            ->where('currency_code', 'IDR')
            ->sum('amount');

            return response()->json([
                'status' => 'success',
                'massage'=>'game over',
                'data' => $total_wallet_update,
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
    public function orderlist(){
        try {
            $playerId = Auth::id();
            $codeExist = WalletKey::where('player_id', $playerId)->get();
            if($codeExist){
                return response()->json([
                    'status' => 'success',
                    'data' => $codeExist,
                ]);
            }else{
                return response()->json([
                    'status' => 'error',
                    'message' => 'order not found',
                    'error_code' => 'ORDER_NOT_FOUND',
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
                $errors = $validator->errors();
                $formattedErrors = [];

                foreach ($errors->keys() as $key) {
                    $formattedErrors[] = [
                        'key' => $key,
                        'value' => $errors->first($key),
                    ];
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $formattedErrors,
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

    public function destroy(Request $request)
    {
        try {
            // Check the password parameter
            $password = $request->password;
            // dd($password);
            if ($password !== 'ResetPosJuara123') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid password',
                    'error_code' => 'INVALID_PASSWORD',
                ], 403);
            }

            // Query to get all wallets that do not have amount 0 and players.email does not contain 'guest'
            $walletsToDelete = DB::table('wallets')
                ->join('players', 'wallets.player_id', '=', 'players.id')
                ->where('wallets.amount', '!=', 0)
                ->where('players.email', 'not like', '%guest%')
                ->select('wallets.id')
                ->get();

            if ($walletsToDelete->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No wallets found to delete',
                    'error_code' => 'wallet_NOT_FOUND',
                ], 404);
            }

            // Delete the wallets
            foreach ($walletsToDelete as $wallet) {
                Wallet::find($wallet->id)->delete();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Score reset successfully',
            ]);
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
                $errors = $validator->errors();
                $formattedErrors = [];

                foreach ($errors->keys() as $key) {
                    $formattedErrors[] = [
                        'key' => $key,
                        'value' => $errors->first($key),
                    ];
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $formattedErrors,
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
                $errors = $validator->errors();
                $formattedErrors = [];

                foreach ($errors->keys() as $key) {
                    $formattedErrors[] = [
                        'key' => $key,
                        'value' => $errors->first($key),
                    ];
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $formattedErrors,
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

            $perPage = 10;

            $rank = Wallet::select('players.username', 'wallets.player_id') // Pilih semua kolom dari tabel players
                ->selectRaw('SUM(amount) as total_amount')
                ->join('players', 'players.id', '=', 'wallets.player_id') // Gabungkan dengan tabel players berdasarkan player_id
                ->where('wallets.currency_code', 'IDR') // Sesuaikan dengan nama tabel untuk kondisi
                ->where('username', '!=', 'guest')
                ->groupBy('wallets.player_id')
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

            if ($playerRank !== false) {
                $playerRankData = $rankedData[$playerRank];
                $playerRankData['player_id'] = $playerId;
                $playerRank = $playerRankData;
            } else {
                // Handle the case where the player is not found
                $playerRank = null;
            }

            // Fetch the leaderboard with total amounts and ranks
            $leaderboard = Wallet::select('players.username', 'wallets.player_id')
                ->selectRaw('SUM(amount) as total_amount')
                ->join('players', 'players.id', '=', 'wallets.player_id') // Gabungkan dengan tabel players berdasarkan player_id
                ->where('currency_code', 'IDR')
                ->where('username', '!=', 'guest')
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
                'next_page' => $leaderboard->currentPage() < $leaderboard->lastPage() ? $leaderboard->currentPage() + 1 : null,
                'prev_page' => $leaderboard->currentPage() > 1 ? $leaderboard->currentPage() - 1 : null,
                'per_page' => $leaderboard->perPage(),
                'total' => $leaderboard->total(),
                'data' => $leaderboardWithRank->toArray(),
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function leaderboardadmin(Request $request)
    {
        try {
            // Ambil parameter dari permintaan
            $perPage = $request->input('pageSize', 10); // Ambil pageSize, default 10
            $sortDirection = $request->input('sortDirection', 'asc'); // Ambil sortDirection, default 'asc'
            $globalFilter = $request->input('globalFilter', ''); // Ambil globalFilter, default ''

            $rank = Wallet::select('players.username', 'wallets.player_id')
                ->selectRaw('SUM(amount) as total_amount')
                ->join('players', 'players.id', '=', 'wallets.player_id')
                ->where('wallets.currency_code', 'IDR')
                ->where('username', '!=', 'guest')
                ->when($globalFilter, function ($query, $filter) {
                    return $query->where('players.username', 'like', "%{$filter}%");
                })
                ->groupBy('wallets.player_id')
                ->orderBy('total_amount', $sortDirection)
                ->get();

            // Fetch the leaderboard with total amounts and ranks
            $leaderboard = Wallet::select('players.username', 'wallets.player_id')
                ->selectRaw('SUM(amount) as total_amount')
                ->join('players', 'players.id', '=', 'wallets.player_id')
                ->where('currency_code', 'IDR')
                ->where('username', '!=', 'guest')
                ->when($globalFilter, function ($query, $filter) {
                    return $query->where('players.username', 'like', "%{$filter}%");
                })
                ->groupBy('wallets.player_id')
                ->orderBy('total_amount', $sortDirection)
                ->paginate($perPage)
                ->withQueryString(); // Add withQueryString()

            // Calculate the cumulative rank for previous pages
            $previousRankOffset = ($leaderboard->currentPage() - 1) * $leaderboard->perPage();

            // Add ranks to the leaderboard data
            $leaderboardWithRank = $leaderboard->map(function ($item, $index) use ($previousRankOffset) {
                $item['rank'] = $index + 1 + $previousRankOffset;
                return $item;
            });

            $response = [
                'status' => 'success',
                'current_page' => $leaderboard->currentPage(),
                'last_page' => $leaderboard->lastPage(),
                'next_page' => $leaderboard->currentPage() < $leaderboard->lastPage() ? $leaderboard->currentPage() + 1 : null,
                'prev_page' => $leaderboard->currentPage() > 1 ? $leaderboard->currentPage() - 1 : null,
                'per_page' => $leaderboard->perPage(),
                'total' => $leaderboard->total(),
                'data' => $leaderboardWithRank->toArray(),
                'params' => [
                    'pageSize' => $perPage,
                    'sortDirection' => $sortDirection,
                    'globalFilter' => $globalFilter,
                ], // Menyertakan parameter yang diterima dalam respons
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
