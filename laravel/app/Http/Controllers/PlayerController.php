<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\SendCodeOtp;
use App\Models\Level;
use App\Models\Player;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Verification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;

class PlayerController extends Controller
{

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $credentials = $request->only('username', 'password');
            Auth::shouldUse('player');
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Username or password invalid',
                    'error_code' => 'USERNAME_OR_PASSWORD_INVALID',
                ], 401);
            }

            $player = Auth::user();
            if ($player->email_verified_at == null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'email not verified, please check your email',
                    'error_code' => 'NOT_VERIFIED',
                ], 401);
            }
            $token = JWTAuth::fromUser($player);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $player->id,
                    'username' => $player->username,
                    'token' => $token,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function adminLogin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $credentials = $request->only('email', 'password');
            Auth::shouldUse('user');
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'email or password invalid',
                    'error_code' => 'EMAIL_OR_PASSWORD_INVALID',
                ], 401);
            }

            $user = Auth::user();

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->id,
                    'user' => $user->name,
                    'email' => $user->email,
                    'token' => $token,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reverifyEmail(Request $request)
    {
        try {
            // Validation rules
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255',
            ]);

            // Check if validation fails
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
            $existingPlayer = Player::where('email', $request->get('email'))->first();
            Verification::where('email', $request->email)->delete();

            $codeData = Verification::create([
                'email' => request()->email,
                'code' => mt_rand(100000, 999999),
                'created_at' => now(),
            ]);
            Mail::to($request->email)->send(new SendCodeOtp($codeData->code));
            return response()->json([
                'status' => 'success',
                'message' => 'Verification email has been sent, please check your email',
                'data' => $existingPlayer,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function AdminRegister(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $user = User::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => bcrypt($request->get('password')),
            ]);

            $token = JWTAuth::fromUser($user);

            return response()->json(compact('user', 'token'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e], 500);
        }
    }

    public function logout()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized, please login again',
                    'error_code' => 'USER_NOT_FOUND',
                ], 401);
            }
            if (!Auth::check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token',
                    'error_code' => 'INVALID_TOKEN',
                ], 401);
            }

            Auth::logout();
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function logoutAdmin(Request $request)
    {
        try {
            $user = Auth::guard('user')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized, please login again',
                    'error_code' => 'USER_NOT_FOUND',
                ], 401);
            }

            Auth::guard('user')->logout();

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProfile()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                    'error_code' => 'UNAUTHORIZED',
                ], 401);
            }

            $highestLevel = $user->levels()->max('level');

            // Get the current level (last played level)
            $currentLevel = $user->levels()->latest()->first();

            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'highest_level' => $highestLevel,
                'current_level' => $currentLevel ? $currentLevel->level : 0,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $userData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getprofileadmin()
    {
        try {
            $user = Auth::guard('user')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                    'error_code' => 'UNAUTHORIZED',
                ], 401);
            }

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $userData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            // Ambil parameter dari permintaan jika ada
            $perPage = $request->input('pageSize', 10); // Ambil pageSize, default 10
            $sortDirection = $request->input('sortDirection', 'asc'); // Ambil sortDirection, default 'asc'
            $globalFilter = $request->input('globalFilter', ''); // Ambil globalFilter, default ''

            // Ambil semua pemain dan urutkan hasilnya
            $query = Player::query();

            // Filter berdasarkan globalFilter jika ada
            if ($globalFilter) {
                $query->where('username', 'like', "%{$globalFilter}%");
            }

            // Urutkan hasil berdasarkan parameter sortDirection
            $players = $query->orderBy('username', $sortDirection)->paginate($perPage);

            // Sembunyikan password dan role pada koleksi pemain
            $players->makeHidden(['password', 'role']);

            return response()->json([
                'status' => 'success',
                'current_page' => $players->currentPage(),
                'last_page' => $players->lastPage(),
                'next_page' => $players->currentPage() < $players->lastPage() ? $players->currentPage() + 1 : null,
                'prev_page' => $players->currentPage() > 1 ? $players->currentPage() - 1 : null,
                'per_page' => $players->perPage(),
                'total' => $players->total(),
                'data' => $players->items(), // Menyertakan data pemain yang telah dipaginate
                'params' => [
                    'pageSize' => $perPage,
                    'sortDirection' => $sortDirection,
                    'globalFilter' => $globalFilter,
                ], // Menyertakan parameter yang diterima dalam respons
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255|unique:players,email',
                'phone_number' => 'required|string|min:10|max:15',
                'username' => 'required|string|max:255|unique:players,username',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $getplayer = Player::where('username', $request->get('username'))

                ->first();
            if ($getplayer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'player already use',
                    'error_code' => 'PLAYER_ALREADY_USE',
                ], 422);
            }

            $player = Player::create([
                'email' => $request->get('email'),
                'phone_number' => $request->get('phone_number'),
                'username' => $request->get('username'),
                'password' => bcrypt($request->get('password')),
            ]);

            $player->makeHidden(['password']);

            Verification::where('email', $request->email)->delete();

            $codeData = Verification::create([
                'email' => request()->email,
                'code' => mt_rand(100000, 999999),
                'created_at' => now(),
            ]);

            $wallet = Wallet::create([
                'player_id' => $player->id,
                'currency_code' => 'IDR',
                'amount' => 250000,
                'label' => 'default',
            ]);

            Mail::to($request->email)->send(new SendCodeOtp($codeData->code));

            return response()->json([
                'status' => 'success',
                'message' => 'Verification email has been sended, please check your email',
                'data' => $player,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function verifiedEmail(Request $request)
    {
        $verification = Verification::firstWhere('code', $request->code);

        // Cek apakah kode reset password yang diminta ditemukan
        if (!$verification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Verificated email',
            ], 422);
        }
        if ($verification->isExpire()) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP is expired',
            ], 422);
        }

        $player = Player::firstWhere('email', $verification->email);

        // $player->update($request->only('password'));
        $player->update([
            "email_verified_at" => now(),
        ]);

        $verification->where('code', $request->code)->delete();

        // return $this->jsonResponse(null, trans('email has been verified'), 200);
        return response()->json([
            'status' => 'success',
            'message' => 'email has been verified',
        ], 200);
    }
    public function show($id)
    {
        try {
            $player = Player::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $player,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getplayerbygame($game_code)
    {
        try {
            $player = Level::select('levels.game_code', 'levels.player_id', 'players.name', 'players.username', 'players.email')
                ->join('players', 'levels.player_id', '=', 'players.id')
                ->where('levels.game_code', $game_code)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $player,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $player = Player::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'picture' => 'nullable|string',
                'username' => 'required|string|max:255',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $player->update([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'picture' => $request->get('picture'),
                'username' => $request->get('username'),
                'password' => bcrypt($request->get('password')),
            ]);

            $player->makeHidden(['password']);

            return response()->json([
                'status' => 'success',
                'data' => $player,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $player = Player::findOrFail($id);

            if ($player) {
                $player->delete();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Player with name ' . $player->name . ' and with email ' . $player->email . ' has been deleted.',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Player with name ' . $player->name . ' and with email ' . $player->email . ' not found.',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function Unauthorized()
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized',
            'error_code' => 'UNAUTHORIZED',
        ], 401);
    }

    public function importCsv(Request $request)
    {
        // Validasi input file CSV
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        // Panggil fungsi untuk mengimpor data
        $data = $this->importData($request->file('file'));

        // Simpan data ke database
        $this->saveToDatabase($data);

        return response()->json(['status' => 'success',  'message' => 'Data berhasil diimpor.']);
    }

    private function importData($file)
    {
        // Menggunakan PHPExcel untuk membaca file CSV


        $reader = IOFactory::createReader('Csv');
        $spreadsheet = $reader->load($file->getPathname());
        $sheetData = $spreadsheet->getActiveSheet()->toArray();

        // Menghapus baris kosong dari hasil impor
        $sheetData = array_filter($sheetData);

        return $sheetData;
    }

    private function saveToDatabase($data)
    {
        // Loop through each row of imported data
        foreach ($data as $index => $row) {
            // Lewati header (baris pertama)
            if ($index === 0) {
                continue;
            }


            // Ambil username, email, dan phone_number dari baris saat ini
            $username = $row[0];
            $email = $row[1];
            $phoneNumber = $row[2];

            // Jika semua nilai dalam baris adalah null, lanjutkan ke baris berikutnya
            if ($username === null && $email === null && $phoneNumber === null) {
                continue;
            }

            // Cari record dengan email yang sama di database
            $player = Player::where('email', $email)->first();

            // Jika record ditemukan, update data
            // Jika tidak ditemukan dan tidak semua nilainya null, insert sebagai record baru
            if ($player) {
                $player->update([
                    'username' => $username,
                    'phone_number' => $phoneNumber,
                ]);
            } elseif ($username !== null && $email !== null && $phoneNumber !== null) {
                Player::create([
                    'username' => $username,
                    'email' => $email,
                    'phone_number' => $phoneNumber,
                ]);
            }
        }
    }
}
