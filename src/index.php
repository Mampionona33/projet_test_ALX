<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'include/get_path.php');
include($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'sessions/database.class.php');    //Include MySQL database class
include($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'sessions/mysql.sessions.php');    //Include PHP MySQL sessions
// $session = new Session();    //Start a new PHP MySQL session
require_once($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'include/get_conf.php');

require_once $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/autoload.php';

require($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/fpdm/fpdf.php');
require($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/fpdi/autoload.php');
require_once 'vendor/autoload.php';

use \mikehaertl\pdftk\Pdf;
use setasign\Fpdi\Fpdi;
