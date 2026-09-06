<?php

namespace Database\Factories;

use App\Enums\GoRegion;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserSetting> */
class UserSettingFactory extends Factory
{
    protected $model = UserSetting::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'go_region' => GoRegion::Europa,
            'count_regional_in_total' => false,
            'count_shiny_in_total' => false,
            'theme' => 'default',
        ];
    }
}
