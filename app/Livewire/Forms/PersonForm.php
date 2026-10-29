<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Enums\RecipientGroup;
use App\Models\Recipient;
use App\Rules\ValidBirthday;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PersonForm extends Form
{
    public ?int $recipientId = null;

    public string $name = '';

    public string $group = 'family';

    public bool $is_shared = false;

    // Strings, not ?int: a blank select or cleared number input sends ''.
    public string $birthday_month = '';

    public string $birthday_day = '';

    public string $birthday_year = '';

    public string $interests = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'group' => ['required', Rule::enum(RecipientGroup::class)],
            'is_shared' => ['boolean'],
            'birthday_month' => ['nullable', 'integer', new ValidBirthday],
            'birthday_day' => ['nullable', 'integer'],
            'birthday_year' => ['nullable', 'integer'],
            'interests' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function fillFrom(Recipient $recipient): void
    {
        $this->recipientId = $recipient->id;
        $this->name = $recipient->name;
        $this->group = $recipient->group->value;
        $this->is_shared = $recipient->is_shared;
        $this->birthday_month = (string) $recipient->birthday_month;
        $this->birthday_day = (string) $recipient->birthday_day;
        $this->birthday_year = (string) $recipient->birthday_year;
        $this->interests = $recipient->interests ?? '';
    }

    /**
     * Normalised input for CreatePerson/UpdatePerson. Does not validate.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'name' => $this->name,
            'group' => $this->group,
            'is_shared' => $this->is_shared,
            'birthday_month' => $this->toInt($this->birthday_month),
            'birthday_day' => $this->toInt($this->birthday_day),
            'birthday_year' => $this->toInt($this->birthday_year),
            'interests' => $this->interests === '' ? null : $this->interests,
        ];
    }

    public function resetForNext(): void
    {
        $group = $this->group;
        $this->reset();
        $this->group = $group;
    }

    private function toInt(string $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
