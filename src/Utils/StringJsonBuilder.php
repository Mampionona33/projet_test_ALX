<?php

namespace Utils;

class StringJsonBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $fields;

    public function __construct()
    {
        $this->fields = [];
    }

    /**
     * Ajoute un champ avec le nom et la valeur spécifiés.
     *
     * @param string $name Le nom du champ
     * @param mixed $value La valeur du champ
     * @return void
     */
    public function addField(string $name, mixed $value): void
    {
        $this->fields[$name] = $value;
    }

    /**
     * Construit et retourne les champs sous forme de chaîne JSON.
     *
     * @return string|false La chaîne JSON des champs ou false en cas d'erreur
     */
    public function build(): string|false
    {
        $json = json_encode($this->fields);
        return $json;
    }
}
