<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use App\Jobs\SendVerificationEmail;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\{Auth, Hash, Validator};
use App\Http\Controllers\RegistrationNotifierController;

class AuthController extends Controller
{

    public function register(Request $r)
    {
        $v = Validator::make($r->all(), [
            'name'                  => 'required|string|max:150',
            'email'                 => 'required|email:rfc,dns|unique:users,email',
            'password'              => 'required|string|min:8|confirmed', // requires password_confirmation
            'password_confirmation' => 'required|string|min:8',
        ], [
            'password.confirmed' => 'Passwords do not match.',
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'msg'    => 'Invalid data',
                'data'   => ['errors' => $v->errors()],
            ], 422);
        }

        $user = User::create([
            'name'          => $r->name,
            'email'         => $r->email,
            'password_hash' => Hash::make($r->password),
            'rol'           => 'user',
            'date_register' => now(),
            'last_login'    => null,
        ]);

        dispatch(new SendVerificationEmail($user));
        RegistrationNotifierController::notify($user, $r);
        $token = $user->createToken('api')->accessToken;

        return response()->json([
            'status' => true,
            'msg'    => "Registered. a email was sent to $r->email check email."
        ], 201);
    }

    public function login(Request $r)
    {
        $r->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'token_device'  => 'nullable|string'
        ]);

        if (! Auth::attempt(['email' => $r->email, 'password' => $r->password])) {
            return response()->json([
                'status' => false,
                'msg'    => 'Invalid credentials',
            ], 401);
        }

        $user = User::where('email', $r->email)->where('rol','!=' ,'admin' )->firstOrFail();
        $user->last_login = now();
        $user->save();

        $token = $user->createToken('api')->accessToken;

        if(isset( $r->token_device )){
            DeviceToken::save_device_token( $user->id , $r->token_device , $r->phone_from , $r->version );
        }

        return response()->json([
            'status' => true,
            'msg'    => 'Login successful',
            'data'   => [
                'token' => $token,
                'type'  => 'Bearer',
                'user'  =>  UserResource::make($user)
            ],
        ]);
    }

    public function user(Request $r)
    {
        return response()->json([
            'status' => true,
            'msg'    => 'OK',
            'data'   => ['user' => $r->user()],
        ]);
    }

    public function logout(Request $r)
    {
        $r->user()->token()->revoke();

        return response()->json([
            'status' => true,
            'msg'    => 'Logged out',
        ]);
    }
}
