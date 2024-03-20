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
     * @param array<string, mixed> $documentsIds
     * @return string
     */
    abstract protected function createSignatureProcedure(array $documentsIds);

    /**
     * @param string $procedureId
     * @return bool
     */
    abstract protected function activateSignatureProcedure(string $procedureId);


    public abstract function sendForSigning(): string|bool;
}
