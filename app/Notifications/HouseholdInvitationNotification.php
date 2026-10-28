<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\HouseholdInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HouseholdInvitationNotification extends Notification
{
    public function __construct(public HouseholdInvitation $invitation, public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->inviter->name ?? 'Someone';

        return (new MailMessage)
            ->subject("{$inviter} invited you to Klaus")
            ->line("{$inviter} invited you to join {$this->invitation->household->name} on Klaus.")
            ->action('Join household', url('/invitations/'.$this->token))
            ->line('This invitation expires in 7 days.');
    }
}
