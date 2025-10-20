<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\API\ManageClientsController;

class GoogleController extends Controller
{
    public function redirect_to_google_login(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'from' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => true,
                'msg'    => $validator->messages()
            ]);
        }

        // generamos la url con las claves de auth de google
        $clientId    = config('app.GOOGLE_CLIENT_ID');
        $redirectUri = config('app.CALLBACK_GOOGLE_URL');
        $scope = 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/user.phonenumbers.read';

        $authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
            'client_id'              => $clientId,
            'redirect_uri'           => $redirectUri,
            'scope'                  => $scope,
            'response_type'          => 'code',
            'include_granted_scopes' => 'true',
            'access_type'            => 'offline'
        ]);

        return response()->json([
            'status' => true,
            'data'   => $authUrl
        ]);
    }

    public function callback(Request $request)
    {
        if (isset($request->error) && $request->error == 'access_denied') {
            return redirect(config('app.FRONT_END_WEB_URL').'sign-in?gstatus=false&msg=You%have%not%been%granted%access%to%log%in%through%Google.');
        }

        $code         = $request->get('code');
        $clientId     = config('app.GOOGLE_CLIENT_ID');
        $clientSecret = config('app.GOOGLE_SECRET_ID');
        $redirectUri  = config('app.CALLBACK_GOOGLE_URL');

        $postData = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri
        ];

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);

        if (isset($response->access_token)) {

            $accessToken = $response->access_token;

            // People API con Http facade (igual que tu versión)
            $library_response = Http::withToken($accessToken)->get(
                'https://people.googleapis.com/v1/people/me?personFields=addresses,ageRanges,biographies,birthdays,calendarUrls,clientData,coverPhotos,emailAddresses,events,externalIds,genders,imClients,interests,locales,locations,memberships,metadata,miscKeywords,names,nicknames,occupations,organizations,phoneNumbers,photos,relations,sipAddresses,skills,urls,userDefined'
            );

            $userInfo = $library_response->json();

            // email para iniciar sesión
            if (isset($userInfo['emailAddresses'][0]['value']) && !empty($userInfo['emailAddresses'][0]['value'])) {
                $email = $userInfo['emailAddresses'][0]['value'];
                $exists_email = User::where('email', $email)->first();

                if ($exists_email) {
                    // TOKEN con Passport
                    $token = $exists_email->createToken('api')->accessToken;

                    $generate_code = Str::random(100);

                    \Log::debug('EMAIL USER HAS LOGIN BY EMAIL - '.$email);
                    \Log::debug('generatet_user'.$generate_code);

                    Cache::put('generatet_user'.$generate_code, $exists_email);
                    Cache::put('api_token'.$generate_code, $token);

                    return redirect(config('app.FRONT_END_WEB_URL').'login?gstatus=true&code='.$generate_code);
                }

                // crear usuario si no existe (TU FORMATO)
                if (!$exists_email) {
                    $profileName = $userInfo['names'][0]['displayName']
                        ?? trim(($userInfo['names'][0]['givenName'] ?? '') . ' ' . ($userInfo['names'][0]['familyName'] ?? ''));

                    if (empty($profileName)) {
                        $profileName = strstr($email, '@', true) ?: $email;
                    }

                    $user = User::create([
                        'name'              => $profileName,
                        'email'             => $email,
                        'password_hash'     => Hash::make('12345678'),
                        'rol'               => 'user',
                        'email_verified_at' => now(),
                        'date_register'     => now(),
                        'last_login'        => null,
                    ]);

                    // TOKEN con Passport
                    $token = $user->createToken('api')->accessToken;

                    $generate_code = Str::random(100);

                    $user->refresh();

                    Cache::put('generatet_user'.$generate_code, $user );
                    Cache::put('api_token'.$generate_code, $token);

                    \Log::debug('EMAIL USER HAS CREATED AN ACCOUNT BY GOOGLE GMAIL - '.$email);

                    return redirect(config('app.FRONT_END_WEB_URL').'login?gstatus=true&code='.$generate_code);
                }
            }
        }

        // error genérico
        return redirect(config('app.FRONT_END_WEB_URL').'login?gstatus=false&msg=Google%login%is%not%working,%please%try%again%later.');
    }

    public function redirect(Request $request)
    {
        $generatet_user = Cache::get('generatet_user'.$request->code);
        $api_token      = Cache::get('api_token'.$request->code);

        Cache::forget('generatet_user'.$request->code);
        Cache::forget('api_token'.$request->code);

        if ($api_token) {
            \Log::info('generatet_user'.$request->code);

            Auth::guard()->setUser($generatet_user);
            auth()->login($generatet_user);

            return response()->json([
                'status'    => true,
                'msg'       => 'Google Login successfully',
                'data'      => [
                    'token' => $api_token,
                    'type'  => 'Bearer',
                    'user'  =>  UserResource::make( $generatet_user )
                ]
            ]);
        }

        return response()->json([
            'status' => false,
            'msg'    => 'Google Login token expired'
        ]);
    }

    public static function checkIfMailIsGmail($email)
    {
        $exploded_email = explode('@', $email);
        $get_end = end($exploded_email);
        return $get_end == 'gmail.com';
    }
}
