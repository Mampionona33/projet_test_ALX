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
    private $mandatStringAdministratifFinancier;

    /**
     * @var int
     */
    private $mandatStringAdministratif;

    /**
     * @var int
     */
    private $mandatStringFinancier;


    /**
     * @var int
     */
    private $mandatString;

    /**
     * @var string
     */
    private $devisDateRequest;

    /**
     * @var int|null
     */
    private $numMpr2;

    /**
     * @var string|null
     */
    private $attestationConsentementString;

    /**
     * @var string|null
     */
    private $procurationString;

    /**
     * @var string|null
     */
    private $lettreDevisString;

    /**
     * @var bool
     */
    private $isSubvention;

    /**
     * @var string|null
     */
    private $subventionString;

    /**
     * @var string|null
     */
    private $nomSubvention;


    public function __construct(string $sender_email, string $recipients, string $path)
    {
        $this->sender_email = $sender_email;
        $this->recipient_email = $recipients;
        $this->path = $path;
        $this->need_copy_mail = false;
        $this->real_sender_email = null;
        $this->mandatStringAdministratifFinancier = 0;
        $this->mandatStringAdministratif = 0;
        $this->mandatStringFinancier = 0;
        $this->mandatString = 0;
        $this->devisDateRequest = date('Y-m-d H:i:s');
        $this->numMpr2 = null;
        $this->attestationConsentementString = null;
        $this->procurationString = null;
        $this->lettreDevisString = null;
        $this->isSubvention = false;
        $this->subventionString = null;
        $this->nomSubvention = null;
    }

    public function setNomSubvention(string $sender_nom): void
    {
        $this->nomSubvention = $sender_nom;
    }

    public function getNomSubvention(): string|null
    {
        return $this->nomSubvention;
    }

    public function setSubventionString(string $subventionString): void
    {
        $this->subventionString = $subventionString;
    }

    public function getSubventionString(): string|null
    {
        return $this->subventionString;
    }

    public function setIsSubvention(bool $isSubvention): void
    {
        $this->isSubvention = $isSubvention;
    }

    public function getIsSubvention(): bool
    {
        return $this->isSubvention;
    }

    public function setLettreDevisString(string $lettreDevisString): void
    {
        $this->lettreDevisString = $lettreDevisString;
    }

    public function getLettreDevisString(): string|null
    {
        return $this->lettreDevisString;
    }

    public function setnumMpr2(int $numMpr2): void
    {
        $this->numMpr2 = $numMpr2;
    }

    public function setProcurationString(string $procurationString): void
    {
        $this->procurationString = $procurationString;
    }

    public function getProcurationString(): string|null
    {
        return $this->procurationString;
    }

    public function setAttestationConsentementString(string $attestationConsentementString): void
    {
        $this->attestationConsentementString = $attestationConsentementString;
    }

    public function getAttestationConsentementString(): string|null
    {
        return $this->attestationConsentementString;
    }

    public function setSender_email(string $sender_email): void
    {
        $this->sender_email = $sender_email;
    }

    public function getSender_email(): string
    {
        return $this->sender_email;
    }

    public function setmandatStringAdministratifFinancier(int $mandatStringAdministratifFinancier): void
    {
        $this->mandatStringAdministratifFinancier = $mandatStringAdministratifFinancier;
    }

    public function getmandatStringAdministratifFinancier(): int
    {
        return $this->mandatStringAdministratifFinancier;
    }

    public function setMandatString_administratif(int $mandatStringAdministratif): void
    {
        $this->mandatStringAdministratif = $mandatStringAdministratif;
    }

    public function getMandatString_administratif(): int
    {
        return $this->mandatStringAdministratif;
    }

    public function setMandatString_financier(int $mandatStringFinancier): void
    {
        $this->mandatStringFinancier = $mandatStringFinancier;
    }

    public function getMandatString_financier(): int
    {
        return $this->mandatStringFinancier;
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
        $this->email->addReplyTo($this->sender_email, $this->sender_nom ?? '');
        $this->email->setFrom($this->sender_email, $this->sender_nom ?? '');
        $this->email->addAddress($this->recipient_email);
    }

    private function shouldAddEmailBcc(): bool
    {
        return strpos($this->path, 'agir-ecologie') === false || strpos($this->path, 'leader-energie') === false;
    }

    private function shouldAddRealSenderEmailToBcc(): bool
    {
        if ($this->need_copy_mail && $this->real_sender_email && isset($_SESSION['user_power']) && ($_SESSION['user_power'] >= 50) && $this->real_sender_email !== null) {
            return true;
        }
        return false;
    }


    private function confgureHiddenRecipients(): void
    {
        if ($this->shouldAddEmailBcc()) {
            $this->email->AddBcc(EMAIL);
        }

        if ($this->shouldAddRealSenderEmailToBcc()) {
            $this->email->AddBcc($this->real_sender_email ?? '');
        }

        if (
            $this->mandatStringAdministratifFinancier === 0
            && $this->mandatStringAdministratif === 0
            && $this->mandatStringFinancier === 0
        ) {
            $this->mandatString = 0;
        } else {
            $this->mandatString = 1;
        }
    }

    private function shouldAttachCertificatPV(string $path): bool
    {
        return strpos($path, 'le.26770') !== false;
    }

    private function attachCertificatPV(string $path): void
    {
        $certificat_pv = $path . "/espace-rac/upload/certificats/certificatPV.pdf";
        $certificatString = file_get_contents($certificat_pv);
        $this->email->addStringAttachment($certificatString, 'Certificat PV.pdf');
    }

    private function attachAssuranceDecennale(string $path): void
    {
        $assurance_decennale = $path . "/espace-rac/upload/certificats/Assurance_decennale.pdf";
        $assurance_decennaleString = file_get_contents($assurance_decennale);
        $this->email->addStringAttachment($assurance_decennaleString, 'Assurance decennale.pdf');
    }


    private function configureAttachments(): void
    {
        $devisDate = DateTime::createFromFormat("Y-m-d H:i:s", $this->devisDateRequest);
        $cutoffDate = DateTime::createFromFormat("d/m/Y H:i:s", "01/05/2010 00:00:00");

        if ($devisDate < $cutoffDate) {
            $this->attachMandatIfPresent($this->mandatStringAdministratifFinancier, 'mandat-administratif-financier.pdf');
            $this->attachMandatIfPresent($this->mandatStringAdministratif, 'mandat-administratif.pdf');
            $this->attachMandatIfPresent($this->mandatStringFinancier, 'mandat-financier.pdf');
        } elseif ($this->numMpr2 !== null) {
            $this->attachMandatIfPresent($this->attestationConsentementString, 'Attestation_de_consentement.pdf');
            $this->attachMandatIfPresent($this->mandatStringAdministratifFinancier, 'mandat-administratif-financier.pdf');
            $this->attachMandatIfPresent($this->mandatStringAdministratif, 'mandat-administratif.pdf');
            $this->attachMandatIfPresent($this->mandatStringFinancier, 'mandat-financier.pdf');
        }

        $this->attachProcuringMandatIfPathContains('futurenv', $this->procurationString, 'Procuration.pdf');

        if ($this->isSubvention) {
            $this->attachProcuringMandatIfPathContains('ghe', $this->subventionString, 'lettre_fond_solidarite.pdf');
            $this->attachProcuringMandatIfPathContains('doovision', $this->subventionString, 'confirmation-des-aides.pdf');
            $this->attachProcuringMandatIfPathContains('efe', $this->subventionString, 'lettre_confirmation_devis.pdf');
            $this->attachSubventionIfPathDoesNotContain(['ghe', 'doovision', 'efe']);
        }
        if ($this->shouldAttachCertificatPV($this->path)) {
            $this->attachCertificatPV($this->path);
        }
    }

    /**
     * Undocumented function
     * @param array<string> $fileName
     * @return void
     */
    private function attachSubventionIfPathDoesNotContain(array $fileName): void
    {
        $found = false;
        foreach ($fileName as $key => $value) {
            if (strpos($this->path, $key) !== false) {
                $found = true;
                break;
            }
        }

        if (!$found && $this->subventionString !== null) {
            $this->email->addStringAttachment($this->subventionString, $this->nomSubvention . '.pdf');
        }
    }


    private function attachProcuringMandatIfPathContains(mixed $fileName, mixed $mandatString, mixed $attachmentName): void
    {
        if (strpos($this->path, $fileName) !== false && $mandatString !== null && $attachmentName !== null) {
            $this->attachMandatIfPresent($mandatString, $attachmentName);
        }
    }

    private function attachMandatIfPresent(mixed $mandatString, mixed $fileName): void
    {
        if ($mandatString !== null && $fileName !== null) {
            $this->email->addStringAttachment($mandatString, $fileName);
        }
    }

    private function configureEmail(): void
    {
        $this->email = new PHPMailer();

        try {
            $this->configureSettings();
            $this->configureRecipients();
            $this->confgureHiddenRecipients();
            $this->configureAttachments();
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
