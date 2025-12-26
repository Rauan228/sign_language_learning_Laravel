<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostCommented extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $commenter, public $post, public $commentContent)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'comment',
            'sender_id' => $this->commenter->id,
            'sender_name' => $this->commenter->name,
            'sender_avatar' => $this->commenter->avatar,
            'post_id' => $this->post->id,
            'comment_content' => mb_substr($this->commentContent, 0, 50),
            'message' => 'прокомментировал(а) вашу запись',
        ];
    }
}
