<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Support\Price;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class GiftForm extends Form
{
    public ?int $giftId = null;

    public ?int $recipientId = null;

    public string $title = '';

    public string $price = '';

    public string $store = '';

    public string $url = '';

    public string $status = 'idea';

    public string $buyerId = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'price' => Price::rules(),
            'store' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'status' => ['required', Rule::enum(GiftStatus::class)],
            'buyerId' => ['nullable', Rule::exists('users', 'id')->where('household_id', Auth::user()?->household_id)],
        ];
    }

    public function fillFrom(Gift $gift): void
    {
        $this->giftId = $gift->id;
        $this->recipientId = $gift->recipient_id;
        $this->title = $gift->title;
        $this->price = Price::toDollars($gift->price_cents);
        $this->store = $gift->store ?? '';
        $this->url = $gift->url ?? '';
        $this->status = $gift->status->value;
        $this->buyerId = (string) ($gift->purchased_by_user_id ?? '');
    }

    /**
     * Normalised input for the gift Actions. Does not validate.
     *
     * @return array{title: string, price_cents: int|null, store: string|null, url: string|null, status: GiftStatus, purchased_by_user_id: int|null}
     */
    public function data(): array
    {
        return [
            'title' => trim($this->title),
            'price_cents' => Price::toCents(trim($this->price)),
            'store' => trim($this->store) ?: null,
            'url' => trim($this->url) ?: null,
            'status' => GiftStatus::from($this->status),
            'purchased_by_user_id' => $this->buyerId === '' ? null : (int) $this->buyerId,
        ];
    }

    public function resetForNext(): void
    {
        $recipientId = $this->recipientId;
        $this->reset();
        $this->recipientId = $recipientId;
    }
}
