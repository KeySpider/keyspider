<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for ($i = 0; $i < 1000000; $i++) {
            $data = [
                '001' => str_random(10),
                '002' => str_random(10).'@gmail.com',
                '003' => str_random(10),
                '004' => str_random(10),
                '005' => str_random(10).'@gmail.com',
                '006' => str_random(10),
                '007' => str_random(10),
                '008' => str_random(10),
            ];

            DB::table('tests')->insert($data);
        }
    }
}
