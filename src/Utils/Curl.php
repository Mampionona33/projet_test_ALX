<?php

namespace Utils;

class Curl
{
    /**
     * @var array<int, mixed>
     */
    private array $options;

    /**
     *  @var bool
     */
    private bool $error = false;

    /**
     * @var string
     */
    private string $url;

    /**
     * Constructeur de la classe Curl
     * @param string $url L'URL à utiliser pour les requêtes cURL
     */
    public function __construct(string $url)
    {
        $this->options = [];
        $this->url = $url;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return $this->error;
    }

    /**
     * @return array<int, mixed>
     */
    public function getOpions()
    {
        return $this->options;
    }

    /**
     * Ajoute une option cURL
     * @param mixed $option L'option à ajouter
     */
    public function addOption($option): void
    {
        $this->options[] = $option;
    }

    /**
     * Effectue une requête GET en utilisant cURL
     * @throws \Exception En cas d'erreur cURL
     * @return string|bool Le contenu de la réponse ou false en cas d'échec
     */
    public function execute(): string|bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt_array($ch, $this->options);
        $output = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->error = true;
            throw new \Exception($error);
        }

        if (!$output) {
            return false;
        }

        return $output;
    }

   
}