<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\VerifyEmailClinical;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    public $tries = 3;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function backoff()
    {
        return [60, 300, 900];
    }

    public function handle()
    {
        Mail::to($this->user->email)->send(new VerifyEmailClinical($this->user));
    }
}
