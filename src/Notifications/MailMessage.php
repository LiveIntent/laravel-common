<?php

namespace LiveIntent\LaravelCommon\Notifications;

use Illuminate\Notifications\Messages\MailMessage as BaseMailMessage;

class MailMessage extends BaseMailMessage
{
    /**
     * The icon that should be rendered at the top of the email.
     *
     * @var string
     */
    public $icon = '';

    /**
     *
     */
    public function icon(string $icon)
    {
        //

        return $this;
    }
}
