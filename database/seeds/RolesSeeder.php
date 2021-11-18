<?php

use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->prepareData() as $values) {
            \App\Models\Roles::query()->updateOrCreate(['name' => $values['name']], $values);
        }
    }
    protected function prepareData()
    {
        return [
            ['name' => 'owner'],
            ['name' => 'admin'],
            ['name' => 'manager'],
            ['name' => 'employee'],
        ];
    }
}
