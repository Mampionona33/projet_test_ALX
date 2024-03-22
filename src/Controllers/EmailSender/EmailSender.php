<?php

use Controllers\EmailSender\AbstractEmailSender;
use PHPMailer\PHPMailer\PHPMailer;

class EmailSender extends AbstractEmailSender
{
    /**
     * @var PHPMailer
     */
    private $email;

    /**
     * @var string
     */
    private $sender_email;

    /**
     * @var string
     */
    private $sender_nom;

    /**
     * @var string
     */
    private $recipient_email;

    /**
     * @var string
     */
    private $path;


    public function __construct(string $sender_email, string $recipients, string $path)
    {
        $this->sender_email = $sender_email;
        $this->recipient_email = $recipients;
        $this->path = $path;
    }

    private function configureSettings(): void
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
        }

        $this->email->CharSet = 'UTF-8';
        $this->email->Encoding = 'base64';
        $this->email->Sender = $this->sender_email;
    }

    private function configureRecipients(): void
    {
        $this->email->ClearReplyTos();
        $this->email->addReplyTo($this->sender_email, $this->sender_nom);
        $this->email->setFrom($this->sender_email, $this->sender_nom);
        $this->email->addAddress($this->recipient_email);
    }

    private function confgureHiddenRecipients(): void
    {
        if (strpos($this->path, 'leader-energie') === false || strpos($this->path, 'agir-ecologie') === false) {
            $this->email->AddBcc(EMAIL);
        }

        if ($need_copy_mail && isset($_SESSION['user_power']) && ($_SESSION['user_power'] >= 50)) {
            $this->email->AddBcc($real_sender_email);
        }

        if ($mandatString_administratif_financier == 0 && $mandatString_administratif == 0 && $mandatString_financier == 0) {
            $mandatString = 0;
        } else {
            $mandatString = 1;
        }
    }


    private function configureEmail(): void
    {
        $this->email = new PHPMailer();

        try {
            $this->configureSettings();
            $this->configureRecipients();
            $this->confgureHiddenRecipients();
            //code...
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    public function send(string $sender_nom): void
    {
        $this->sender_nom = $sender_nom;
        $this->configureEmail();
        $this->email->send();
    }
}
