<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop setting the owner controls.
 *
 * @property int|null $branch_id
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['branch_id', 'key', 'value', 'updated_by'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * A branch setting falls back to the group setting, which falls back to
     * the value in config. So a shop that has never touched a setting still
     * behaves sensibly.
     */
    public static function get(string $key, mixed $default = null, ?int $branchId = null): mixed
    {
        $rows = self::query()
            ->where('key', $key)
            ->where(fn ($query) => $query->whereNull('branch_id')
                ->when($branchId !== null, fn ($q) => $q->orWhere('branch_id', $branchId)))
            ->get();

        $branchRow = $branchId === null
            ? null
            : $rows->firstWhere('branch_id', $branchId);

        $groupRow = $rows->firstWhere('branch_id', null);

        $row = $branchRow ?? $groupRow;

        return $row === null ? $default : ($row->value['v'] ?? $default);
    }

    public static function put(string $key, mixed $value, ?int $branchId = null, ?int $byUserId = null): void
    {
        self::updateOrCreate(
            ['branch_id' => $branchId, 'key' => $key],
            // Wrapped so a scalar can live in a json column on every driver.
            ['value' => ['v' => $value], 'updated_by' => $byUserId],
        );
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
