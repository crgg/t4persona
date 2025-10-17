<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class GenerateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:generate_admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Admins';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $emails = ['geremy@t4app.com','ramon@t4app.com'];

        foreach ($emails as $key => $email) {

            $exists_email = User::where('email',$email)->first();
            if($exists_email){
                echo("Already exists user $email".PHP_EOL);
                continue;
            }

            $exists_email = User::create([
                'name'          => $email,
                'email'         => $email,
                'password_hash' => Hash::make('12345678'),
                'rol'           => 'admin',
                'email_verified_at' => now(),
                'date_register' => now(),
                'last_login'    => null,
            ]);

            echo("CREATED user $email, default password '12345678' ".PHP_EOL);

        }

    }
}
