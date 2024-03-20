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
     * @var array<string, mixed> $devis_id
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
     * DocumentsHandler constructor.
     * @param array<string, mixed> $pdfStrings
     * @param array<string, mixed> $doc_ids
     * @param array<string, mixed> $signerDatas
     * @param array<string, mixed> $page_numbers
     * @param array<string, mixed> $positions
     * @param array<string, mixed> $ex_date
     * @param array<string, mixed> $devis_id
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
    }
    public function sendForSigning(): string|bool
    {
        $preparedData = $this->prepareDocumentData();
        $documentsIds = $this->sendDocumentsToAPI($preparedData);
        $procedureId = $this->createSignatureProcedure($documentsIds);
        $activationResult = $this->activateSignatureProcedure($procedureId);
        return $activationResult;
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

    public function setDoumentUrl(string $url): void
    {
        $this->documentUrl = $url;
    }

    public function setFileUrl(): void
    {
        $this->fileUrl = $this->fileUrl;
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
     * @return array<int|string, bool|string> Les identifiants des documents créés
     */
    protected function sendDocumentsToAPI($preparedData)
    {
        $documentsIds = [];

        foreach ($preparedData as $name => $documentData) {
            $pdfString = $documentData['pdf_string'];
            $docId = $documentData['doc_id'];
            $name = $documentData['name'];

            $documentId = $this->sendDocumentToAPI($pdfString, $docId, $name);

            $documentsIds[$docId] = $documentId;
        }

        return $documentsIds;
    }



    private function createSignatureProcedure($documentsIds)
    {
        // Logique de création de la procédure de signature
        // Retourne l'identifiant de la procédure créée
    }

    /**
     * @return bool|string
     */
    private function activateSignatureProcedure($procedureId)
    {
    }
}
