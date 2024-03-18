<?php

namespace Utils;

use CURLFile;
class CURLStringFile extends CURLFile {
    public function __construct(string $data, string $postname, string $mime = "application/octet-stream") {
        $this->name     = 'data://'. $mime .';base64,' . base64_encode($data);
        $this->mime     = $mime;
        $this->postname = $postname;
    }
}