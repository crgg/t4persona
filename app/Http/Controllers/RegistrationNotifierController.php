<?php

namespace App\Http\Controllers;

use App\Mail\NotificateRegistrationEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class RegistrationNotifierController extends Controller
{

    public static function notify(User $user, $req = null)
    {
        $req = $req instanceof Request ? $req : request();

        $ip        = (string) ($req->ip() ?: '0.0.0.0');
        $userAgent = (string) $req->userAgent();

        $geo = array();
        try {
            $res = Http::timeout(3)->get('https://ipapi.co/'.$ip.'/json/');
            //$res = Http::timeout(3)->get('https://ipapi.co/190.20.255.169/json/');
            $j   = $res->ok() ? (array) $res->json() : array();
            $geo = array(
                'city'      => isset($j['city']) ? $j['city'] : null,
                'region'    => isset($j['region']) ? $j['region'] : null,
                'country'   => isset($j['country_name']) ? $j['country_name'] : (isset($j['country']) ? $j['country'] : null),
                'latitude'  => isset($j['latitude']) ? $j['latitude'] : null,
                'longitude' => isset($j['longitude']) ? $j['longitude'] : null,
                'postal'    => isset($j['postal']) ? $j['postal'] : null,
                'timezone'  => isset($j['timezone']) ? $j['timezone'] : null,
                'org'       => isset($j['org']) ? $j['org'] : null,
                'asn'       => isset($j['asn']) ? $j['asn'] : null,
            );
        } catch (\Throwable $e) {
            // Optionally: \Log::warning('Geo lookup failed: '.$e->getMessage());
            $geo = array(
                'city' => null, 'region' => null, 'country' => null,
                'latitude' => null, 'longitude' => null, 'postal' => null,
                'timezone' => null, 'org' => null, 'asn' => null,
            );
        }

        // Address payload from user model (adjust to your schema)
        $address = array(
            'address' => isset($user->address) ? $user->address : null,
            'city'    => isset($user->city) ? $user->city : null,
            'state'   => isset($user->state) ? $user->state : null,
            'zip'     => isset($user->zip) ? $user->zip : null,
            'country' => isset($user->country) ? $user->country : null,
        );

        // Recipients from .env (SIGNUP_ALERT_RECIPIENTS=mail1@mail.com,mail2@mail.com)
        $cfg = (string)'geremysaico@gmail.com,ramon@t4app.com';
        //$cfg = (string)'geremysaico@gmail.com';
        $recipients = array_filter(array_map('trim', explode(',', $cfg)));

        foreach ($recipients as $to) {
            Mail::to($to)->send(new NotificateRegistrationEmail($user, $ip, $userAgent, $geo, $address));
        }
    }
}
