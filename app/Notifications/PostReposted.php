<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostReposted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $reposter, public $post)
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
            'type' => 'repost',
            'sender_id' => $this->reposter->id,
            'sender_name' => $this->reposter->name,
            'sender_avatar' => $this->reposter->avatar,
            'post_id' => $this->post->id,
            'post_content' => mb_substr($this->post->content ?? $this->post->title, 0, 50),
            'message' => 'сделал(а) репост вашей записи',
        ];
    }
}
