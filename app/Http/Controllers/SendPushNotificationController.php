<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use App\Notifications\FirebaseSendNotification;

class SendPushNotificationController extends Controller
{
    public function send_push_notification(Request $request)
    {

        $validator = \Validator::make($request->all(), [
            'title'         => 'nullable|string|max:100',
            'message'       => 'required|string|max:1000',
            'data'          => 'nullable|array',
            'user_id'     => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'msg'    => $validator->messages()->first(),
            ], 422);
        }

        if(isset($request->user_id)){


            $user = User::where('id',$request->user_id)->first();

            if(!$user){
                return response()->json([
                    'status' => false,
                    'msg'    => 'User not exists'
                ]);
            }

            $deviceTokens = DeviceToken::where('user_id', $request->user_id)->whereNotNull('device_token')->pluck('device_token')->toArray();
        }else{
            try {
                $deviceTokens = DeviceToken::where('user_id', auth()->user()->id)->whereNotNull('device_token')->pluck('device_token')->toArray();
            } catch (\Throwable $th) {
                \Log::error("FALLO EL ENVIO DE NOTIFICACION  ",[$request->all()]);
                \Log::error("FALLO EL ENVIO DE NOTIFICACION  ",[$th]);
                $deviceTokens = [];
            }

        }

        if( count($deviceTokens) <= 0 ){

            return response()->json([
                'status' => false,
                'msg'    => "Not have device tokens"
            ]);

        }

        $data = $request->input('data', []);

        $data['from_t4evers'] = 'true';

        return FirebaseSendNotification::send_array(
            $deviceTokens,
            $data,
            $request->input('message'),
            $request->input('title', 'T4EVER')
        );

    }
}
