<?php

class EmailSender
{
    /**
     * @var EmailFormater
     */
    private $emailFormater;

    public function __construct(EmailFormater $emailFormater)
    {
        $this->emailFormater = $emailFormater;
    }
}
