<?php

namespace Controllers\DocumentsHandler;

use Utils\Curl;
use Utils\CURLStringFile;

class DocumentsHandler {

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
     * @param array<string, mixed> $signerDatas
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
    private function isYouSignV3(){
        return defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 === 1 ;
    }

    /**
     * @return bool
     */
    private function isNamed(string $name) : bool {
        return array_key_exists($name, $this->pdfStrings);
    }

    private function isYouSignApiUrl() : bool {
        return defined('YOUSIGN_API_URL') && YOUSIGN_API_URL !== '';
    }

    private function isYouSignApiKey() : bool {
      return defined('YOUSIGN_API_KEY') && YOUSIGN_API_KEY !== '';
    }

    /**
     * @return bool|string
     */
    private function uploadDocuments() {
        $options = [];
        $documentsUploaded = new Curl();

        foreach ($this->pdfStrings as $name => $pdfString) {
           if($this->isYouSignV3() && $this->isYouSignApiUrl() && $this->isYouSignApiKey()) {
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
           }else {
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
                CURLOPT_POSTFIELDS =>"{\n    \"name\": \"" . $name . "-" . $this->doc_ids[$name] . ".pdf\",\n    \"content\": \"" . $base64FileContent . "\"\n}",
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
        "y" => $y + 10
    ];
}

private function addDateMentionToDocument(string $docId, int $page, float $x, float $y): void
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

private function processDevisDocument(string $docName, string $docId): void
{
    $pt_to_mm = 0.352778;
    $height_paper = round(297 / $pt_to_mm);

    foreach ($this->page_numbers[$docName] as $key => $page) {
        $x_y_positions = explode(',', $this->positions[$docName][$key]);
        $x = intval($x_y_positions[0]);
        $y = $height_paper - intval($x_y_positions[3]);
        $width = intval($x_y_positions[2]) - intval($x_y_positions[0]);

        $this->addSignatureToDocument($docId, $page, $x, $y, $width);
        $this->addDateMentionToDocument($docId, $page, $x, $y);
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

    if ($this->isNamed("devis")) {
        $this->processDevisDocument($docName, $docId);
    }
}



}
