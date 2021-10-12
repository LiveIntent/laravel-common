<?php

namespace LiveIntent\LaravelCommon\Notifications;

class MailMessage
{
    /**
     * The "from" information for the message.
     *
     * @var array
     */
    public $from = [];

    /**
     * The "reply to" information for the message.
     *
     * @var array
     */
    public $replyTo = [];

    /**
     * The "cc" information for the message.
     *
     * @var array
     */
    public $cc = [];

    /**
     * The "bcc" information for the message.
     *
     * @var array
     */
    public $bcc = [];

    /**
     * The subject of the notification.
     *
     * @var string
     */
    public $subject;

    /**
     * The title of the notification.
     *
     * @var string
     */
    public $title;

    /**
     * The greeting of the notification.
     *
     * @var string
     */
    public $greeting;

    /**
     * The salutation of the notification.
     *
     * @var string
     */
    public $salutation;

    /**
     * The attachments for the message.
     *
     * @var array
     */
    public $attachments = [];

    /**
     * The raw attachments for the message.
     *
     * @var array
     */
    public $rawAttachments = [];

    /**
     * Priority level of the message.
     *
     * @var int
     */
    public $priority;

    /**
     * The icon that should be rendered at the top of the email.
     *
     * @var string
     */
    public $icon = '';

    /**
     * The json parts of the message.
     *
     * @var array
     */
    public $parts = [];

    /**
     * Set the title of the message.
     *
     * @return static
     */
    public function title($title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the subject of the message.
     *
     * @return static
     */
    public function subject($subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the icon of the message.
     *
     * @return static
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the greeting of the message.
     *
     * @return static
     */
    public function greeting($greeting): static
    {
        $this->greeting = $greeting;

        return $this;
    }

    /**
     * Set the salutation of the message.
     *
     * @return static
     */
    public function salutation($salutation): static
    {
        $this->salutation = $salutation;

        return $this;
    }

    /**
     * Add a line of text to the body of the message.
     *
     * @return static
     */
    public function line($text): static
    {
        $this->parts[] = [
            'type' => 'text',
            'data' => [
                'body' => $text,
            ],
        ];

        return $this;
    }

    /**
     * Add a call to action to the body of the message.
     *
     * @return static
     */
    public function action($title, $url): static
    {
        return $this->actionButton($title, $url);
    }

    /**
     * Add a call to action button to the body of the message.
     *
     * @return static
     */
    public function actionButton($title, $url): static
    {
        $this->parts[] = [
            'type' => 'cta-button',
            'data' => [
                'body' => $title,
                'action' => $url,
            ],
        ];

        return $this;
    }

    /**
     * Add a call to action link to the body of the message.
     *
     * @return static
     */
    public function actionLink(string $title, string $url): static
    {
        $this->parts[] = [
            'type' => 'cta-link',
            'data' => [
                'body' => $title,
                'action' => $url,
            ],
        ];

        return $this;
    }

    /**
     * Add a colored line of text that represents a status, like a warning or success.
     *
     * @return static
     */
    public function statusLine(string $status, string $text): static
    {
        $this->parts[] = [
            'type' => 'status-line',
            'data' => [
                'status' => $status,
                'body' => $text,
            ],
        ];

        return $this;
    }

    /**
     * Add a table of data to the body of the message.
     *
     * @return static
     */
    public function infoTable($title, $rows): static
    {
        $this->parts[] = [
            'type' => 'into-table',
            'data' => [
                'title' => $title,
                'rows' => $rows,
            ],
        ];

        return $this;
    }
}
