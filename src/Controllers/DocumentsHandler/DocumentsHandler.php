<?php

namespace Controllers\DocumentsHandler;

use Utils\Curl;
use Utils\CURLStringFile;
use Utils\StringJsonBuilder;

class DocumentsHandler
{

    /**
     * @var array<string, mixed>
     */
    private $pdfStrings;

    /**
     * @var array<string, mixed>
     */
    private $doc_ids;

    /**
     * @var array<string, mixed>
     */
    private $page_numbers;

    /**
     * @var array<string, mixed>
     */
    private $positions;

    /**
     * @var array<string, mixed>
     */
    private $signerDatas;

    /**
     * @var array<int, mixed>
     */
    private $documents;

    /**
     * @var string
     */
    private $procedure_finished_email;

    /**
     * @var string
     */
    private $ex_date;

    /**
     * @var string | int
     */
    private $devis_id;

    /**
     * @var array<int |string>
     */
    private $documents_ids;

    /**
     * @var string
     */
    private $webhook_url;

    /**
     * DocumentsHandler constructor.
     * @param array<string, mixed> $pdfStrings
     * @param array<string, mixed> $doc_ids
     * @param array<string, mixed> $page_numbers
     * @param array<string, mixed> $positions
     * @param array<string, mixed> $signerDatas
     * @param string $ex_date
     * @param string $devis_id
     * @return void
     */
    public function __construct($pdfStrings, $doc_ids, $page_numbers, $positions, $signerDatas, $ex_date, $devis_id)
    {
        $this->pdfStrings = $pdfStrings;
        $this->doc_ids = $doc_ids;
        $this->documents = [];
        $this->page_numbers = $page_numbers;
        $this->positions = $positions;
        $this->signerDatas = $signerDatas;
        $this->ex_date = $ex_date ?? '';
        $this->devis_id = $devis_id;
        $this->webhook_url = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/webhook_yousign.php";
    }

    /**
     * @return bool
     */
    private function isYouSignV3()
    {
        return defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 === 1;
    }

    /**
     * Vérifie si le nom du document est dans le tableau
     * @return bool
     */
    private function isDocumentName(string $name): bool
    {
        return array_key_exists($name, $this->pdfStrings);
    }

    /**
     * Vérifier si le nom du document est dans le tableau position
     */
    private function isDocumentPosition(string $name): bool
    {
        return array_key_exists($name, $this->positions);
    }

    private function isYouSignApiUrl(): bool
    {
        return defined('YOUSIGN_API_URL') && YOUSIGN_API_URL !== '';
    }

    private function isYouSignApiKey(): bool
    {
        return defined('YOUSIGN_API_KEY') && YOUSIGN_API_KEY !== '';
    }

    /**
     * Upload documents
     * @return bool|string
     */
    private function uploadDocuments()
    {
        $options = [];
        $documentsUploaded = new Curl();

        foreach ($this->pdfStrings as $name => $pdfString) {
            if ($this->isYouSignV3() && $this->isYouSignApiUrl() && $this->isYouSignApiKey()) {
                $options[] = [
                    CURLOPT_URL => 'https://' . YOUSIGN_API_URL . '/documents',
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POSTFIELDS =>  array(
                        'file' => new CURLStringFile($pdfString, $name . '-' . $this->doc_ids[$name] . '.pdf', 'application/pdf'), 'nature' => 'signable_document'
                    ),
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Bearer " . YOUSIGN_API_KEY
                    ),
                ];
            } else {
                $base64FileContent =  base64_encode($pdfString);
                $options[] = [
                    CURLOPT_URL => 'https://' . YOUSIGN_API_URL . "/files",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => "{\n    \"name\": \"" . $name . "-" . $this->doc_ids[$name] . ".pdf\",\n    \"content\": \"" . $base64FileContent . "\"\n}",
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Bearer " . YOUSIGN_API_KEY,
                        "Content-Type: application/json"
                    ),
                ];
            }
        }
        $documentsUploaded->setOptions($options);
        $response = $documentsUploaded->execute();
        return $response;
    }


    private function decodeUploadedDocuments(string $documentsUploaded): ?array
    {
        return json_decode($documentsUploaded, true);
    }

    private function addSignatureToDocument(int|string $docId, int $page, float $x, float $y, float $width): void
    {
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "signature",
            "page" => $page,
            "width" => $width,
            "x" => $x,
            "y" => $y
        ];
    }

    private function addDateMentionDateToDocument(string $docId, int $page, float $x, float $y): void
    {
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "mention",
            "mention" => "%date%",
            "page" => $page,
            "x" => $x + 20,
            "y" => $y - 5
        ];
    }

    private function addSignerMentionToDocument(string $docId, int $page, float $x, float $y): void
    {
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "mention",
            "mention" => $this->signerDatas['firstname'] . " " . $this->signerDatas['lastname'] . " - Bon pour Accord",
            "page" => $page,
            "x" => $x,
            "y" => $y + 40
        ];
    }

    /**
     * Ajoute le bon pour accord au document
     * @param string $name
     * @param string $key
     * @param int $page
     * @param string $fileId
     * @param array<string, mixed> $positions
     * @return void
     */
    private function addBonPourAccordToDocument($name,  $key,  $page,  $fileId, $positions)
    {
        $this->documents[] = [
            "mention" => "{date.fr}",
            "mention2" => $this->signerDatas['firstname'] . " " . $this->signerDatas['lastname'] . " - Bon pour Accord",
            "position" => $positions[$name][$key],
            "page" => $page,
            "file" => $fileId
        ];
    }


    private function processDevisDocument(string $docName, string $docId): void
    {
        $pt_to_mm = 0.352778;
        $height_paper = round(297 / $pt_to_mm);

        foreach ($this->page_numbers[$docName] as $key => $page) {
            $x_y_positions = explode(',', $this->positions[$docName][$key]);
            $x = intval($x_y_positions[0]);
            $y = $height_paper - intval($x_y_positions[3]);
            $width = intval($x_y_positions[2]) - intval($x_y_positions[0]);

            $this->addSignatureToDocument($docId, $page, $x, $y + 10, $width);
            $this->addDateMentionDateToDocument($docId, $page, $x, $y);
            $this->addSignerMentionToDocument($docId, $page, $x, $y);
            $this->addBonPourAccordToDocument($docName, $key, $page, $docId, $this->positions);
        }
    }

    private function addMandatToDocument(string $docName, int|string $docId): void
    {
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "mention",
            "mention" => "%date%",
            "page" => $this->page_numbers[$docName],
            "x" => 76, //$x + 100,
            "y" => 610 //$y - 48
        ];
    }

    /**
     * Process the attestation tva
     * @param string|int $docId
     * @param string $name
     * @param float $x
     * @param float $y
     * @return void
     */
    private function addAttestationTvaToDocument($docId, $name, $x, $y): void
    {
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "mention",
            "mention" => "%date%",
            "page" => $this->page_numbers[$name],
            "x" => $x + 130,
            "y" => $y - 40
        ];
    }

    /**
     * Ajout de subvention dans le document
     * @param string|int $docId
     * @param string $name
     * @param float $x
     * @param float $y
     */
    private function addSubventionToDocument($docId, $name, $x, $y): void
    {
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "mention",
            "mention" => "%date%",
            "page" => $this->page_numbers[$name],
            "x" => $x,
            "y" => $y + 45
        ];
    }

    /**
     * @param string|int $docId The ID of the document.
     * @param string $name The name of the document.
     * @param int $page The page number of the document.
     * @param int $key The key used to determine the x and y positions of the mention.
     * @return void
     */
    private function addMandatSpecialLeToDocument($docId, $name, $page, $key): void
    {
        // Add a signature to the list of documents
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "signature",
            "mention" => "%date%",
            "page" => $page,
            "width" => 162,
            "height" => 78,
            "x" => 355,
            "y" => 664
        ];

        // Determine the x and y positions of the mention based on the key
        $mention_x = ($key == 0) ? 362 : 318;
        $mention_y = ($key == 0) ? 570 : 626;

        // Add a mention to the list of documents
        $this->documents[] = [
            "document_id" => $docId,
            "type" => "mention",
            "mention" => "%date%",
            "page" => $page,
            "x" => $mention_x,
            "y" => $mention_y
        ];
    }

    private function addFilesToDocument(string $name, int $key, int $page, int|string $fileId): void
    {
        $this->documents[] = [
            "mention" => "{date.fr}",
            "position" => $this->positions[$name][$key],
            "page" => $page,
            "file" => $fileId
        ];
    }

    /**
     * Ajoute une mention à la liste des documents.
     * @param string $name Le nom du document.
     * @param int $key La clé de la mention.
     * @param int $page Le numéro de page.
     * @param int|string $fileId L'ID du fichier
     */
    private function addListTravauxPreconisesToDocument($name, $key, $page, $fileId): void
    {
        $this->documents[] = [
            "mention" => "{date.fr}",
            "mention2" => $this->signerDatas['firstname'] . " " . $this->signerDatas['lastname'],
            "position" => $this->positions[$name][$key],
            "page" => $page,
            "file" => $fileId
        ];
    }

    /**
     * @param string|int $docId The ID of the document.
     * @return void
     */
    private function processDocumentByNamePosition($docId): void
    {
        $pt_to_mm = 0.352778;
        $height_paper = round(297 / $pt_to_mm);
        $actions = [];

        foreach ($this->pdfStrings as $name => $pdfString) {
            if (isset($this->page_numbers[$name])) {
                foreach ($this->page_numbers[$name] as $key => $page) {
                    $x_y_positions = explode(',', $this->positions[$name][$key]);
                    if (is_array($x_y_positions) && count($x_y_positions) >= 4) {
                        $x = intval($x_y_positions[0]);
                        $y = $height_paper - intval($x_y_positions[3]);
                        $width = intval($x_y_positions[2]) - $x;

                        // Add the action to the list of actions
                        $actions[] = function () use ($name, $docId, $page, $key, $x, $y, $width) {
                            // Check the document type and add it to the list of documents
                            if (
                                $this->isDocumentName('mandat_administratif_financier') ||
                                $this->isDocumentName('mandat_administratif') ||
                                $this->isDocumentName('mandat_financier')
                            ) {
                                $this->addMandatToDocument($name, $docId);
                            } else if ($this->isDocumentName('attestation_tva')) {
                                $this->addAttestationTvaToDocument($docId, $name, $x, $y);
                            } else if ($this->isDocumentName('subvention')) {
                                $this->addSubventionToDocument($docId, $name, $x, $y);
                            } else if ($this->isDocumentName('mandat_special_le')) {
                                $this->addMandatSpecialLeToDocument($docId, $name, $page, $key);
                            } else if ($this->isDocumentName('list_travaux_preconises')) {
                                $this->addListTravauxPreconisesToDocument($name, $key, $page, $docId);
                            } else if (
                                $this->isDocumentName('amo') || $this->isDocumentName('doc_leader') ||
                                $this->isDocumentName('mandat_sibel1') || $this->isDocumentName('mandat_sibel2') ||
                                $this->isDocumentName('mandat_sibel3') || $this->isDocumentName('doc_planitis') ||
                                $this->isDocumentName('procuration')
                            ) {
                                $this->addFilesToDocument($name, $key, $page, $docId);
                            }

                            if (!is_array($this->page_numbers[$name])) {
                                $this->addSignatureToDocument($docId, $page, $x, $y, $width);
                            }
                        };
                    }
                }
            }
        }

        // Execute all the actions
        foreach ($actions as $action) {
            $action();
        }
    }



    private function buildProcedureFinishedEmail(): string|bool
    {
        // Création d'une instance de StringJsonBuilder
        $procedure_finished_email_json = new StringJsonBuilder();

        // Vérifier si les emails de suivi des dossiers sont définis et valides
        if (defined('EMAILS_SUIVI_DOSSIERS') && is_array(EMAILS_SUIVI_DOSSIERS) && !empty(EMAILS_SUIVI_DOSSIERS)) {
            // Création de l'e-mail de notification pour les dossiers de suivi
            $subject = "[YOUSIGN] " . $this->signerDatas['firstname'] . " " . $this->signerDatas['lastname'] . " vient de signer les documents.";
            $message = $this->signerDatas['firstname'] . " " . $this->signerDatas['lastname'] . " (" . $this->signerDatas['phone'] . ") vient de signer les documents. Cliquez ici pour y accéder : <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accéder aux documents\">Accéder aux documents</tag><br><br>Très cordialement,<br>" . SOCIETE . ".";
            $destinataires = json_decode(EMAILS_SUIVI_DOSSIERS);

            $procedure_finished_email_json->addField("subject", $subject);
            $procedure_finished_email_json->addField("message", $message);
            $procedure_finished_email_json->addField("to", $destinataires);
        }

        // Création de l'e-mail de notification pour le membre
        $subject_member = "Documents signés avec succès !";
        $message_member = "Bonjour <tag data-tag-type=\"string\" data-tag-name=\"recipient.firstname\"></tag> <tag data-tag-type=\"string\" data-tag-name=\"recipient.lastname\"></tag>, <br><br> Vos documents ont bien été signés électroniquement. Cliquez ici pour y accéder : <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accéder aux documents\">Accéder aux documents</tag><br><br>Très cordialement,<br>" . SOCIETE . ".";

        $procedure_finished_email_json->addField("subject", $subject_member);
        $procedure_finished_email_json->addField("message", $message_member);
        $procedure_finished_email_json->addField("to", ["@member"]);

        // Construction de la chaîne JSON finale
        return $procedure_finished_email_json->build();
    }

    /**
     * @return string|bool
     */
    private function buildMembersArb(): string|bool
    {
        $members_arb_json = new StringJsonBuilder();

        // Construire les données JSON pour le membre
        $members_arb_json->addField("firstname", $this->signerDatas['firstname']);
        $members_arb_json->addField("lastname", $this->signerDatas['lastname']);
        $members_arb_json->addField("email", $this->signerDatas['email']);
        $members_arb_json->addField("phone", $this->signerDatas['phone']);
        $members_arb_json->addField("fileObjects", $this->documents);

        // Retourner la chaîne JSON finale pour le membre
        return $members_arb_json->build();
    }

    private function createSignaturePostRequest(): string|bool
    {
        $postRequset = new StringJsonBuilder();
        $expire_at = $this->ex_date ?
            '"expiration_date": "' . $this->ex_date . '",' : "";
        $signers = [
            [
                "info" => [
                    "first_name" => $this->signerDatas['firstname'],
                    "last_name" => $this->signerDatas['lastname'],
                    "email" => $this->signerDatas['email'],
                    "phone_number" => $this->signerDatas['phone'],
                    "locale" => "fr"
                ],
                "signature_level" => "electronic_signature",
                "signature_authentication_mode" => "otp_sms",
                "custom_text" => [
                    "request_subject" => "Vous êtes invité à signer vos documents",
                    "request_body" => "Veuillez signer les documents suivants.",
                    "reminder_subject" => "Rappel : Vous n'avez pas encore signé vos documents.",
                    "reminder_body" => "Veuillez signer les documents suivants."
                ],
                "fields" => json_encode($this->documents)
            ]
        ];

        $postRequset->addField("name", "Liste des documents a signer par le client");
        $postRequset->addField("delivery_mode", "none");
        $postRequset->addField("external_id", "DEVIS_'.$this->devis_id.'");
        $postRequset->addField("timezone", "Europe/Paris");
        $postRequset->addField("email_custom_note", "Veuillez signer les documents suivants.");
        $postRequset->addField("email_custom_note", "Veuillez signer les documents suivants. ," . $expire_at);
        $postRequset->addField("documents", json_encode($this->documents_ids));
        $postRequset->addField("signers", $signers);


        return $postRequset->build();
    }

    private function createProcedurePostRequset(): string|bool
    {
        $postRequest = new StringJsonBuilder();
        $expires_at = $this->ex_date ? '"expiresAt": "' . $this->ex_date . '",' : "";
        $config = [
            "email" => [
                "procedure.finished" => [$this->procedure_finished_email]
            ],
            "webhook" => [
                "procedure.started" => [
                    [
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => [
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Started"
                        ]
                    ]
                ],
                "procedure.finished" => [
                    [
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => [
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Finished"
                        ]
                    ]
                ],
                "procedure.refused" => [
                    [
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => [
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Refused"
                        ]
                    ]
                ],
                "procedure.expired" => [
                    [
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => [
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Expired"
                        ]
                    ]
                ],
                "procedure.deleted" => [
                    [
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => [
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Deleted"
                        ]
                    ]
                ]
            ]
        ];


        $postRequest->addField("name", "Documents a signer");
        $postRequest->addField(
            "description",
            "Liste des documents a signer par le client" .
                $expires_at
        );
        $postRequest->addField("start", true);
        $postRequest->addField("members", $this->buildMembersArb());
        $postRequest->addField("operationLevel", "advanced");
        $postRequest->addField("config", json_encode($config));

        return $postRequest->build();
    }


    private function getSignatures(): string |bool
    {
        $signatureCurl = new Curl();
        $options = [
            CURLOPT_URL => 'https://' . YOUSIGN_API_URL . '/signature_requests',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $this->createSignaturePostRequest(),
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . YOUSIGN_API_KEY,
                "Content-Type: application/json"
            )
        ];
        $signatureCurl->setOptions($options);
        return $signatureCurl->execute();
    }

    private function getProcedure(): string |bool
    {
        $procedureCurl = new Curl();
        $options = [
            CURLOPT_URL => 'https://' . YOUSIGN_API_URL . "/procedures",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $this->createProcedurePostRequset()
        ];
        $procedureCurl->setOptions($options);
        return $procedureCurl->execute();
    }

    /**
     * @return void
     */
    public function formatDocuments(): void
    {

        foreach ($this->pdfStrings as $name => $pdfString) {
            $documentsUploaded = $this->uploadDocuments();
            if (!$documentsUploaded || $documentsUploaded === true) {
                return;
            }

            $decodedUploadedDocuments = $this->decodeUploadedDocuments($documentsUploaded);
            if ($decodedUploadedDocuments === null) {
                return;
            }

            if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 === 1) {

                $docName = $decodedUploadedDocuments['name'];
                $docId = $decodedUploadedDocuments['id'];
                $this->documents_ids[] = $docId;

                if ($this->isDocumentName("devis")) {
                    $this->processDevisDocument($docName, $docId);
                } else {
                    $this->processDocumentByNamePosition($docId);
                }
            }
        }
    }


    private function createProcedure(): string | bool
    {
        if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 === 1) {
            return $this->getSignatures();
        }
        return $this->getProcedure();
    }

    private function sendDocForSigning(): string | bool
    {
    }
}
