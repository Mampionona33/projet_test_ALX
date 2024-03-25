<?php

namespace Controllers\EmailFormater;

use DateTime;
use PHPMailer\PHPMailer\PHPMailer;

class EmailFormater
{
    /**
     * @var PHPMailer
     */
    private $email;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $sender_email;

    /**
     * @var string|null
     */
    private $senderName;

    /**
     * @var string
     */
    private $devisDateRequest;

    public function __construct(string $sender_email, string $devisDateRequest)
    {
        $this->email = new PHPMailer();
        $this->sender_email = $sender_email;
        $this->devisDateRequest = $devisDateRequest;
        // -----------------------------

        $this->setupServer();
        $this->setupAttachments();
    }

    /**
     * Setter and getter
     */
    public function setDevisDateRequest(string $devisDateRequest): void
    {
        $this->devisDateRequest = $devisDateRequest;
    }
    public function getDevisDateRequest(): string
    {
        return $this->devisDateRequest;
    }
    public function setSenderName(string $senderName): void
    {
        $this->senderName = $senderName;
    }
    public function getSenderName(): string|null
    {
        return $this->senderName;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
    public function getPath(): string
    {
        return $this->path;
    }
    // -----------------------

    /**
     * Set up server
     * @throws \Exception
     * @return void
     */
    private function setupServer(): void
    {
        if (defined('SMTP')) {
            $this->email->IsSMTP();
            $this->email->Host = SMTP;
            $this->email->SMTPAuth = true;
            $this->email->Username = EMAIL;
            $this->email->Password = EMAIL_PWD;
            if ((defined('SMTP_SECURE')))
                $this->email->SMTPSecure = SMTP_SECURE;
            if ((defined('SMTP_PORT')))
                $this->email->Port = SMTP_PORT;
        } else {
            throw new \Exception('SMTP not defined');
        }

        $this->email->CharSet = 'UTF-8';
        $this->email->Encoding = 'base64';
        $this->email->Sender = $this->sender_email;
    }

    /**
     * Setup recipients
     * @param string $recipientEmail
     */
    public function setupRecipients(string $recipientEmail): void
    {
        $this->email->ClearReplyTos();
        $this->email->addReplyTo($this->sender_email, $this->senderName ?? '');
        $this->email->setFrom($this->sender_email, $this->senderName ?? '');
        $this->email->addAddress($recipientEmail);
    }

    /**
     * Attach mandat if present
     * @param string|null $mandatString
     * @param string|null $fileName
     * @return void
     * @throws \Exception
     */
    private function attachMandatIfPresent($mandatString, $fileName): void
    {
        if ($mandatString !== null && $fileName !== null) {
            $this->email->addStringAttachment($mandatString, $fileName);
        }
        if ($mandatString === null && $fileName === null) {
            throw new \Exception('No mandat attached');
        }
    }

    /**
     * Setup attachments
     */
    private function setupAttachments(): void
    {
        $devisDate = DateTime::createFromFormat("Y-m-d H:i:s", $this->devisDateRequest);
        $cutoffDate = DateTime::createFromFormat("d/m/Y H:i:s", "01/05/2010 00:00:00");

        if ($devisDate < $cutoffDate) {
        }
    }
}
