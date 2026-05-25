<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $inviterName,
        private string $orgName,
        private string $resetUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been invited to join {$this->orgName} on Social Media Poster")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$this->inviterName} has invited you to join **{$this->orgName}** on Social Media Poster.")
            ->line('Your account has been created. Click the button below to set your password and get started.')
            ->action('Accept Invitation & Set Password', $this->resetUrl)
            ->line('This invitation link expires in 60 minutes.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
