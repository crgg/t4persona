<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\SendVerificationEmail;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    // Verifica email desde el enlace firmado
    public function verify(Request $r, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->email))) {
            return response()->json([
                'status' => false,
                'msg'    => 'Invalid verification hash',
            ], 400);
        }

        if (! $r->hasValidSignature()) {
            return response()->json([
                'status' => false,
                'msg'    => 'Expired or invalid link',
            ], 400);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'status' => true,
                'msg'    => 'Email already verified',
            ]);
        }

        $user->email_verified_at = now();
        $user->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Email verified successfully',
        ]);
    }

    // Reenvía el correo de verificación (requiere auth:api)
    public function resend(Request $r)
    {
        $user = $r->user();

        if ($user->email_verified_at) {
            return response()->json([
                'status' => false,
                'msg'    => 'Email already verified',
            ], 400);
        }

        dispatch(new SendVerificationEmail($user));

        return response()->json([
            'status' => true,
            'msg'    => 'Verification email queued',
        ]);
    }
}
