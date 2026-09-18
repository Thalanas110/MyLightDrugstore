<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'medicine_id',
    'inventory_lot_id',
    'actor_user_id',
    'movement_type',
    'quantity_delta',
    'reason',
    'source_type',
    'source_id',
    'occurred_at',
])]
final class InventoryMovement extends Model
{
    public const CREATED_AT = 'occurred_at';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (self $movement): never {
            throw new LogicException('Inventory movements are append-only.');
        });

        self::deleting(static function (self $movement): never {
            throw new LogicException('Inventory movements are append-only.');
        });
    }

    /** @return BelongsTo<Medicine, $this> */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    /** @return BelongsTo<InventoryLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
