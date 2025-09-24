<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailClinical;

class EmailVerificationController extends Controller
{
    // Decode APP_KEY (supports base64:...)
    protected function appKeyBytes(): string
    {
        $key = config('app.key');
        return Str::startsWith($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }

    // Verifica email con HMAC simple (?tk=EXP.SIGN), sin rutas firmadas
    public function verify(Request $r, $id, $hash)
    {
        $user = User::findOrFail($id);

        // Mantén el chequeo de hash del email
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['status' => false, 'msg' => 'Invalid verification hash'], 400);
        }

        // Nuevo: validar token HMAC simple
        $tk = (string) $r->query('tk', '');
        if ($tk === '' || ! str_contains($tk, '.')) {
            return response()->json(['status' => false, 'msg' => 'Missing token'], 400);
        }

        [$exp, $sig] = explode('.', $tk, 2);
        if (!ctype_digit($exp) || strlen($sig) !== 64) {
            return response()->json(['status' => false, 'msg' => 'Invalid token'], 400);
        }
        if ((int) $exp < time()) {
            return response()->json(['status' => false, 'msg' => 'Expired token'], 400);
        }

        $calc = hash_hmac('sha256', $user->getKey() . '|' . $exp, $this->appKeyBytes());
        if (! hash_equals($calc, $sig)) {
            return response()->json(['status' => false, 'msg' => 'Invalid signature'], 400);
        }

        if ($user->email_verified_at) {
            return response()->json(['status' => true, 'msg' => 'Email already verified']);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json(['status' => true, 'msg' => 'Email verified successfully']);
    }

    public function resend(Request $r)
    {
        $user = $r->user();

        if ($user->email_verified_at) {
            return response()->json(['status' => false, 'msg' => 'Email already verified'], 400);
        }

        Mail::to($user->email)->queue(new VerifyEmailClinical($user));

        return response()->json(['status' => true, 'msg' => 'Verification email queued']);
    }
}
