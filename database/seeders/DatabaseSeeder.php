<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // A back-office/API user for issuing Sanctum tokens.
        //
        // This is a LOCAL DEVELOPMENT credential only. It is intentionally
        // written in plain sight because it grants access to nothing but a
        // throwaway local database; never reuse it anywhere real. The password
        // is hashed by the model cast before it is stored.
        User::updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'API Admin',
                'password' => 'password',
            ],
        );

        $this->call([
            ProductSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
