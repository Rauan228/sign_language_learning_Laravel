<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostLiked extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $liker, public $post)
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
            'type' => 'like',
            'sender_id' => $this->liker->id,
            'sender_name' => $this->liker->name,
            'sender_avatar' => $this->liker->avatar,
            'post_id' => $this->post->id,
            'post_content' => substr($this->post->content ?? $this->post->title, 0, 50),
            'message' => 'liked your post',
        ];
    }
}
