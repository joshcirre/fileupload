<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Override;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property int $size
 * @property string|null $mime_type
 * @property Carbon|null $expires_at
 * @property bool $delete_after_first_download
 * @property Carbon|null $first_downloaded_at
 * @property int $download_count
 */
final class Share extends Model
{
    /** @use HasFactory<\Database\Factories\ShareFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->delete_after_first_download && $this->first_downloaded_at !== null;
    }

    public function isAvailable(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeReapable(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('expires_at', '<=', now())
            ->orWhere(fn (Builder $inner) => $inner
                ->where('delete_after_first_download', true)
                ->whereNotNull('first_downloaded_at')
            )
        );
    }

    public function deleteFileAndRecord(): void
    {
        Storage::disk($this->disk)->delete($this->path);
        $this->delete();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'first_downloaded_at' => 'datetime',
            'delete_after_first_download' => 'bool',
            'size' => 'int',
            'download_count' => 'int',
        ];
    }
}
