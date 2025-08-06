<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductsTableSeeder extends Seeder
{
    public function run()
    {
        $products = [
            [
                'name' => 'Kamera Canon EOS 1500D',
                'description' => 'Kamera DSLR dengan kualitas tinggi untuk pemula',
                'price' => 4500000,
                'stock' => 10,
                'image' => 'https://via.placeholder.com/150'
            ],
            [
                'name' => 'Tripod Profesional',
                'description' => 'Tripod ringan dan kuat untuk semua kamera',
                'price' => 350000,
                'stock' => 20,
                'image' => 'https://via.placeholder.com/150'
            ],
            [
                'name' => 'Lensa Fix 50mm',
                'description' => 'Lensa bokeh terbaik untuk portrait',
                'price' => 1250000,
                'stock' => 15,
                'image' => 'https://via.placeholder.com/150'
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
