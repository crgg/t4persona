<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\SoftwareMediaController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\SoftwareInterationsController;
use App\Http\Controllers\WhatsappConversationZipController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
/*
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
*/


Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me',      [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Email resend (requiere usuario autenticado)
    Route::post('/email/resend', [EmailVerificationController::class, 'resend']);
    Route::get('/auth/user',      [AuthController::class, 'user']);

    Route::post('/update-user-info', [UserController::class, 'update_user_info']);
    Route::post('/change-password',  [UserController::class, 'change_password']);
    Route::post('/upload-avatar-picture', [UserController::class, 'upload_avatar_picture']);

    Route::middleware(['email.verified'])->group(function () {
    });

        Route::apiResources([
            'assistants'   => AssistantController::class,
            'media'        => MediaController::class
        ]);
        Route::post('/set-assistant-avatar', [AssistantController::class, 'set_assistant_avatar']);
        Route::post('/store-whatsapp-conversation', [WhatsappConversationZipController::class, 'store_whatsapp_zip']);
        Route::get    ('/whatsapp-conversations',        [WhatsappConversationZipController::class, 'index']);
        Route::get    ('/whatsapp-conversations/{id}',   [WhatsappConversationZipController::class, 'show']);
        Route::delete ('/whatsapp-conversations/{id}',   [WhatsappConversationZipController::class, 'destroy']);


        Route::apiResource('interactions', InteractionController::class)->except(['update']);

        Route::post('/sessions/start', [SessionController::class, 'start']);
        Route::post('/sessions/{session}/end', [SessionController::class, 'end']);
        Route::get('/sessions/{session}',   [SessionController::class, 'show']);
        Route::get('/interactions/was-canceled', [InteractionController::class, 'wasCanceled']);
        Route::post('/interactions/{interaction}/cancel', [InteractionController::class, 'cancel']);      // cancelar por ID
        Route::post('/interactions/cancel-last',          [InteractionController::class, 'cancelLast']);  // cancelar la ultima pendiente por sesión

    Route::get('/assistant-questions', [QuestionController::class, 'index']);
    Route::post('/assistant-answers', [AnswerController::class, 'store']);

});

Route::middleware('software.respond_api_token')->group(function () {

    Route::post('/software-interactions/{interaction}/respond', [SoftwareInterationsController::class, 'respond']);
    Route::apiResource('software-interactions', SoftwareInterationsController::class);
    Route::get('/software/users-open-sessions', [SoftwareInterationsController::class, 'usersWithOpenSessions']);

    Route::get('/software-media',                 [SoftwareMediaController::class, 'index']);
    Route::get('/software-media/{media}',         [SoftwareMediaController::class, 'show']);
    Route::patch('/software-media/{media}/enrich',[SoftwareMediaController::class, 'enrich']);



});




Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');
