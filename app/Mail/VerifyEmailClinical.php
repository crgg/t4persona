<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class VerifyEmailClinical extends Mailable
{
    /** @var \App\Models\User */
    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    protected function appKeyBytes(): string
    {
        $key = config('app.key');
        return Str::startsWith($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }

    protected function makeVerifyUrl(User $user, int $ttlMinutes = 60): string
    {
        $exp  = now()->addMinutes($ttlMinutes)->timestamp;

        $base = URL::route('verification.verify', [
            'id'   => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $msg = $user->getKey() . '|' . $exp;                 // mensaje a firmar
        $sig = hash_hmac('sha256', $msg, $this->appKeyBytes()); // firma con APP_KEY

        return $base . '?tk=' . $exp . '.' . $sig;
    }

    public function build()
    {
        // 🔁 reemplaza la firma de Laravel por nuestro HMAC simple
        $verificationUrl = $this->makeVerifyUrl($this->user, 60);

        return $this->subject('Verify your email')
            ->view('emails.verify_clinical', [
                'user'    => $this->user,
                'url'     => $verificationUrl,
                'logoUrl' => config('app.logo_url', env('APP_LOGO_URL', null)),
                'colors'  => [
                    'bg'       => '#F8FAFC',
                    'card'     => '#FFFFFF',
                    'primary'  => '#0EA5E9',
                    'primaryD' => '#0284C7',
                    'text'     => '#0F172A',
                    'muted'    => '#475569',
                    'border'   => '#E2E8F0',
                ],
            ]);
    }
}
