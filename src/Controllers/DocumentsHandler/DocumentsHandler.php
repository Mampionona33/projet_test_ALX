<?php

namespace Controllers\DocumentsHandler;

use Utils\Curl;
use Utils\CURLStringFile;

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
     * @var array<int, mixed>
     */
    private $signerDatas;

    /**
     * @var array<int, mixed>
     */
    private $documents;

    /**
     * DocumentsHandler constructor.
     * @param array<string, mixed> $pdfStrings
     * @param array<string, mixed> $doc_ids
     * @param array<string, mixed> $page_numbers
     * @param array<string, mixed> $positions
     * @param array<int, mixed> $signerDatas
     * @return void
     */
    public function __construct($pdfStrings, $doc_ids, $page_numbers, $positions, $signerDatas)
    {
        $this->pdfStrings = $pdfStrings;
        $this->doc_ids = $doc_ids;
        $this->documents = [];
        $this->page_numbers = $page_numbers;
        $this->positions = $positions;
        $this->signerDatas = $signerDatas;
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

    private function addSignatureToDocument(string $docId, int $page, float $x, float $y, float $width): void
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
        }
    }

    private function addMandatToDocument(string $docName, string $docId): void
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
     * @param int $x
     * @param int $y
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
     * @param int $x
     * @param int $y
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
     * This function adds a mandat special le document to the list of documents.
     * 
     * It takes in the document ID, name, page number, and a key, which is used to determine
     * the x and y positions of the mention.
     * 
     * The function first adds a signature to the list of documents. The signature is
     * located at page $page, and has a width and height of 162 and 78 pixels, respectively.
     * The x and y positions are set to 355 and 664, respectively.
     * 
     * After that, the function adds a mention to the list of documents. The mention is
     * located at the same page as the signature. The x and y positions are determined based
     * on the key. If the key is 0, the x position is set to 362, and the y position is set
     * to 570. Otherwise, the x position is set to 318, and the y position is set to 626.
     * 
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


    /**
     * @return void
     */
    public function formatDocuments(): void
    {
        $documentsUploaded = $this->uploadDocuments();
        if (!$documentsUploaded || $documentsUploaded === true) {
            return;
        }

        $decodedUploadedDocuments = $this->decodeUploadedDocuments($documentsUploaded);
        if ($decodedUploadedDocuments === null) {
            return;
        }

        $docName = $decodedUploadedDocuments['name'];
        $docId = $decodedUploadedDocuments['id'];

        if ($this->isDocumentName("devis")) {
            $this->processDevisDocument($docName, $docId);
        } else {
            $this->processDocumentByNamePosition($docId);
        }
    }
}
