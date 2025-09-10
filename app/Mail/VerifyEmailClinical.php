<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;

class VerifyEmailClinical extends Mailable
{
    /** @var \App\Models\User */
    public $user;


    public function __construct(User $user)
    {
        $this->user = $user;

    }

    public function build()
    {


        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $this->user->id, 'hash' => sha1($this->user->email)],
            true
        );

        return $this->subject('Verify your email')
            ->view('emails.verify_clinical', [
                'user' => $this->user,
                'url'  => $verificationUrl,
                'logoUrl' => config('app.logo_url', env('APP_LOGO_URL', null)),
                'colors' => [
                    'bg'       => '#F8FAFC', // gris muy claro
                    'card'     => '#FFFFFF', // blanco
                    'primary'  => '#0EA5E9', // azul clínico
                    'primaryD' => '#0284C7', // azul más oscuro
                    'text'     => '#0F172A', // gris azulado oscuro
                    'muted'    => '#475569', // gris medio
                    'border'   => '#E2E8F0', // borde suave
                ],
            ]);
    }
}
