<?php

namespace GridX\Storefront\Seeders\Testing;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        Schema::connection(config('gridx.connection.db'))->disableForeignKeyConstraints();
        Schema::connection(config('storefront.connection.db'))->disableForeignKeyConstraints();
        try {
            $this->purgeSeedData();
        } finally {
            Schema::connection(config('storefront.connection.db'))->enableForeignKeyConstraints();
            Schema::connection(config('gridx.connection.db'))->enableForeignKeyConstraints();
        }

        $this->call([
            CatalogAndProductsSeeder::class,
            CheckoutOrdersSeeder::class,
        ]);
    }

    protected function purgeSeedData(): void
    {
        foreach ([
            new CheckoutOrdersSeeder(),
            new CatalogAndProductsSeeder(),
        ] as $seeder) {
            $seeder->purgeSeedData();
        }
    }
}
