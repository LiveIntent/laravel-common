<?php

namespace LiveIntent\LaravelCommon\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Channels\MailChannel as BaseMailChannel;

class MailChannel extends BaseMailChannel
{
    // /**
    //  * Send the given notification.
    //  *
    //  * @param  mixed  $notifiable
    //  * @param  \Illuminate\Notifications\Notification  $notification
    //  * @return void
    //  */
    // public function send($notifiable, Notification $notification)
    // {
    //     // $message = $notification->toMail($notifiable);

    //     // dd($message);
    //     // Send notification to the $notifiable instance...
    // }
}
