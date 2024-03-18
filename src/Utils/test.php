<?php
require_once 'vendor/autoload.php';

use Utils\Curl;
use Utils\StringJsonBuilder;

$curl = new Curl();
$curl->addOption(array(
    CURLOPT_URL => 'https://httpbin.org/post',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "test",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
));

var_dump($curl->getOpions());

$fields = new StringJsonBuilder();
$fields->addField('nom', 'test');
$fields->addField('nom_1', 'test_1');
$result = $fields->build();

var_dump($result);
