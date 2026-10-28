<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['Amara Okafor',    'amara.okafor@example.test'],
            ['Liam Fitzgerald', 'liam.fitzgerald@example.test'],
            ['Priya Raghavan',  'priya.raghavan@example.test'],
            ['Chen Wei',        'chen.wei@example.test'],
            ['Sofia Marchetti', 'sofia.marchetti@example.test'],
            // Referenced by the Postman collection and the README examples, so
            // the documented requests work immediately after seeding.
            ['Test Customer',   'customer@example.test'],
            // Seeded with no orders, to exercise the empty-history response.
            ['Noah Lindqvist',  'noah.lindqvist@example.test'],
        ];

        foreach ($customers as [$name, $email]) {
            Customer::updateOrCreate(
                ['email' => mb_strtolower($email)],
                ['name' => $name],
            );
        }

        Customer::factory()->count(8)->create();
    }
}
