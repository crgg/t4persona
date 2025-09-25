<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    private const MAX_FILE_KB      = 102400; // 100MB

    public function update_user_info(Request $request){

        $validator = \Validator::make($request->all(),
            [
                'name'     => ['sometimes','string','max:150'],
                'age'      => ['sometimes','nullable','integer','min:15'],
                'alias'    => ['sometimes','nullable','string','max:150'],
                'country'  => ['sometimes','nullable','string','max:100'],
                'language' => ['sometimes','nullable','string','max:50'],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if(count($data) > 0){
            User::where('id',Auth::user()->id)->update($data);

        }

        return response()->json([
            'status' => true,
            'msg'    => 'User info updated',
            'data'   => UserResource::make( User::where('id',Auth::user()->id)->first() )
        ]);

    }

    public function change_password(Request $request){


        $validator = \Validator::make($request->all(), [
            'current_password' => ['required', 'string', function ($attr, $value, $fail) use ($request) {
                $user = $request->user();
                if (!$user || !Hash::check($value, $user->password_hash)) {
                    $fail('Current password is incorrect.');
                }
            }],
            'password' => [
                'required',
                'string',
                'confirmed',                 // needs 'password_confirmation'
                'different:current_password',
                Password::min(8),
            ],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'password.required'         => 'Please enter a new password.',
            'password.confirmed'        => 'Password confirmation does not match.',
            'password.different'        => 'New password must be different from the current password.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $request->user()->forceFill([
            'password_hash' => Hash::make($request->input('password')),
        ])->save();

        return response()->json([
            'status'  => true,
            'message' => 'Password updated successfully.',
        ]);


    }

    public function upload_avatar_picture(Request $request){

        $validator = \Validator::make($request->all(), [
            'file'         => ['required','file','max:'.self::MAX_FILE_KB, 'image'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'errors'=>$validator->errors()], 422);
        }
        $data = $validator->validated();

        $user = User::where('id',Auth::user()->id)->first();

        $file = $request->file('file');

        $fileName = preg_replace('/\s+/', '_', $file->getClientOriginalName());

        $key = 'user/'.$user->id.'/avatar/'.(string) Str::uuid().'-'.$fileName;

        if(isset($user->avatar_path) ){
            if(Storage::disk('s3')->exists( $user->avatar_path )){
                Storage::disk('s3')->delete( $user->avatar_path );
            }
        }

        Storage::disk('s3')->putFileAs(
            dirname($key),
            $file,
            basename($key),
            [
                'visibility'  => 'public',
                'ContentType' => $file->getClientMimeType() ?: $file->getMimeType(),
            ]
        );


        $user->avatar_path = $key;
        $user->save();
        $user->refresh();

        return response()->json([
            'status' => true,
            'msg'    => 'Avatar stored',
            'data'   => UserResource::make( $user ),
        ], 201);
    }


}
