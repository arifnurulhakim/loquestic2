<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordUsernameRequest;
use App\Mail\SendCodeResetPassword;
use App\Models\Player;
use Illuminate\Http\Request;
use App\Models\ResetCodePassword;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /**
     * Send random code to email of user to reset password (Setp 1)
     *
     * @param  mixed $request
     * @return void
     */
    public function __invoke(ForgotPasswordRequest $request)
    {
        try {
            ResetCodePassword::where('email', $request->email)->delete();
            $player = Player::firstWhere('email', $request->email);
            // dd($player);
            if (!$player) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'email not found',
                    'error_code' => 'EMAIL_NOT_FOUND',
                ], 401);
            }

            $codeData = ResetCodePassword::create($request->data());

            Mail::to($request->email)->send(new SendCodeResetPassword($codeData->code));
            return response()->json([
                'status' => 'success',
                'message' => 'email sended',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function username(ForgotPasswordUsernameRequest $request)
    {
        try {
            $player = Player::firstWhere('username', $request->username);
            ResetCodePassword::where('email', $player->email)->delete();
            // dd($player);
            if (!$player) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'username not found',
                    'error_code' => 'USERNAME_NOT_FOUND',
                ], 401);
            }

            $codeData = ResetCodePassword::create($request->data());

            Mail::to($player->email)->send(new SendCodeResetPassword($codeData->code));
            return response()->json([
                'status' => 'success',
                'message' => 'email sended',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
