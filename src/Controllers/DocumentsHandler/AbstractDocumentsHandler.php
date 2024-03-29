<?php

namespace Controllers\DocumentsHandler;

abstract class AbstractDocumentsHandler
{
    /**
     * @return array<int, array<string, mixed>>
     */
    // abstract protected function prepareDocumentData();

    /**
     * @return string
     * @throws \Exception
     */
    abstract protected function createSignatureProcedure();

    /**
     * @param string $procedureId
     * @return bool
     */
    abstract protected function activateSignatureProcedure(string $procedureId);


    public abstract function sendForSigning(): string|bool;
}
