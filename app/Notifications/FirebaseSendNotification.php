<?php

namespace App\Notifications;

use Illuminate\Http\Request;


class FirebaseSendNotification {


    public static function multiple_send($device_tokens, $data){

    }
    public static function send_bck($device_tokens, $data) {

        \Log::info('NOITIFICATION-TO-T4-M_BACK_FIRE', ['devices_tokes' => $device_tokens
       , 'data' => [$data]]);

		$url= config('app.T4NOTIFICATION_URL') . '/api/send-multi-push-notification';

		\Log::info('NOITIFICATION-TO-T4-M_BACK_FIRE_21', ['url' => $url]);

		$fields = [
			    'title'         => 'T4EVER',
				'message'       => "1",
				'silent'        => 1,
				'application'   => 't4ever',
				'device_tokens'  => $device_tokens,
				'data'          => $data
		];

        $fields = json_encode ( $fields );

        $headers = array (
			    'Connect-With-Push-Notification-Api: 123456789',
				'Content-Type: application/json',
		);

		try {

			$ch = curl_init ();
			curl_setopt ( $ch, CURLOPT_URL, $url );
			curl_setopt ( $ch, CURLOPT_POST, true );
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch,  CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
			curl_setopt ( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		$result = curl_exec ( $ch );
		$response_ = json_decode($result, true);
			\Log::info('NOITIFICATION-TO-T4-M_53', ['result json decode' => $response_]);
			if ($response_["failure"]) {
				\Log::error('NOITIFICATION-TO-T4-M_55', ['failure' => $response_["failure"]]);
			}

		} catch (\Throwable $th) {
			\Log::error('NOITIFICATION-TO-T4_ERROR', ['error' => $th]);
		}

		// return work new notification
		return response()->json([
			'status' => true,
			'result' => $result
		]);

    }

    public static function send_array($device_tokens, $data , $message = '' , $title = '') {

        \Log::info('NOITIFICATION-TO-T4-A', ['request-data' => $device_tokens]);
		//https://backend.t4tms.us/
		//$url=  'http://localhost:8050/api/send-multi-push-notification';

		$url= config('app.T4NOTIFICATION_URL') . '/api/send-multi-push-notification';

		\Log::info('NOITIFICATION-TO-T4-A', ['url' => $url]);

		$fields = [
			    'title'         =>  !empty( $title )   ? $title   : 'T4EVER',
				'message'       =>  !empty( $message ) ? $message : "2",
				'silent'        =>  0,
				'application'   => 't4ever',
				'device_tokens'  => $device_tokens,
				'data'          => $data
		];

        $fields = json_encode ( $fields );

        $headers = array (
			    'Connect-With-Push-Notification-Api: 123456789',
				'Content-Type: application/json',
		);

		try {

			$ch = curl_init ();
			curl_setopt ( $ch, CURLOPT_URL, $url );
			curl_setopt ( $ch, CURLOPT_POST, true );
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch,  CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
			curl_setopt ( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		$result = curl_exec ( $ch );
		$response_ = json_decode($result, true);
			\Log::info('NOITIFICATION-TO-T4-A', ['result json decode' => $response_]);
			if ($response_["failure"]) {
				\Log::error('NOITIFICATION-TO-T4-A', ['failure' => $response_["failure"]]);
			}

		} catch (\Throwable $th) {
			\Log::error('NOITIFICATION-TO-T4-A', ['error' => $th]);
		}

		// return work new notification
		return response()->json([
			'status' => true,
			'result' => $result
		]);

    }
    /**
     *  normally is for web
     */
    public static function send(Request $r) {
        \Log::info('NOITIFICATION-TO-T4', ['request-data' => $r->all()]);

		$url= config('app.T4NOTIFICATION_URL') . '/api/send-push-notification';

		\Log::info('NOITIFICATION-TO-T4', ['url' => $url]);

		$fields = [
			    'title'         => 'T4EVER',
				'message'       => $r->mensaje,
				'silent'        => 0,
				'application'   => 't4ever',
				'device_token'  => $r->device_token,
				'data'          => $msg
		];

        $fields = json_encode ( $fields );

        $headers = array (
			    'Connect-With-Push-Notification-Api: 123456789',
				'Content-Type: application/json',
		);

		try {

			$ch = curl_init ();
			curl_setopt ( $ch, CURLOPT_URL, $url );
			curl_setopt ( $ch, CURLOPT_POST, true );
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch,  CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
			curl_setopt ( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		$result = curl_exec ( $ch );
		$response_ = json_decode($result, true);
			\Log::info('NOITIFICATION-TO-T4', ['result json decode' => $response_]);
			if ($response_["failure"]) {
				\Log::error('NOITIFICATION-TO-T4', ['failure' => $response_["failure"]]);
			}

		} catch (\Throwable $th) {
			\Log::error('NOITIFICATION-TO-T4', ['error' => $th]);
		}

		// return work new notification
		return response()->json([
			'status' => true,
			'result' => $result
		]);


    }
}
