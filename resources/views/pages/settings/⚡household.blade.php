<?php

use App\Actions\Households\RenameHousehold;
use App\Actions\Households\ResendHouseholdInvitation;
use App\Actions\Households\RevokeHouseholdInvitation;
use App\Actions\Households\SendHouseholdInvitation;
use App\Livewire\Forms\InviteForm;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

new #[Title('Household settings')] class extends Component {
    public InviteForm $form;

    public string $householdName = '';

    public function mount(): void
    {
        $this->householdName = Auth::user()->household->name;
    }

    public function rename(RenameHousehold $renameHousehold): void
    {
        try {
            $renameHousehold->handle(Auth::user(), $this->householdName);
        } catch (ValidationException $e) {
            $this->addError('householdName', $e->validator->errors()->first('name'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Household renamed.'));
    }

    public function invite(SendHouseholdInvitation $sendInvitation): void
    {
        $this->form->validate();

        try {
            $sendInvitation->handle(Auth::user(), $this->form->email);
        } catch (ValidationException $e) {
            $this->addError('form.email', $e->validator->errors()->first('email'));

            return;
        } catch (TransportExceptionInterface) {
            $this->addError('form.email', __("Couldn't send the invite email. Try again."));

            return;
        }

        Flux::toast(variant: 'success', text: __('Invite sent to :email.', ['email' => $this->form->email]));

        $this->form->reset();
        unset($this->invitations);
    }

    public function revoke(RevokeHouseholdInvitation $revokeInvitation, int $invitationId): void
    {
        $revokeInvitation->handle(Auth::user(), $invitationId);

        unset($this->invitations);
        Flux::toast(variant: 'success', text: __('Invite revoked.'));
    }

    public function resend(ResendHouseholdInvitation $resendInvitation, int $invitationId): void
    {
        try {
            $invitation = $resendInvitation->handle(Auth::user(), $invitationId);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first('email'));

            return;
        } catch (TransportExceptionInterface) {
            Flux::toast(variant: 'danger', text: __("Couldn't send the invite email. Try again."));

            return;
        }

        unset($this->invitations);
        Flux::toast(variant: 'success', text: __('Invite re-sent to :email.', ['email' => $invitation->email]));
    }

    #[Computed]
    public function members()
    {
        return Auth::user()->household->users()->orderBy('name')->get();
    }

    #[Computed]
    public function invitations()
    {
        return Auth::user()->household->pendingInvitations()->orderBy('expires_at')->get();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Household settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Household')" :subheading="__('Manage who shares your household')">
        <form wire:submit="rename" class="mb-8 space-y-4">
            <flux:input wire:model="householdName" :label="__('Household name')" type="text" required />
            <flux:button variant="primary" type="submit" data-test="rename-household-button">{{ __('Save') }}</flux:button>
        </form>

        <div class="space-y-2">
            @foreach ($this->members as $member)
                <div>
                    <flux:heading>{{ $member->name }}</flux:heading>
                    <flux:text>{{ $member->email }}</flux:text>
                </div>
            @endforeach
        </div>

        <form wire:submit="invite" class="mt-8 space-y-4">
            <flux:input wire:model="form.email" :label="__('Invite by email')" type="email" required autocomplete="off" />
            <flux:button variant="primary" type="submit" data-test="invite-button">{{ __('Send invite') }}</flux:button>
        </form>

        @if ($this->invitations->isNotEmpty())
            <flux:heading class="mt-8">{{ __('Pending invites') }}</flux:heading>
            <div class="mt-2 space-y-2">
                @foreach ($this->invitations as $invitation)
                    <div wire:key="invitation-{{ $invitation->id }}" class="flex items-center justify-between gap-4">
                        <div>
                            <flux:text>{{ $invitation->email }}</flux:text>
                            <flux:text size="sm">{{ __('Expires :date', ['date' => $invitation->expires_at->format('M j, Y')]) }}</flux:text>
                        </div>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="resend({{ $invitation->id }})" data-test="resend-invite-button">{{ __('Re-send') }}</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="revoke({{ $invitation->id }})" wire:confirm="{{ __('Revoke this invite?') }}" data-test="revoke-invite-button">{{ __('Revoke') }}</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-pages::settings.layout>
</section>
