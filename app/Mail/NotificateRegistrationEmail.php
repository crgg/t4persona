<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificateRegistrationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $ip;
    public $userAgent;
    public $geo;
    public $address;

    public function __construct($user, $ip, $userAgent, array $geo = array(), array $address = array())
    {
        $this->user      = $user;
        $this->ip        = (string) $ip;
        $this->userAgent = (string) $userAgent;
        $this->geo       = $geo;
        $this->address   = $address;
    }

    public function build()
    {
        return $this->subject('New user registered: '.(isset($this->user->email) ? $this->user->email : 'unknown'))
            ->markdown('emails.notificate_registration', array(
                'user'      => $this->user,
                'ip'        => $this->ip,
                'userAgent' => $this->userAgent,
                'geo'       => $this->geo,
                'address'   => $this->address,
            ));
    }
}
