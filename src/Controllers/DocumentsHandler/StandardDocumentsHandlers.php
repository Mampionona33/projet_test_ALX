<?php

namespace Controllers\DocumentsHandler;

use Controllers\DocumentsHandler\AbstractDocumentsHandler;
use Utils\CURLStringFile;

class StandardDocumentsHandlers extends AbstractDocumentsHandler
{
    /**
     * @var array<string, mixed> $pdfStrings
     */
    private $pdfStrings;

    /**
     * @var array<string, mixed> $doc_ids
     */
    private $doc_ids;

    /**
     * @var array<string, mixed> $signerDatas
     */
    private $signerDatas;

    /**
     * @var array<string, mixed> $page_numbers
     */
    private $page_numbers;

    /**
     * @var array<string, mixed> $positions
     */
    private $positions;

    /**
     * @var array<string, mixed> $ex_date
     */
    private $ex_date;

    /**
     * @var string $devis_id
     */
    private $devis_id;

    /**
     * @var array<string, mixed> $signer_types
     */
    private $signer_types;

    /**
     * @var string $documentUrl
     */
    private $documentUrl;

    /**
     * @var string $fileUrl
     */
    private $fileUrl;

    /**
     * @var array<int|string, mixed> $documents_ids
     */
    private $documents_ids;

    /**
     * @var array<string, mixed> $documents
     */
    private $documents;

    /**
     * @var string $webhook_url
     */
    private $webhook_url;

    /**
     * DocumentsHandler constructor.
     * @param array<string, mixed> $pdfStrings
     * @param array<string, mixed> $doc_ids
     * @param array<string, mixed> $signerDatas
     * @param array<string, mixed> $page_numbers
     * @param array<string, mixed> $positions
     * @param array<string, mixed> $ex_date
     * @param string $devis_id
     * @param array<string, mixed> $signer_types
     * @return void
     */
    public function __construct($pdfStrings, $doc_ids, $signerDatas, $page_numbers, $positions, $ex_date, $devis_id, $signer_types)
    {
        $this->pdfStrings = $pdfStrings;
        $this->doc_ids = $doc_ids;
        $this->signerDatas = $signerDatas;
        $this->page_numbers = $page_numbers;
        $this->positions = $positions;
        $this->ex_date = $ex_date;
        $this->devis_id = $devis_id;
        $this->signer_types = $signer_types;
        $this->documents_ids = [];
        $this->documents = [];
        $this->webhook_url = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/webhook_yousign.php";
    }

    public function setDoumentUrl(string $url): void
    {
        $this->documentUrl = $url;
    }

    /**
     * Méthode pour envoyer les documents à signer à YouSign
     * @throws \Exception
     */
    public function sendDocumentsForSigning(array $pdfStrings, array $docIds, array $pageNumbers, array $positions, array $signerDatas): void
    {
        foreach ($pdfStrings as $name => $pdfString) {
            // Initialisation de cURL
            $curl = curl_init();

            // Configuration des options cURL
            // ...

            // Exécution de la requête
            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                throw new \Exception("error" . $err);
            }



            $json = json_decode($response, true);

            // Ajout de l'identifiant du document à la liste des documentsIds
            $this->documentsIds[] = $json['id'];

            // Vérification du nom du document
            if ($name == "devis") {
                // Traitement spécifique pour le document "devis"
                // ...
            } else {
                // Autres traitements pour les autres types de documents
                // ...
            }
        }
    }

    public function setFileUrl(): void
    {
        $this->fileUrl = $this->fileUrl;
    }

    public function setWebhookUrl(string $webhook_url): void
    {
        $this->webhook_url = $webhook_url;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhook_url;
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    protected function prepareDocumentData()
    {
        $preparedData = [];
        // Loop through each document and prepare data for it
        foreach ($this->pdfStrings as $name => $pdfString) {
            $preparedData[] = [
                'pdf_string' => $pdfString,
                'document_id' => $this->doc_ids[$name],
                'signer_data' => $this->signerDatas[$name],
                'page_number' => $this->page_numbers[$name],
                'position' => $this->positions[$name],
                'ex_date' => $this->ex_date,
                'devis_id' => $this->devis_id,
                'signer_types' => $this->signer_types,
            ];
        }
        return $preparedData;
    }

    /**
     * Envoie le document à l'API de signature et retourne l'identifiant du document créé.
     *
     * @param string $pdfString Le contenu du PDF à envoyer
     * @param string $docId L'identifiant du document
     * @param string $name Le nom du document
     * @return string|bool L'identifiant du document créé par l'API de signature ou false en cas d'échec
     */
    protected function sendDocumentToAPI(string $pdfString, string $docId, string $name): string|bool
    {
        // Initialisation de cURL
        $curl = curl_init();

        if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 === 1) {
            curl_setopt($curl, CURLOPT_URL, $this->documentUrl);
            curl_setopt($curl, CURLOPT_POSTFIELDS, [
                'file' => new CURLStringFile($pdfString, $name . '-' . $docId . '.pdf', 'application/pdf'),
                'nature' => 'signable_document'
            ]);
        } else {
            $base64FileContent = base64_encode($pdfString);
            curl_setopt($curl, CURLOPT_URL, $this->fileUrl);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
                'name' => $name . '-' . $docId . '.pdf',
                'content' => $base64FileContent
            ]));
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }

        // Configuration des autres options cURL
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . YOUSIGN_API_KEY
            ],
        ]);

        // Exécution de la requête
        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            curl_close($curl);
            return false;
        } else {
            if (is_string($response)) {
                $responseData = json_decode($response, true);

                if (isset($responseData['document_id'])) {
                    curl_close($curl);
                    return $responseData['document_id'];
                } else {
                    curl_close($curl);
                    return false;
                }
            } else {
                curl_close($curl);
                return false;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $preparedData
     * @return void
     */
    protected function getDocumentIds($preparedData)
    {
        $this->documents_ids = [];

        foreach ($preparedData as $name => $documentData) {
            $pdfString = $documentData['pdf_string'];
            $docId = $documentData['doc_id'];
            $name = $documentData['name'];

            $documentId = $this->sendDocumentToAPI($pdfString, $docId, $name);

            $this->documents_ids[$docId] = $documentId;
        }
    }

    /**
     * Crée le tableau des membres à envoyer à l'API de signature.
     *
     * @return string Le JSON représentant les membres
     */
    protected function createMembersArb(): string
    {
        $members_arb = '{
            "firstname": "' . $this->signerDatas['firstname'] . '",
            "lastname": "' . $this->signerDatas['lastname'] . '",
            "email": "' . $this->signerDatas['email'] . '",
            "phone": "' . $this->signerDatas['phone'] . '",
            "fileObjects": ' . json_encode($this->documents) . '
        }';

        return $members_arb;
    }

    /**
     * @return string
     */
    protected function createProcedureFinishedEmail(): string
    {
        $procedure_finished_email = '';


        // @phpstan-ignore-next-line
        if (defined('EMAILS_SUIVI_DOSSIERS') && is_array(EMAILS_SUIVI_DOSSIERS) && !empty(EMAILS_SUIVI_DOSSIERS)) {
            $procedure_finished_email .= '{
            "subject": "[YOUSIGN] ' . $this->signerDatas['firstname'] . ' ' . $this->signerDatas['lastname'] . ' viens de signer les documents.",
            "message": "' . $this->signerDatas['firstname'] . ' ' . $this->signerDatas['lastname'] . ' (' . $this->signerDatas['phone'] . ') viens de signer les documents. Cliquez ici pour y accéder : <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accèder aux documents\">Accèder aux documents</tag><br><br>Très cordialement,<br>' . SOCIETE . '.",
            "to": ' . json_encode(EMAILS_SUIVI_DOSSIERS) . '
        },';
        }

        $procedure_finished_email .= '{
        "subject": "Documents signés avec succès !",
        "message": "Bonjour <tag data-tag-type=\"string\" data-tag-name=\"recipient.firstname\"></tag> <tag data-tag-type=\"string\" data-tag-name=\"recipient.lastname\"></tag>, <br><br> Vos documents ont bien été signés électroniquement. Cliquez ici pour y accéder : <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accèder aux documents\">Accèder aux documents</tag><br><br>Très cordialement,<br>' . SOCIETE . '.",
        "to": ["@member"]
    }';

        return $procedure_finished_email;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function createPostFieldSignatureProcedure()
    {
        $expires_at = $this->ex_date ? $this->ex_date : null;

        $postData = array(
            "name" => "Liste des documents à signer par le client",
            "delivery_mode" => "none",
            "external_id" => "DEVIS_" . $this->devis_id,
            "timezone" => "Europe/Paris",
            "email_custom_note" => "Veuillez signer les documents suivants.",
            "expiration_date" => $expires_at,
            "documents" => json_encode($this->documents_ids),
            "signers" => array(
                array(
                    "info" => array(
                        "first_name" => $this->signerDatas['firstname'],
                        "last_name" => $this->signerDatas['lastname'],
                        "email" => $this->signerDatas['email'],
                        "phone_number" => $this->signerDatas['phone'],
                        "locale" => "fr"
                    ),
                    "signature_level" => "electronic_signature",
                    "signature_authentication_mode" => "otp_sms",
                    "custom_text" => array(
                        "request_subject" => "Vous êtes invité à signer vos documents",
                        "request_body" => "Veuillez signer les documents suivants.",
                        "reminder_subject" => "Rappel : Vous n'avez pas encore signé vos documents.",
                        "reminder_body" => "Veuillez signer les documents suivants."
                    ),
                    "fields" => json_encode($this->documents)
                )
            )
        );


        $jsonString = json_encode($postData);

        if ($jsonString === false) {
            throw new \Exception("Failed to encode JSON");
        }

        return $jsonString;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function createPostFieldListDocumentsAsigner(): string
    {

        $expires_at = $this->ex_date ? $this->ex_date : null;

        $postData = array(
            "name" => "Documents à signer",
            "description" => "Liste des documents à signer par le client",
            "expiresAt" => $expires_at,
            "start" => true,
            "members" => $this->createMembersArb(),
            "operationLevel" => "advanced",
            "config" => array(
                "email" => array(
                    "procedure.finished" => array($this->createProcedureFinishedEmail())
                )
            ),
            "webhook" => array(
                "procedure.started" => array(
                    array(
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => array(
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Started"
                        )
                    )
                ),
                "procedure.finished" => array(
                    array(
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => array(
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Finished"
                        )
                    )
                ),
                "procedure.refused" => array(
                    array(
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => array(
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Refused"
                        )
                    )
                ),
                "procedure.expired" => array(
                    array(
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => array(
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Expired"
                        )
                    )
                ),
                "procedure.deleted" => array(
                    array(
                        "url" => $this->webhook_url,
                        "method" => "GET",
                        "headers" => array(
                            "X-Yousign-Custom-Header" => "Yousign Webhook - Procedure Deleted"
                        )
                    )
                )
            )
        );


        $jsonString = json_encode($postData);

        if ($jsonString === false) {
            throw new \Exception("Failed to encode JSON");
        }

        return $jsonString;
    }

    private function isYousignV3(): bool
    {
        return defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 === 1;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function createSignatureProcedure(): string
    {
        $curl = curl_init();
        if ($this->isYousignV3()) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://' . YOUSIGN_API_URL . '/signature_requests',
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . YOUSIGN_API_KEY,
                    "Content-Type: application/json"
                ),
                CURLOPT_POSTFIELDS => $this->createPostFieldSignatureProcedure(),
            ));
        } else {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://' . YOUSIGN_API_URL . "/procedures",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $this->createPostFieldListDocumentsAsigner(),
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . YOUSIGN_API_KEY,
                    "Content-Type: application/json"
                )
            ));
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception("cURL Error #:" . $err);
        }

        if (is_string($response)) {
            return $response;
        } else {
            throw new \Exception("Failed to create signature procedure");
        }
    }

    /**
     * @param string $procedureId
     * @return string|bool
     * @throws \Exception
     */
    protected function activateSignatureProcedure($procedureId)
    {
        $json = json_decode($procedureId, true);

        $curl = curl_init();

        if (!isset($json['id'])) {
            throw new \Exception("Impossible de créer la procédure de signature. Erreur #:" . $procedureId);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://' . YOUSIGN_API_URL . '/signature_requests/' . $json['id'] . '/activate',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . YOUSIGN_API_KEY
            )
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception("cURL Error #:" . $err);
        } else {
            return $response;
        }
    }

    public function sendForSigning(): string|bool
    {
        $preparedData = $this->prepareDocumentData();
        $this->getDocumentIds($preparedData);
        $procedureId = $this->createSignatureProcedure();

        $activationResult = $this->activateSignatureProcedure($procedureId);

        return $activationResult;
    }
}
