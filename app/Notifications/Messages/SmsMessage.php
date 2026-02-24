<?php

namespace App\Notifications\Messages;

/**
 * Data Transfer Object for SMS messages.
 *
 * Provides a fluent API for building SMS messages with
 * recipient, content, and sender information.
 */
class SmsMessage
{
    /**
     * The recipient phone number.
     */
    protected ?string $to = null;

    /**
     * The message content.
     */
    protected ?string $content = null;

    /**
     * The sender name/number.
     */
    protected ?string $from = null;

    /**
     * Set the recipient phone number.
     *
     * @param  string  $to  The recipient phone number
     * @return $this
     */
    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Set the message content.
     *
     * @param  string  $content  The message content
     * @return $this
     */
    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the sender name/number.
     *
     * @param  string  $from  The sender name or number
     * @return $this
     */
    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Get the recipient phone number.
     */
    public function getTo(): ?string
    {
        return $this->to;
    }

    /**
     * Get the message content.
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Get the sender name/number.
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }
}
