<?php
class DocumentManager{
    private $pdfStrings = array();
    private $positions = array();
    private $pageNumbers = array();
    private $docIds = array();

    public function addDocument($name, $string, $position, $pageNumber, $docId) {
        $this->pdfStrings[$name] = $string;
        $this->positions[$name] = $position;
        $this->pageNumbers[$name] = $pageNumber;
        $this->docIds[$name] = $docId;
    }

    public function getDocuments() {
        return array(
            'pdfStrings' => $this->pdfStrings,
            'positions' => $this->positions,
            'pageNumbers' => $this->pageNumbers,
            'docIds' => $this->docIds
        );
    }
}

?>