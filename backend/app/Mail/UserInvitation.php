<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  User    $user       the invited account
     * @param  string  $inviteUrl  SPA set-password link (carries the token)
     */
    public function __construct(
        public User $user,
        public string $inviteUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to '.config('app.name'),
        );
    }

    public function content(): Content
    {
        // $user and $inviteUrl are public props, so they're available in the view
        return new Content(view: 'emails.invitation');
    }
}
