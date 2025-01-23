<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\SendCodeOtp;
use App\Models\Level;
use App\Models\Player;
use App\Models\User;
use App\Models\Verification;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

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

            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
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

    public function index()
    {
        try {
            $players = Player::all()->makeHidden(['password', 'role']);
            return response()->json([
                'status' => 'success',
                'data' => $players,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validation rules
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255|unique:players,email',
                'phone_number' => 'required|string|min:10|max:15',
                'username' => 'required|string|max:255|unique:players,username',
                'password' => 'required|string|min:6',
            ]);

            // Check if validation fails
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            // Check if player with the given username already exists
            $existingPlayer = Player::where('username', $request->get('username'))->first();
            if ($existingPlayer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Player with this username already exists',
                    'error_code' => 'PLAYER_ALREADY_USE',
                ], 422);
            }

            // Create a new player
            $player = Player::create([
                'email' => $request->get('email'),
                'phone_number' => $request->get('phone_number'),
                'username' => $request->get('username'),
                'password' => bcrypt($request->get('password')),
            ]);
            if ($player) {
                // Buat entri di tabel wallets
                $wallet = Wallet::create([
                    'player_id' => $player->id,
                    'amount' => 0,
                    'currency_code' => 'IDR',
                    'category' => 'system',
                    'label' => 'default',
                ]);
            }

            // Hide the password field in the response
            $player->makeHidden(['password']);

            // Delete existing verification records for the email
            Verification::where('email', $request->email)->delete();
            $uuidCode = Str::uuid()->toString();

            // Generate and store a new verification code
            $codeData = Verification::create([
                'email' => $request->email,
                'code' => $uuidCode,
                'created_at' => now(),
            ]);

            // $link = 'http://127.0.0.1:8000/api/';
            $link = 'http://127.0.0.1:8000/api/';
            $veriflink = $link . 'verifiedEmail?code=' . $uuidCode;

            // Send verification email
            Mail::to($request->email)->send(new SendCodeOtp($veriflink));

            return response()->json([
                'status' => 'success',
                'message' => 'Verification email has been sent, please check your email',
                'data' => $player,
            ], 201);
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
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            // Check if player with the given username already exists
            $existingPlayer = Player::where('email', $request->get('email'))->first();
            if (!$existingPlayer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Player not found',
                    'error_code' => 'PLAYER_NOT_FOUND',
                ], 422);
            } else {
                // Delete existing verification records for the email
                Verification::where('email', $request->email)->delete();
                $uuidCode = Str::uuid()->toString();

                // Generate and store a new verification code
                $codeData = Verification::create([
                    'email' => $request->email,
                    'code' => $uuidCode,
                    'created_at' => now(),
                ]);

                // $link = 'https://doddi.plexustechdev.com/logistic-quest/laravel/public/api/';
                $link = 'http://127.0.0.1:8000/api/';
                $veriflink = $link . 'verifiedEmail?code=' . $uuidCode;

                // Send verification email
                Mail::to($request->email)->send(new SendCodeOtp($veriflink));
                return response()->json([
                    'status' => 'success',
                    'message' => 'Verification email has been sent, please check your email',
                    'data' => $existingPlayer,
                ], 201);

            }

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function verifiedEmail(Request $request)
    {
        $verification = Verification::firstWhere('code', $request->code);

        // Cek apakah kode reset password yang diminta ditemukan
        if (!$verification) {
            return response()->view('emailf', [], 422);
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
        return response()->view('emails', [], 200);
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
}
