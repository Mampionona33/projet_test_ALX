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

    /**
     * @var string|null
     */
    private $mandatStringAdministratifFinancier;

    /**
     * @var string|null
     */
    private $mandatStringAdministratif;


    /**
     * @var string|null
     */
    private $mandatStringFinancier;

    /**
     * @var string|null
     */
    private $attestationConsentementString;

    /**
     * @var string|null
     */
    private $numMpr2;

    /**
     * @var string|null
     */
    private $procurationString;

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
    private $pdfDocumentMprString;

    /**
     * @var string|null
     */
    private $nomDocumentMpr;

    /**
     * @var string|null
     */
    private $amoString;

    /**
     * @var string|null
     */
    private $refusamoString;

    /**
     * @var string|null
     */
    private $mandatSpecialString;

    /**
     * @var string|null
     */
    private $mandat1Asc2String;


    /**
     * @var string|null
     */
    private $mandat2Asc2String;

    /**
     * @var string|null
     */
    private $nomSubvention;

    /**
     * @var string|null
     */
    private $lettreEngagementString;

    /**
     * @var string|null
     */
    private $tvaString;


    /**
     * @var string|null
     */
    private $ccString;

    /**
     * @var string|null
     */
    private $mandatSibel1String;

    /**
     * @var string|null
     */
    private $mandatSibel2String;

    /**
     * @var string|null
     */
    private $mandatSibel3String;

    /**
     * @var string|null
     */
    private $docLeaderString;

    /**
     * @var string|null
     */
    private $pdfString;

    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private $ficheTechs;

    /**
     * Constructor
     * @param string $sender_email
     * @param string $devisDateRequest
     */
    public function __construct(string $sender_email, string $devisDateRequest)
    {
        $this->email = new PHPMailer();
        $this->sender_email = $sender_email;
        $this->devisDateRequest = $devisDateRequest;
        $this->isSubvention = false;
        // -----------------------------

        $this->setupServer();
    }

    /**
     * Setter and getter
     */
    public function setDocLeaderString(string|null $docLeaderString): void
    {
        $this->docLeaderString = $docLeaderString;
    }
    public function getDocLeaderString(): string|null
    {
        return $this->docLeaderString;
    }

    /**
     * @param array<int|string, array<string, mixed>>|null $ficheTechs
     */
    public function setFicheTechs(array|null $ficheTechs): void
    {
        $this->ficheTechs = $ficheTechs;
    }
    /**
     * @return array<int|string, array<string, mixed>>|null
     */
    public function getFicheTechs()
    {
        return $this->ficheTechs;
    }

    public function setPdfString(string|null $pdfString): void
    {
        $this->pdfString = $pdfString;
    }
    public function getPdfString(): string|null
    {
        return $this->pdfString;
    }

    public function setMandatSibel1String(string|null $mandatSibel1String): void
    {
        $this->mandatSibel1String = $mandatSibel1String;
    }
    public function getMandatSibel1String(): string|null
    {
        return $this->mandatSibel1String;
    }

    public function setMandatSibel2String(string|null $mandatSibel2String): void
    {
        $this->mandatSibel2String = $mandatSibel2String;
    }
    public function getMandatSibel2String(): string|null
    {
        return $this->mandatSibel2String;
    }

    public function setMandatSibel3String(string|null $mandatSibel3String): void
    {
        $this->mandatSibel3String = $mandatSibel3String;
    }
    public function getMandatSibel3String(): string|null
    {
        return $this->mandatSibel3String;
    }

    public function setCcString(string|null $ccString): void
    {
        $this->ccString = $ccString;
    }
    public function getCcString(): string|null
    {
        return $this->ccString;
    }

    public function setTvaString(string|null $tvaString): void
    {
        $this->tvaString = $tvaString;
    }
    public function getTvaString(): string|null
    {
        return $this->tvaString;
    }

    public function setLettreEngagementString(string|null $lettreEngagementString): void
    {
        $this->lettreEngagementString = $lettreEngagementString;
    }
    public function getLettreEngagementString(): string|null
    {
        return $this->lettreEngagementString;
    }

    public function setNomSubvention(string|null $nomSubvention): void
    {
        $this->nomSubvention = $nomSubvention;
    }
    public function getNomSubvention(): string|null
    {
        return $this->nomSubvention;
    }


    public function setMandat2Asc2String(string|null $mandat2Asc2String): void
    {
        $this->mandat2Asc2String = $mandat2Asc2String;
    }
    public function getMandat2Asc2String(): string|null
    {
        return $this->mandat2Asc2String;
    }

    public function setMandat1Asc2String(string|null $mandat1Asc2String): void
    {
        $this->mandat1Asc2String = $mandat1Asc2String;
    }
    public function getMandat1Asc2String(): string|null
    {
        return $this->mandat1Asc2String;
    }

    public function setMandatSpecialString(string|null $mandatSpecialString): void
    {
        $this->mandatSpecialString = $mandatSpecialString;
    }
    public function getMandatSpecialString(): string|null
    {
        return $this->mandatSpecialString;
    }

    public function setRefusamoString(string|null $refusamoString): void
    {
        $this->refusamoString = $refusamoString;
    }
    public function getRefusamoString(): string|null
    {
        return $this->refusamoString;
    }

    public function setAmoString(string|null $amoString): void
    {
        $this->amoString = $amoString;
    }
    public function getAmoString(): string|null
    {
        return $this->amoString;
    }

    public function setNomDocumentMpr(string|null $nomDocumentMpr): void
    {
        $this->nomDocumentMpr = $nomDocumentMpr;
    }
    public function getNomDocumentMpr(): string|null
    {
        return $this->nomDocumentMpr;
    }

    public function setPdfDocumentMprString(string|null $pdfDocumentMprString): void
    {
        $this->pdfDocumentMprString = $pdfDocumentMprString;
    }
    public function getPdfDocumentMprString(): string|null
    {
        return  $this->pdfDocumentMprString;
    }

    public function setSubventionString(string|null $subventionString): void
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

    public function setProcurationString(string|null $procurationString): void
    {
        $this->procurationString = $procurationString;
    }
    public function getProcurationString(): string|null
    {
        return $this->procurationString;
    }


    public function setNumMpr2(): string|null
    {
        return $this->numMpr2;
    }
    public function getNumMpr2(): string|null
    {
        return $this->numMpr2;
    }

    public function setAttestationConsentementString(string $attestationConsentementString): void
    {
        $this->attestationConsentementString = $attestationConsentementString;
    }
    public function getAttestationConsentementString(): string|null
    {
        return $this->attestationConsentementString;
    }

    public function setMandatStringFinancier(string $mandatStringFinancier): void
    {
        $this->mandatStringFinancier = $mandatStringFinancier;
    }
    public function getMandatStringFinancier(): string|null
    {
        return $this->mandatStringFinancier;
    }

    public function setMandatStringAdministratif(string $mandatStringAdministratif): void
    {
        $this->mandatStringAdministratif = $mandatStringAdministratif;
    }
    public function getMandatStringAdministratif(): string|null
    {
        return $this->mandatStringAdministratif;
    }

    public function setMandatStringAdministratifFinancier(string $mandatStringAdministratifFinancier): void
    {
        $this->mandatStringAdministratifFinancier = $mandatStringAdministratifFinancier;
    }
    public function getMandatStringAdministratifFinancier(): string|null
    {
        return $this->mandatStringAdministratifFinancier;
    }

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
     * Setup attachments
     */
    public function setupAttachments(): void
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

        if ($this->isSubvention) {
            $this->attachProcuringMandatIfPathContains('ghe', $this->subventionString ?? '', 'lettre_fond_solidarite.pdf');
            $this->attachProcuringMandatIfPathContains('doovision', $this->subventionString ?? '', 'confirmation-des-aides.pdf');
            $this->attachProcuringMandatIfPathContains('efe', $this->subventionString ?? '', 'lettre_confirmation_devis.pdf');
            $this->attachSubventionIfPathDoesNotContain(['ghe', 'doovision', 'efe']);
        }

        $this->attachProcuringMandatIfPathContains('futurenv', $this->procurationString ?? '', 'Procuration.pdf');
        $this->attachMandatIfPresent($this->pdfDocumentMprString, $this->nomDocumentMpr . '.pdf');
        $this->attachMandatIfPresent($this->amoString, 'contrat_amo.pdf');
        $this->attachMandatIfPresent($this->refusamoString, 'refus_amo.pdf');
        $this->attachMandatIfPresent($this->mandatSpecialString, 'mandat_special.pdf');
        $this->attachMandatIfPresent($this->mandat1Asc2String, 'mandat_representation.pdf');
        $this->attachMandatIfPresent($this->mandat2Asc2String, 'mandat_special.pdf');
        $this->attachMandatIfPresent($this->lettreEngagementString, 'lettre_engagement.pdf');
        $this->attachMandatIfPresent($this->tvaString, 'attestation_tva.pdf');
        $this->attachMandatIfPresent($this->ccString, 'cadre_contribution.pdf');


        $this->attachProcuringMandatIfPathContains('leader-energie', $this->docLeaderString ?? '', 'document_eco_energy.pdf');
        $this->attachProcuringMandatIfPathContains('sibel-energie', $this->mandatSibel1String ?? '', 'Mandat_Gestionnaire_de_reseau.pdf');
        $this->attachProcuringMandatIfPathContains('sibel-energie', $this->mandatSibel2String ?? '', 'Mandat_de_demarches_primes_CEE.pdf');
        $this->attachProcuringMandatIfPathContains('sibel-energie', $this->mandatSibel3String ?? '', 'Mandat_dassistance_administrative_(urbanisme_Consuel).pdf');

        $this->attachProcuringMandatIfPathContains('asc2', $this->pdfString ?? '', 'bon_commande.pdf');
        $this->attachProcuringMandatIfPathDoesNotContain('asc2', $this->pdfString ?? '', 'devis.pdf');

        $this->attachMandatIfKeyNotInPath($this->ficheTechs ?? [], 'nom_unique', '.pdf', $this->path, 'upload', 'fiches_techniques');

        $this->attacheGetContent($this->path . "/espace-rac/" . 'upload/SIBEL-PLAQUETTE-PAC.pdf', 'sibel-energie', 'SIBEL-PLAQUETTE-PAC.pdf');
    }

    /**
     * Setup content
     */
    public function setupContent(): void
    {
        $this->email->isHTML(true);
        $this->email->Subject = $this->subject;
        $this->email->Body = $this->body;
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
        if ($mandatString === null) {
            throw new \Exception('No mandat string');
        }
        if ($fileName === null) {
            throw new \Exception('No mandat file name');
        }
    }

    private function pathExist(): bool
    {
        return $this->path !== null;
    }

    private function noPathThrowerException(): void
    {
        if (!$this->pathExist()) {
            throw new \Exception('No path');
        }
    }

    /**
     * Attach procuring mandat if path contains word
     *
     * @param string $fileName Le nom du fichier à rechercher dans le chemin
     * @param string $mandatString La chaîne représentant le mandat à attacher
     * @param string $attachmentName Le nom de la pièce jointe à attacher
     * 
     * @return void
     * 
     * @throws \Exception Si le chemin n'est pas défini ou si le nom du fichier ou de la pièce jointe est vide
     */
    private function attachProcuringMandatIfPathContains(string $fileName, string $mandatString = '', string $attachmentName = ''): void
    {
        $this->noPathThrowerException();

        if (empty($mandatString) || empty($attachmentName)) {
            throw new \Exception('Le nom du fichier ou le nom de la pièce jointe ne peut pas être vide.');
        }

        if (strpos($this->path, $fileName) !== false) {
            $this->attachMandatIfPresent($mandatString, $attachmentName);
        }
    }

    /**
     * Attache un mandat de procuration si le chemin ne contient pas un mot spécifique
     *
     * @param string $fileName Le nom du fichier à rechercher dans le chemin
     * @param string $mandatString La chaîne représentant le mandat à attacher
     * @param string $attachmentName Le nom de la pièce jointe à attacher
     * 
     * @return void
     * 
     * @throws \Exception Si le chemin n'est pas défini ou si le nom du fichier ou de la pièce jointe est vide
     */
    private function attachProcuringMandatIfPathDoesNotContain(string $fileName, string $mandatString = '', string $attachmentName = ''): void
    {
        $this->noPathThrowerException();

        if (empty($mandatString) || empty($attachmentName)) {
            throw new \Exception('Le nom du fichier ou le nom de la pièce jointe ne peut pas être vide.');
        }

        if (strpos($this->path, $fileName) === false) {
            $this->attachMandatIfPresent($mandatString, $attachmentName);
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

        if (!$found && $this->subventionString !== null && $this->nomSubvention !== null) {
            $this->email->addStringAttachment($this->subventionString, $this->nomSubvention . '.pdf');
        }
    }

    /**
     * Attache un mandat si la clé spécifiée dans le tableau n'est pas contenue dans le chemin.
     *
     * @param array<mixed, array<string, mixed>> $dataArray Le tableau contenant les données à parcourir
     * @param string $keyName La clé du tableau dont la valeur sera utilisée comme mandat à attacher
     * @param string $attachmentName Le nom de la pièce jointe à attacher
     * @param string $path Le chemin de base pour construire le chemin complet du fichier à attacher
     * @param string $folder Le dossier dans lequel se trouvent les fichiers à attacher
     * @param string $subFolder Le sous-dossier dans lequel se trouvent les fichiers à attacher
     * 
     * @return void
     * 
     * @throws \Exception Si le chemin n'est pas défini ou si le nom du fichier ou de la pièce jointe est vide
     */
    private function attachMandatIfKeyNotInPath(array $dataArray, string $keyName, string $attachmentName, string $path, string $folder, string $subFolder): void
    {
        $this->noPathThrowerException();

        if (empty($attachmentName)) {
            throw new \Exception('Le nom de la pièce jointe ne peut pas être vide.');
        }

        if (empty($dataArray)) {
            throw new \Exception('Le tableau ne peut pas être vide.');
        }

        foreach ($dataArray as $data) {
            if (!empty($data[$keyName])) {
                if (strpos($this->path, $data[$keyName]) === false) {
                    $file_to_attach = $path . '/' . $folder . '/' . $subFolder . '/' . $data[$keyName];
                    $this->email->addAttachment($file_to_attach, $attachmentName);
                    break;
                }
            }
        }
    }

    /**
     * Attache le contenu d'un fichier au courriel si le nom de fichier est trouvé dans le chemin.
     *
     * @param string $path Le chemin du fichier
     * @param string $fileName Le nom du fichier à rechercher dans le chemin
     * @param string $attachmentName Le nom de la pièce jointe à utiliser dans le courriel
     * 
     * @return void
     * 
     * @throws \Exception Si le nom de la pièce jointe ou le chemin est vide, ou si la lecture du fichier échoue
     */
    private function attacheGetContent(string $path, string $fileName, string $attachmentName): void
    {
        $this->noPathThrowerException();

        if (empty($attachmentName)) {
            throw new \Exception('Le nom de la pièce jointe ne peut pas être vide.');
        }

        if (empty($path)) {
            throw new \Exception('Le chemin ne peut pas être vide.');
        }

        if (strpos($path, $fileName) !== false) {
            $fileContent = file_get_contents($path);
            if ($fileContent !== false) {
                $this->email->addStringAttachment((string)$fileContent, $attachmentName);
            } else {
                throw new \Exception('Impossible de lire le contenu du fichier.');
            }
        }
    }
}
