<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OccasionType;
use Database\Factories\OccasionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property OccasionType $type
 * @property string $name
 * @property Carbon $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household $household
 */
class Occasion extends Model
{
    /** @use HasFactory<OccasionFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OccasionType::class,
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return BelongsToMany<Recipient, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class)->withPivot('budget_cents')->withTimestamps();
    }
}
