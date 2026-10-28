<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * A realistic catalogue.
     *
     * Hand-written rather than purely random so the data makes sense on screen
     * and deliberately spans the interesting cases: zero stock, stock exactly
     * at the default threshold of 10, stock just below it, healthy stock, zero
     * tax, and several different tax bands.
     */
    public function run(): void
    {
        $products = [
            // name, code, price (INR), tax_rate, stock
            ['Wireless Optical Mouse',        'SKU-MOU-1001', '2399.00',   '18.00', 142],
            ['Mechanical Keyboard (87-key)',  'SKU-KEY-1002', '8599.00',   '18.00', 64],
            ['27-inch 4K Monitor',            'SKU-MON-1003', '33499.00',  '18.00', 23],
            ['USB-C Docking Station',         'SKU-DOC-1004', '12499.00',  '18.00', 8],
            ['Noise Cancelling Headphones',   'SKU-AUD-1005', '19199.00',  '18.00', 10],
            ['1080p Webcam',                  'SKU-CAM-1006', '5699.00',   '18.00', 0],
            ['Laptop Stand (Aluminium)',      'SKU-STD-1007', '4249.00',   '12.00', 87],
            ['External SSD 1TB',              'SKU-SSD-1008', '10799.00',  '12.00', 5],
            ['HDMI Cable 2m',                 'SKU-CAB-1009', '949.00',    '12.00', 310],
            ['Surge Protected Power Strip',   'SKU-PWR-1010', '1899.00',   '12.00', 3],
            ['Ergonomic Office Chair',        'SKU-CHR-1011', '26799.00',  '18.00', 17],
            ['Adjustable Standing Desk',      'SKU-DSK-1012', '47899.00',  '18.00', 6],
            ['Printer Paper A4 (500 sheets)', 'SKU-PAP-1013', '719.00',    '5.00',  520],
            ['Whiteboard Marker Set',         'SKU-MRK-1014', '1179.00',   '5.00',  95],
            ['Document Binder Clips',         'SKU-CLP-1015', '479.00',    '5.00',  12],
            // Zero-rated items exercise the tax_rate = 0 path end to end.
            ['Printed Safety Handbook',       'SKU-BOK-1016', '1439.00',   '0.00',  44],
            ['First Aid Kit (Workplace)',     'SKU-MED-1017', '3699.00',   '0.00',  9],
            ['Fire Extinguisher 2kg',         'SKU-FIR-1018', '6149.00',   '0.00',  2],
        ];

        foreach ($products as [$name, $code, $price, $taxRate, $stock]) {
            // updateOrCreate keyed on the unique code makes the seeder safe to
            // run repeatedly without colliding with the unique constraint.
            Product::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'price' => $price,
                    'tax_rate' => $taxRate,
                    'stock_quantity' => $stock,
                ],
            );
        }

        // A few extra randomised products so list screens have enough rows to
        // demonstrate pagination.
        Product::factory()->count(12)->create();
    }
}
