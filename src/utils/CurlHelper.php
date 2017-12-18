<?php

namespace Utils;

class CurlHelper {
    private $ch;

    public function __construct($url) {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_HEADER, 0); 
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); 
    }

    public function getResponse() {
        $rawResponse = curl_exec($this->ch);
        return json_decode($rawResponse, true);
    }
}

?>