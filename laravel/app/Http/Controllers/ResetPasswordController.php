<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\Player;
use App\Models\ResetCodePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends Controller
{
    /**
     * @param  mixed $request
     * @return void
     */
    public function __invoke(ResetPasswordRequest $request)
    {
        try {
            $passwordReset = ResetCodePassword::firstWhere('code', $request->code);

            if (!$passwordReset) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid reset code',
                ], 422);
            }

            if ($passwordReset->isExpire()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Code is expired',
                ], 422);
            }

            $player = Player::firstWhere('email', $passwordReset->email);
            $player->update([
                "password" => Hash::make($request->new_password),
            ]);

            $passwordReset->where('code', $request->code)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been successfully reset',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function resetFirstPassword(Request $request)
    {
        try {
            $player = Auth::user();

            if (empty($player)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized, please login again',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'old_password' => 'required|string|min:6',
                'new_password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                ], 422);
            }

            // Validasi password lama
            if (!Hash::check($request->old_password, $player->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Old password not matched',
                ], 422);
            }

            $player->update([
                "password" => Hash::make($request->new_password),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been successfully reset',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function resetFirstPasswordAdmin(Request $request)
    {
        try {
            $user = Auth::guard('user')->user();
            if (empty($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized, please login again',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'old_password' => 'required|string|min:6',
                'new_password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                ], 422);
            }

            // Validasi password lama
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Old password not matched',
                ], 422);
            }

            $user->update([
                "password" => Hash::make($request->new_password),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been successfully reset',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
