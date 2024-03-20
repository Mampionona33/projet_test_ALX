<?php

namespace Controllers\DocumentsHandler;

abstract class AbstractDocumentsHandler
{
    /**
     * @return array<int, array<string, mixed>>
     */
    abstract protected function prepareDocumentData();

    /**
     * @param array<int, array<string, mixed>> $preparedData
     * @return array<int|string, string|string> Les identifiants des documents créés
     */
    abstract protected function sendDocumentsToAPI($preparedData);

    /**
     * @return string|bool
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
