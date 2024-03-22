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
     * @var string|null
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

    /**
     * @var bool
     */
    private $need_copy_mail;

    /**
     * @var string|null
     */
    private $real_sender_email;

    /**
     * @var int
     */
    private $mandatString_administratif_financier;

    /**
     * @var int
     */
    private $mandatString_administratif;

    /**
     * @var int
     */
    private $mandatString_financier;


    /**
     * @var int
     */
    private $mandatString;

    public function __construct(string $sender_email, string $recipients, string $path)
    {
        $this->sender_email = $sender_email;
        $this->recipient_email = $recipients;
        $this->path = $path;
        $this->need_copy_mail = false;
        $this->real_sender_email = null;
        $this->mandatString_administratif_financier = 0;
        $this->mandatString_administratif = 0;
        $this->mandatString_financier = 0;
        $this->mandatString = 0;
    }

    public function setMandatString_administratif_financier(int $mandatString_administratif_financier): void
    {
        $this->mandatString_administratif_financier = $mandatString_administratif_financier;
    }

    public function getMandatString_administratif_financier(): int
    {
        return $this->mandatString_administratif_financier;
    }

    public function setMandatString_administratif(int $mandatString_administratif): void
    {
        $this->mandatString_administratif = $mandatString_administratif;
    }

    public function getMandatString_administratif(): int
    {
        return $this->mandatString_administratif;
    }

    public function setMandatString_financier(int $mandatString_financier): void
    {
        $this->mandatString_financier = $mandatString_financier;
    }

    public function getMandatString_financier(): int
    {
        return $this->mandatString_financier;
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

        if ($this->need_copy_mail && $this->real_sender_email && isset($_SESSION['user_power']) && ($_SESSION['user_power'] >= 50)) {
            $this->email->AddBcc($this->real_sender_email);
        }

        if (
            $this->mandatString_administratif_financier === 0
            && $this->mandatString_administratif === 0
            && $this->mandatString_financier === 0
        ) {
            $this->mandatString = 0;
        } else {
            $this->mandatString = 1;
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

    public function send(string $sender_nom, bool $need_copy_mail): void
    {
        $this->sender_nom = $sender_nom;
        $this->need_copy_mail = $need_copy_mail;
        $this->configureEmail();
        $this->email->send();
    }
}
