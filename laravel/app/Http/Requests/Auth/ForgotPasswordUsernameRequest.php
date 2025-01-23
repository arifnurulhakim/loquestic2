<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Player;

class ForgotPasswordUsernameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'username' => 'required|string',
        ];
    }

    /**
     * the data of above request
     *
     * @return void
     */
    public function data()
    {
        $player=Player::firstWhere('username',request()->username);
        return [
            'email' =>  $player->email,
            'code' => mt_rand(100000, 999999),
            'created_at' => now()
        ];
    }
}
