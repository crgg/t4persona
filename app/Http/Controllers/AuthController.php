<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Validator};

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $v = Validator::make($r->all(), [
            'name'     => 'required|string|max:150',
            'email'    => 'required|email:rfc,dns|unique:users,email',
            'password' => 'required|string|min:8',
        ]);
        if ($v->fails()) {
            return response()->json(['message'=>'Datos inválidos','errors'=>$v->errors()], 422);
        }

        $user = User::create([
            'name'          => $r->name,
            'email'         => $r->email,
            'password_hash' => Hash::make($r->password),
            'rol'           => 'user',
            'date_register' => now(),
            'last_login'    => null,
        ]);

        $token = $user->createToken('api')->accessToken;

        return response()->json([
            'token' => $token,
            'type'  => 'Bearer',
            'user'  => [
                'id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'rol'=>$user->rol
            ],
        ], 201);
    }

    public function login(Request $r)
    {
        $r->validate(['email'=>'required|email','password'=>'required|string']);
        if (! Auth::attempt(['email'=>$r->email,'password'=>$r->password])) {
            return response()->json(['message'=>'Credenciales inválidas'], 401);
        }

        $user = User::where('email', $r->email)->firstOrFail();
        $user->last_login = now(); $user->save();

        $token = $user->createToken('api')->accessToken;
        return response()->json([
            'token'=>$token,'type'=>'Bearer',
            'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'rol'=>$user->rol]
        ]);
    }

    public function me(Request $r)
    {
        return response()->json($r->user());
    }

    public function logout(Request $r)
    {
        $r->user()->token()->revoke();
        return response()->json(['message'=>'Sesión cerrada']);
    }
}
