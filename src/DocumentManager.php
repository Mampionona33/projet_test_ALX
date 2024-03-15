<?php
class DocumentManager{
    /**
     * @var array<string, string>
     */
    private $pdfStrings;

    /**
     * @param array<string, string> $pdfStrings
     */
    public function __construct( $pdfStrings ) {
        $this->pdfStrings = $pdfStrings;
    }

    /**
     * @return bool
     */
    private function isYousignV3() : bool {
        return defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1;
    }

    private function isNameDevis() : bool {
      return $this->isYousignV3() &&  $this->pdfStrings['devis'];
    }
}
