<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserCollection;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\UserAdminResource;
use Illuminate\Validation\Rules\Password;
use App\Http\Resources\UserAdminCollection;

class UserAdminController extends Controller
{
    CONST ROLE_ARRAY = ['admin','user'];

    public function index(Request $request){


        $rol = '';

        if(isset( $request->role )){

            if(! in_array( $request->role , self::ROLE_ARRAY ) ){
                return response()->json([
                    'status' => false,
                    'msg'    => "Plase select 'admin' or 'user' for role, used '$request->role' "
                ]);
            }

            $rol = $request->role;

        }else{
            $rol = 'admin';
        }

        $perPage  = $request->per_page ?? 25;

        $query = User::query()
            ->where('rol',$rol);
        $query->orderBy('id','desc');

        if($rol == 'admin'){
            $paginator = $query->paginate($perPage)->withQueryString();
            return response()->json(
                (new UserAdminCollection($paginator))->toArray($request)
            );
        }else{
            $paginator = $query->paginate($perPage)->withQueryString();
            return response()->json(
                (new UserCollection($paginator))->toArray($request)
            );
        }

    }

    public function login(Request $r)
    {
        $r->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt(['email' => $r->email, 'password' => $r->password])) {
            return response()->json([
                'status' => false,
                'msg'    => 'Invalid credentials',
            ], 401);
        }

        $user = User::where('email', $r->email)->where('rol' ,'admin' )->firstOrFail();
        $user->last_login = now();
        $user->save();

        $token = $user->createToken('api')->accessToken;

        return response()->json([
            'status' => true,
            'msg'    => 'Login successful',
            'data'   => [
                'token' => $token,
                'type'  => 'Bearer',
                'user'  =>  UserAdminResource::make($user)
            ],
        ]);
    }




}
