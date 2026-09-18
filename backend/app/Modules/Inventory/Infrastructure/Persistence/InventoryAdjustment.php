<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['actor_user_id', 'reason', 'occurred_at'])]
final class InventoryAdjustment extends Model
{
    public const CREATED_AT = 'occurred_at';

    public const UPDATED_AT = null;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(static function (self $adjustment): never {
            throw new LogicException('Inventory adjustments are append-only.');
        });

        self::deleting(static function (self $adjustment): never {
            throw new LogicException('Inventory adjustments are append-only.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
