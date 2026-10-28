<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class InviteForm extends Form
{
    #[Validate('required|email|max:255')]
    public string $email = '';
}
