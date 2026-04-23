<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Share>
 */
final class ShareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->slug().'.bin';

        return [
            'user_id' => User::factory(),
            'original_name' => $name,
            'disk' => 'local',
            'path' => 'shares/'.Str::orderedUuid().'-'.$name,
            'size' => fake()->numberBetween(1024, 10_485_760),
            'mime_type' => 'application/octet-stream',
            'expires_at' => now()->addDays(7),
            'delete_after_first_download' => false,
            'first_downloaded_at' => null,
            'download_count' => 0,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function oneTime(): self
    {
        return $this->state(fn (): array => [
            'delete_after_first_download' => true,
            'expires_at' => null,
        ]);
    }
}
