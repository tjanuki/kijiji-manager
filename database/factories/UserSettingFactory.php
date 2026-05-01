<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSetting>
 */
class UserSettingFactory extends Factory
{
    protected $model = UserSetting::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'snippets' => [
                'pickup' => 'Pickup at front porch, Toronto',
                'payment' => 'Cash or e-transfer',
            ],
        ];
    }
}
