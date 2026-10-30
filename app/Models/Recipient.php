<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecipientGroup;
use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property RecipientGroup $group
 * @property string $name
 * @property bool $is_shared
 * @property int|null $birthday_month
 * @property int|null $birthday_day
 * @property int|null $birthday_year
 * @property string|null $interests
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household $household
 */
class Recipient extends Model
{
    /** @use HasFactory<RecipientFactory> */
    use HasFactory;

    protected $fillable = [
        'group',
        'name',
        'is_shared',
        'birthday_month',
        'birthday_day',
        'birthday_year',
        'interests',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => RecipientGroup::class,
            'is_shared' => 'boolean',
            'birthday_month' => 'integer',
            'birthday_day' => 'integer',
            'birthday_year' => 'integer',
            'archived_at' => 'datetime',
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
     * @return BelongsToMany<Occasion, $this>
     */
    public function occasions(): BelongsToMany
    {
        return $this->belongsToMany(Occasion::class)->withPivot('budget_cents')->withTimestamps();
    }

    public function birthdayLabel(): ?string
    {
        if ($this->birthday_month === null || $this->birthday_day === null) {
            return null;
        }

        $label = Carbon::create(2000, $this->birthday_month, $this->birthday_day)->format('M j');

        return $this->birthday_year === null ? $label : $label.', '.$this->birthday_year;
    }

    public function age(): ?int
    {
        if ($this->birthday_year === null || $this->birthday_month === null || $this->birthday_day === null) {
            return null;
        }

        return (int) Carbon::create($this->birthday_year, $this->birthday_month, $this->birthday_day)->diffInYears(now());
    }
}
