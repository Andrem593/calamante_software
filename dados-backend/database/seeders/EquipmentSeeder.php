<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $equipments = [
            ['model' => 'C30', 'capacity' => '130 FUNDAS'],
            ['model' => 'C12', 'capacity' => '50 FUNDAS'],
            ['model' => 'CF280', 'capacity' => '70 FUNDAS'],
            ['model' => 'ICB1', 'capacity' => '140 FUNDAS'],
            ['model' => 'ICB1 BIG', 'capacity' => '280 FUNDAS'],
            ['model' => '20A6', 'capacity' => '25 FUNDAS'],
            ['model' => 'CV250', 'capacity' => '25 FUNDAS'],
            ['model' => 'CR23CAF', 'capacity' => '70 FUNDAS'],
            ['model' => 'M450', 'capacity' => '180 FUNDAS'],
            ['model' => 'M650', 'capacity' => '270 FUNDAS'],
            ['model' => 'AD380', 'capacity' => '140 FUNDAS'],
            ['model' => 'M600', 'capacity' => '225 FUNDAS'],
            ['model' => 'M1000', 'capacity' => '400 FUNDAS'],
            ['model' => 'RCA', 'capacity' => '45 FUNDAS'],
            ['model' => 'GLOBAL', 'capacity' => '45 FUNDAS'],
            ['model' => 'OP100', 'capacity' => '50 FUNDAS'],
            ['model' => 'RC100', 'capacity' => '60 FUNDAS'],
            ['model' => 'METALFRIO', 'capacity' => '90 FUNDAS'],
        ];

        foreach ($equipments as $eq) {
            Equipment::updateOrCreate(
                ['model' => $eq['model']],
                ['capacity' => $eq['capacity']]
            );
        }
    }
}
