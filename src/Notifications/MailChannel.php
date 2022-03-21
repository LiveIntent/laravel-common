<?php

namespace LiveIntent\LaravelCommon\Notifications;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Channels\MailChannel as BaseMailChannel;

class MailChannel extends BaseMailChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toMail($notifiable);

        $normaniMessage = $this->buildNormaniMessage($notifiable, $notification, $message);

        $this->sendNormaniMessage($normaniMessage);
    }

    /**
     * Build the normani message.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @param mixed $message
     * @return array
     */
    protected function buildNormaniMessage($notifiable, $notification, $message)
    {
        $recipients = $this->getRecipients($notifiable, $notification, $message);

        $title = ($message->title ?: Str::title(
            Str::snake(Str::before(class_basename($notification), 'Notification'), ' ')
        ));

        return [
            'to' => array_map(fn ($a) => ['email' => $a], $recipients),
            'subject' => $message->subject ?: $title,
            'channels' => ['email'],
            'message' => [
                'json' => [
                    'icon' => $message->icon,
                    'title' => $title,
                    'greeting' => $message->greeting,
                    'salutation' => Arr::wrap($message->salutation),
                    'parts' => $message->parts,
                ],
            ],
        ];
    }

    /**
     * Send a message through the Normani service.
     *
     * @return \Illuminate\Http\Client\Response
     */
    protected function sendNormaniMessage(array $payload): \Illuminate\Http\Client\Response
    {
        $url = config('liveintent.services.notifications.url').'/api/notifications';

        return Http::post($url, $payload)->throw();
    }
}
