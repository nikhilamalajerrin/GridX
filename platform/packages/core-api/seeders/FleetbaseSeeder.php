<?php

namespace GridX\Seeders;

use Illuminate\Database\Seeder;

class GridXSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(ExtensionSeeder::class);
        $this->call(PermissionSeeder::class);
    }
}
