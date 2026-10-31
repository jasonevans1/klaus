<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GiftStatus;
use Database\Factories\GiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property int $recipient_id
 * @property int|null $occasion_id
 * @property string $title
 * @property GiftStatus $status
 * @property int|null $purchased_by_user_id
 * @property int|null $price_cents
 * @property string|null $store
 * @property string|null $url
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household $household
 * @property-read Recipient $recipient
 * @property-read Occasion|null $occasion
 * @property-read User|null $purchasedBy
 */
class Gift extends Model
{
    /** @use HasFactory<GiftFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'idea'];

    protected $fillable = [
        'title',
        'price_cents',
        'store',
        'url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GiftStatus::class,
            'price_cents' => 'integer',
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
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * @return BelongsTo<Occasion, $this>
     */
    public function occasion(): BelongsTo
    {
        return $this->belongsTo(Occasion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by_user_id');
    }
}
