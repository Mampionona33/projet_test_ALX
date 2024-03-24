<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'include/get_path.php');
include($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'sessions/database.class.php');    //Include MySQL database class
include($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'sessions/mysql.sessions.php');    //Include PHP MySQL sessions
$session = new Session();    //Start a new PHP MySQL session
require_once($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'include/get_conf.php');

require_once $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/autoload.php';

require($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/fpdm/fpdf.php');
require($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/fpdi/autoload.php');

use \mikehaertl\pdftk\Pdf;
use setasign\Fpdi\Fpdi;



class CURLStringFile extends CURLFile
{
    public function __construct(string $data, string $postname, string $mime = "application/octet-stream")
    {
        $this->name     = 'data://' . $mime . ';base64,' . base64_encode($data);
        $this->mime     = $mime;
        $this->postname = $postname;
    }
}

function send_document_for_signing($pdfStrings, $doc_ids, $signerDatas, $page_numbers, $positions, $ex_date = "", $devis_id, $signer_types)
{

    global $path;
    /*$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 
                "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/webhook_yousign.php";*/
    $webhook_url = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/webhook_yousign.php";

    $appPath = getcwd();


    $documents = array();
    $i = 0;
    $documents_ids = [];
    foreach ($pdfStrings as $name => $pdfString) {
        // SEND FILE TO YOUSIGN
        /*$contentBytes = file_get_contents($appPath . "/" . $fileNamePath);*/
        $curl = curl_init();

        if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://' . YOUSIGN_API_URL . '/documents',
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS =>  array(
                    'file' => new CURLStringFile($pdfString, $name . '-' . $doc_ids[$name] . '.pdf', 'application/pdf'), 'nature' => 'signable_document'
                ),
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . YOUSIGN_API_KEY
                ),
            ));
        } else {
            $base64FileContent =  base64_encode($pdfString);
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://' . YOUSIGN_API_URL . "/files",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "{\n    \"name\": \"" . $name . "-" . $doc_ids[$name] . ".pdf\",\n    \"content\": \"" . $base64FileContent . "\"\n}",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . YOUSIGN_API_KEY,
                    "Content-Type: application/json"
                ),
            ));
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "Impossible d'envoyer le fichier à Yousign. Erreur #:" . $err;
            exit();
        }


        $json = json_decode($response, true);


        // https://placeit.yousign.fr/
        if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
            $pt_to_mm = 0.352778;
            $height_paper = round(297 / $pt_to_mm);
            $width_paper = round(210 / $pt_to_mm);
            $documents_ids[] = $json['id'];
            if ($name == "devis") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $x_y_positions = explode(',', $positions[$name][$key]);
                    $x = intval($x_y_positions[0]);
                    $y = $height_paper - $x_y_positions[3];
                    $width = $x_y_positions[2] - $x_y_positions[0];
                    $documents[] = [
                        "document_id" => $json['id'],
                        "type" => "signature",
                        "page" => $page,
                        "width" => $width,
                        "x" => $x,
                        "y" => $y + 10
                    ];
                    $documents[] = [
                        "document_id" => $json['id'],
                        "type" => "mention",
                        "mention" => "%date%",
                        "page" => $page,
                        "x" => $x + 20,
                        "y" => $y - 5
                    ];
                    $documents[] = [
                        "document_id" => $json['id'],
                        "type" => "mention",
                        "mention" => $signerDatas['firstname'] . " " . $signerDatas['lastname'] . " - Bon pour Accord",
                        "page" => $page,
                        "x" => $x,
                        "y" => $y + 40
                    ];
                }
            } else {
                if (isset($positions[$name])) {
                    $x_y_positions = explode(',', $positions[$name]);
                    if (is_array($x_y_positions) && count($x_y_positions) >= 4) {
                        $x = intval($x_y_positions[0]);
                        $y = $height_paper - intval($x_y_positions[3]);
                        $width = intval($x_y_positions[2]) - $x;
                    } else {
                        $x = null;
                        $y = null;
                        $width = null;
                    }

                    if ($name == "mandat_administratif_financier" || $name == "mandat_administratif" || $name == "mandat_financier") {
                        $documents[] = [
                            "document_id" => $json['id'],
                            "type" => "mention",
                            "mention" => "%date%",
                            "page" => $page_numbers[$name],
                            "x" => 76, //$x + 100,
                            "y" => 610 //$y - 48
                        ];
                    } else if ($name == 'attestation_tva') {
                        $documents[] = [
                            "document_id" => $json['id'],
                            "type" => "mention",
                            "mention" => "%date%",
                            "page" => $page_numbers[$name],
                            "x" => $x + 130,
                            "y" => $y - 40
                        ];
                    }
                    // else if($name != 'attestation_consentement'){
                    //     $documents[] = [
                    //         "document_id" => $json['id'],
                    //         "type" => "mention",
                    //         "mention" => "%date%",
                    //         "page" => $page_numbers[$name],
                    //         "x" => $x + 30,
                    //         "y" => $y
                    //     ];
                    // }
                    else if ($name == "subvention") {
                        $documents[] = [
                            "document_id" => $json['id'],
                            "type" => "mention",
                            "mention" => "%date%",
                            "page" => $page_numbers[$name],
                            "x" => $x,
                            "y" => $y + 45
                        ];
                    } else if ($name == "mandat_special_le") {
                        foreach ($page_numbers[$name] as $key => $page) {
                            $documents[] = [
                                "document_id" => $json['id'],
                                "type" => "signature",
                                "page" => $page,
                                "width" => 162,
                                "height" => 78,
                                "x" => 355,
                                "y" => 664
                            ];
                            if ($key == 0) {
                                $mention_x = 362;
                                $mention_y = 570;
                            } else {
                                $mention_x = 318;
                                $mention_y = 626;
                            }
                            $documents[] = [
                                "document_id" => $json['id'],
                                "type" => "mention",
                                "mention" => "%date%",
                                "page" => $page,
                                "x" => $mention_x,
                                "y" => $mention_y
                            ];
                        }
                    }

                    if (!is_array($page_numbers[$name])) {
                        $documents[] = [
                            "document_id" => $json['id'],
                            "type" => "signature",
                            "page" => $page_numbers[$name],
                            "width" => $width,
                            "x" => $x,
                            "y" => $y
                        ];
                    }
                }
            }
        } else {
            if ($name == "devis") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "mention2" => $signerDatas['firstname'] . " " . $signerDatas['lastname'] . " - Bon pour Accord",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } else if ($name == "liste_travaux_preconises" && $need_offre_amo_et_liste_travaux_preconises = 1) {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "mention2" => $signerDatas['firstname'] . " " . $signerDatas['lastname'],
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } /* else if($name == "offre_amo") {
              foreach ($page_numbers[$name] as $key => $page) {

                  $documents['client'][] = [
                      "mention" => "{date.fr}",
                      "position" => $positions[$name][$key],
                      "page" => $page,
                      "file" => $json['id']
                  ];
                  $documents['installateur'][] = [
                      "mention" => "{date.fr}",
                      "position" => $positions[$name][$key],
                      "page" => $page,
                      "file" => $json['id']
                  ];
                  $documents['amo'][] = [
                      "mention" => "{date.fr}",
                      "position" => $positions[$name][$key],
                      "page" => $page,
                      "file" => $json['id']
                  ];

              }
          } */ else if ($name == "amo") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } else if ($name == "doc_leader") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } else if ($name == "mandat_sibel1") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } else if ($name == "mandat_sibel2") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } else if ($name == "mandat_sibel3") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } else if ($name == "doc_planitis") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            } else if ($name == "procuration") {
                foreach ($page_numbers[$name] as $key => $page) {
                    $documents[] = [
                        "mention" => "{date.fr}",
                        "position" => $positions[$name][$key],
                        "page" => $page,
                        "file" => $json['id']
                    ];
                }
            }
            /*else if($name == "mandat_administratif_financier" || $name == "mandat_financier" || $name == "mandat_administratif") {
              error_log('name_mandat = '.$name);
              error_log(print_r($page_numbers, true));
              error_log(print_r($positions, true));

              foreach ($page_numbers[$name] as $key => $page) {

                error_log('city = '.$signerDatas['city']);
                error_log('page = '.$page);
                error_log('json_id = '.$json['id']);

                  $documents[] = [
                    "mention" => "{date.fr}",
                    "mention2" => $signerDatas['city'],
                    "position" => $positions[$name][$key],
                    "page" => $page,
                    "file" => $json['id']
                  ];
              }
          }*/ else {
                $documents[] = [
                    "mention" => "{date.fr}",
                    "position" => $positions[$name],
                    "page" => $page_numbers[$name],
                    "file" => $json['id']
                ];
            }
        }
        $i++;
    }

    $curl = curl_init();

    $procedure_finished_email = (defined('EMAILS_SUIVI_DOSSIERS') && is_array(EMAILS_SUIVI_DOSSIERS) && !empty(EMAILS_SUIVI_DOSSIERS) && !in_array("", EMAILS_SUIVI_DOSSIERS)) ? '
                      {
                          "subject": "[YOUSIGN] ' . $signerDatas['firstname'] . ' ' . $signerDatas['lastname'] . ' viens de signer les documents.",
                          "message":"' . $signerDatas['firstname'] . ' ' . $signerDatas['lastname'] . ' (' . $signerDatas['phone'] . ') viens de signer les documents. Cliquez ici pour y accéder : <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accèder aux documents\">Accèder aux documents</tag><br><br>Très cordialement,<br>' . SOCIETE . '.",
                          "to": ' . json_encode(EMAILS_SUIVI_DOSSIERS) . '
                      },' : '';
    $procedure_finished_email .= '{
                          "subject": "Documents signés avec succès !",
                          "message": "Bonjour <tag data-tag-type=\"string\" data-tag-name=\"recipient.firstname\"></tag> <tag data-tag-type=\"string\" data-tag-name=\"recipient.lastname\"></tag>, <br><br> Vos documents ont bien été signés éléctroniquement. Cliquez ici pour y accéder : <tag data-tag-type=\"button\" data-tag-name=\"url\" data-tag-title=\"Accèder aux documents\">Accèder aux documents</tag><br><br>Très cordialement,<br>' . SOCIETE . '.",
                          "to": ["@member"]
                      }';

    $members_arb = '';

    /*
    $members_arb .=  '{
                  "firstname": "' . $signerDatas['client']['firstname'] . '",
                  "lastname": "' . $signerDatas['client']['lastname'] . '",
                  "email": "' . $signerDatas['client']['email'] . '",
                  "phone": "' . $signerDatas['client']['phone'] . '",
                  "fileObjects": ' . json_encode($documents['client']) . '
              }';
    */

    $members_arb .=  '{
                    "firstname": "' . $signerDatas['firstname'] . '",
                    "lastname": "' . $signerDatas['lastname'] . '",
                    "email": "' . $signerDatas['email'] . '",
                    "phone": "' . $signerDatas['phone'] . '",
                    "fileObjects": ' . json_encode($documents) . '
                    }';

    /*
    foreach ($signerDatas as $key => $value) {
        if ($key != 'client') {
            $members_arb .= ',
                  {
                      "firstname": "' . $value['firstname'] . '",
                      "lastname": "' . $value['lastname'] . '",
                      "email": "' . $value['email'] . '",
                      "phone": "' . $value['phone'] . '",
                      "fileObjects": ' . json_encode($documents[$key]) . '
                  }';
        }
    }
    */

    // CREATE A NEW PROCEDURE
    if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
        $expires_at = $ex_date ? '"expiration_date": "' . $ex_date . '",' : "";
        $post = '{
            "name": "Liste des documents a signer par le client",
            "delivery_mode": "none",
            "external_id": "DEVIS_' . $devis_id . '",
            "timezone": "Europe/Paris",
            "email_custom_note": "Veuillez signer les documents suivants.",
            ' . $expires_at . '
            "documents": ' . json_encode($documents_ids) . ',
            "signers": [
                {
                    "info": {
                        "first_name": "' . $signerDatas['firstname'] . '",
                        "last_name": "' . $signerDatas['lastname'] . '",
                        "email": "' . $signerDatas['email'] . '",
                        "phone_number": "' . $signerDatas['phone'] . '",
                        "locale": "fr"
                    },
                    "signature_level": "electronic_signature",
                    "signature_authentication_mode": "otp_sms",
                    "custom_text": {
                        "request_subject": "Vous êtes invité à signer vos documents",
                        "request_body": "Veuillez signer les documents suivants.",
                        "reminder_subject": "Rappel : Vous n\'avez pas enocre signé vos documents.",
                        "reminder_body": "Veuillez signer les documents suivants."
                    },
                    "fields": ' . json_encode($documents) . '
                }
            ]
        }';

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://' . YOUSIGN_API_URL . '/signature_requests',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . YOUSIGN_API_KEY,
                "Content-Type: application/json"
            )
        ));
    } else {

        $expires_at = $ex_date ? '"expiresAt": "' . $ex_date . '",' : "";
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://' . YOUSIGN_API_URL . "/procedures",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => '{
            "name": "Documents a signer",
            "description": "Liste des documents a signer par le client",
            ' . $expires_at . '
            "start" : true,
            "members": [
              ' . $members_arb . '              
            ],
            "operationLevel": "advanced",
            "config": {
                "email": {
                    "procedure.finished": [
                      ' . $procedure_finished_email . '
                    ]
                },
                "webhook": {
                    "procedure.started": [
                        {
                            "url": "' . $webhook_url . '",
                            "method": "GET",
                            "headers": {
                                "X-Yousign-Custom-Header": "Yousign Webhook - Procedure Started"
                            }
                        }
                    ],
                    "procedure.finished": [
                        {
                            "url": "' . $webhook_url . '",
                            "method": "GET",
                            "headers": {
                                "X-Yousign-Custom-Header": "Yousign Webhook - Procedure Finished"
                            }
                        }
                    ],
                    "procedure.refused": [
                        {
                            "url": "' . $webhook_url . '",
                            "method": "GET",
                            "headers": {
                                "X-Yousign-Custom-Header": "Yousign Webhook - Procedure Refused"
                            }
                        }
                    ],
                    "procedure.expired": [
                        {
                            "url": "' . $webhook_url . '",
                            "method": "GET",
                            "headers": {
                                "X-Yousign-Custom-Header": "Yousign Webhook - Procedure Expired"
                            }
                        }
                    ],
                    "procedure.deleted": [
                        {
                            "url": "' . $webhook_url . '",
                            "method": "GET",
                            "headers": {
                                "X-Yousign-Custom-Header": "Yousign Webhook - Procedure Deleted"
                            }
                        }
                    ]
                }
            }
        }',
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . YOUSIGN_API_KEY,
                "Content-Type: application/json"
            ),
        ));
    }

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
        exit();
    } else {
        if (!defined('IS_YOUSIGN_V3') || IS_YOUSIGN_V3 != 1)
            return $response;
    }

    if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
        $json = json_decode($response, true);

        $curl = curl_init();

        if (!isset($json['id'])) {
            echo "Impossible de créer la procédure de signature. Erreur #:" . $response;
            exit();
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://' . YOUSIGN_API_URL . '/signature_requests/' . $json['id'] . '/activate',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . YOUSIGN_API_KEY
            )
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
            exit();
        } else {
            return $response;
        }
    }
};

$msg = "";
$error = true;

function send_email($sender_nom, $sender_email, $real_sender_email, $body_email, $body_tel, $recipient_gender_nom_prenom, $recipient_email, $attestationConsentementString, $mandatString_administratif_financier, $mandatString_administratif, $mandatString_financier, $tvaString, $ccString, $subventionString, $lettredevisString, $docLeaderString, $procurationString, $mandat_sibel1String, $mandat_sibel2String, $mandat_sibel3String, $amoString, $refusamoString, $mandat_special_leString, $mandat1_asc2_String, $mandat2_asc2_String, $lettreengagementString, $pdfString, $fichetechs_array, $signature_url, $signature, $path, $is_subvention, $nom_subvention, $pdf_document_mprString, $nom_document_mpr, $need_copy_mail, $devis_date_req, $num_mpr2)
{
    global $msg;
    global $error;

    $email = new PHPMailer\PHPMailer\PHPMailer();
    try {
        if (defined('SMTP') && !empty(SMTP)) {
            $email->IsSMTP();
            $email->Host = SMTP;
            $email->SMTPAuth = true;
            $email->Username = EMAIL;
            $email->Password = EMAIL_PWD;
            if ((defined('SMTP_SECURE') && !empty(SMTP_SECURE)))
                $email->SMTPSecure = SMTP_SECURE;
            if ((defined('SMTP_PORT') && !empty(SMTP_PORT)))
                $email->Port = SMTP_PORT;

            // Errors debug : https://stackoverflow.com/questions/21937586/phpmailer-smtp-error-password-command-failed-when-send-mail-from-my-server
            // $email->SMTPDebug  = 1;  
        }


        // setings
        $email->CharSet = 'UTF-8';
        $email->Encoding = 'base64';
        $email->Sender = $sender_email;

        //Recipients
        $email->ClearReplyTos();
        $email->addReplyTo($sender_email, $sender_nom);
        $email->setFrom($sender_email, $sender_nom); //Name is optional
        $email->addAddress($recipient_email);

        //Hidden Recipients
        if (strpos($path, 'leader-energie') == false || strpos($path, 'agir-ecologie') == false) {
            $email->AddBcc(EMAIL);
        }

        if ($need_copy_mail and isset($_SESSION['user_power']) and ($_SESSION['user_power'] >= 50)) {
            $email->AddBcc($real_sender_email);
        }

        if ($mandatString_administratif_financier == 0 && $mandatString_administratif == 0 && $mandatString_financier == 0) {
            $mandatString = 0;
        } else {
            $mandatString = 1;
        }
        //Attachments
        $date_format = "d/m/Y H:i:s";
        $devis_date_c = DateTime::createFromFormat("Y-m-d H:i:s", $devis_date_req);
        $t1  = DateTime::createFromFormat($date_format, "01/05/2010 00:00:00");

        if ($devis_date_c < $t1) {
            if ($mandatString_administratif_financier) { //$is_mandat) {
                $email->addStringAttachment($mandatString_administratif_financier, 'mandat-administratif-financier.pdf');
            } else {
                if ($mandatString_administratif) { //$is_mandat) {
                    $email->addStringAttachment($mandatString_administratif, 'mandat-administratif.pdf');
                }
                if ($mandatString_financier) { //$is_mandat) {
                    $email->addStringAttachment($mandatString_financier, 'mandat-financier.pdf');
                }
            }
        } else {
            if ($num_mpr2 != NULL) {
                $email->addStringAttachment($attestationConsentementString, 'Attestation_de_consentement.pdf');

                if ($mandatString_administratif_financier) { //$is_mandat) {
                    $email->addStringAttachment($mandatString_administratif_financier, 'mandat-administratif-financier.pdf');
                } else {
                    if ($mandatString_administratif) { //$is_mandat) {
                        $email->addStringAttachment($mandatString_administratif, 'mandat-administratif.pdf');
                    }
                    if ($mandatString_financier) { //$is_mandat) {
                        $email->addStringAttachment($mandatString_financier, 'mandat-financier.pdf');
                    }
                }
            }
        }

        if (strpos($path, 'futurenv') == true) {
            $email->addStringAttachment($procurationString, 'Procuration.pdf');
        }

        if ($is_subvention) {
            if (strpos($path, 'ghe') == true) {
                $email->addStringAttachment($lettredevisString, 'lettre_fond_solidarite.pdf');
            } else {
                if (strpos($path, 'doovision') == true) {
                    $email->addStringAttachment($lettredevisString, 'confirmation-des-aides.pdf');
                }
                $email->addStringAttachment($subventionString, $nom_subvention . '.pdf');
            }
        } else {
            if (strpos($path, 'efe') == true) {
                $email->addStringAttachment($lettredevisString, 'lettre_confirmation_devis.pdf');
            }
        }

        if (strpos($path, 'le.26770') == true) {
            $certificat_pv = $path . "/espace-rac/" . 'upload/certificats/certificatPV.pdf';
            $certificatString = file_get_contents($certificat_pv);
            $email->addStringAttachment($certificatString, 'Certificat PV.pdf');

            $assurance_decennale = $path . "/espace-rac/" . 'upload/certificats/Assurance_decennale.pdf';
            $assurance_decennaleString = file_get_contents($assurance_decennale);
            $email->addStringAttachment($assurance_decennaleString, 'Assurance decennale.pdf');
        }

        if ($pdf_document_mprString) {
            $email->addStringAttachment($pdf_document_mprString, $nom_document_mpr . ".pdf");
        }

        if ($amoString) {
            $email->addStringAttachment($amoString, 'contrat_amo.pdf');
        }

        if ($refusamoString) {
            $email->addStringAttachment($refusamoString, 'refus_amo.pdf');
        }

        if ($mandat_special_leString) {
            $email->addStringAttachment($mandat_special_leString, 'mandat_special.pdf');
        }

        if ($mandat1_asc2_String) {
            $email->addStringAttachment($mandat1_asc2_String, 'mandat_representation.pdf');
        }
        if ($mandat2_asc2_String) {
            $email->addStringAttachment($mandat2_asc2_String, 'mandat_special.pdf');
        }

        if ($lettreengagementString) {
            $email->addStringAttachment($lettreengagementString, 'lettre_engagement.pdf');
        }

        if ($tvaString) { // TVA 5.5% ou 10
            $email->addStringAttachment($tvaString, 'attestation_tva.pdf');
        }

        if ($ccString) {
            $email->addStringAttachment($ccString, 'cadre_contribution.pdf'); //$ccString est déclaré dans inc/cc.php
        }

        if (strpos($path, 'leader-energie') == true && $prime_anah) {
            $email->addStringAttachment($docLeaderString, 'document_eco_energy.pdf');
        }

        if (strpos($path, 'sibel-energie') == true && $prime_anah) {
            $email->addStringAttachment($mandat_sibel1String, 'Mandat_Gestionnaire_de_reseau.pdf');
            $email->addStringAttachment($mandat_sibel2String, 'Mandat_de_demarches_primes_CEE.pdf');
            $email->addStringAttachment($mandat_sibel3String, 'Mandat_dassistance_administrative_(urbanisme_Consuel).pdf');
        }


        if (strpos($path, 'asc2') == true) {
            $email->addStringAttachment($pdfString, 'bon_commande.pdf');
        } else {
            $email->addStringAttachment($pdfString, 'devis.pdf');
        }



        $cpt_fichetech = 0;
        $cpt_linkfichetech = 0;
        $link_fichetech = [];
        foreach ($fichetechs_array as $k1 => $v1) {
            $cpt_fichetech += 1;
            if (!empty($v1['nom_unique'])) {
                $file_to_attach = $path . "/espace-rac/" . 'upload/fiches_techniques/' . $v1['nom_unique'];
                $email->addAttachment($file_to_attach, $v1['nom'] . '.pdf');
            }
            if (!empty($v1['fiche_link'])) {
                $cpt_linkfichetech += 1;
                $link_fichetech[] = $v1['fiche_link'];
            }
        }

        // rendre générique la possibilité de pouvoir ajouter N documents en PJ dans les emails
        if (strpos($path, 'sibel-energie') == true) {
            $plaquette = $path . "/espace-rac/" . 'upload/SIBEL-PLAQUETTE-PAC.pdf';
            $plaquetteString = file_get_contents($plaquette);
            $email->addStringAttachment($plaquetteString, 'SIBEL-PLAQUETTE-PAC.pdf');
        }

        //Content
        $email->isHTML(true);
        $email->Subject   = SOCIETE . " - Votre projet Habitation";

        if (strpos($path, 'leader-energie') == false) {
            $body = $recipient_gender_nom_prenom . ",<br /><br />";
            if ($mandatString) { //$is_mandataire_anah && $prime_anah_finale) {
                $body .= "Je vous prie de bien vouloir trouver votre <strong>mandat</strong> qui devra être signé par vos soins (en cliquant sur le lien en fin de mail), nous permettant d'effectuer en votre nom et pour votre compte les démarches administratives, afin d'obtenir les différentes aides annoncées.";
                $body .= "<br /><br />";
                $body .= "Vous trouverez également le <strong>devis</strong> qui devra être signé par vos soins (en cliquant sur le lien en fin de mail).<br />";
                if ($need_cc) {
                    $body .= "Le devis est accompagné du cadre de contribution que nous vous joignons à titre d'informations. Ce document indique le montant des aides liés aux certificats d'économies d'énergies (CEE).<br /><br />";
                } else {
                    $body .= "<br />";
                }
            } else {
                $body .= "Je vous prie de bien vouloir trouver votre <strong>devis</strong> qui devra être signé par vos soins (en cliquant sur le lien en fin de mail).<br />";
                if ($ccString) {
                    $body .= "Le devis est accompagné du cadre de contribution que nous vous joignons à titre d'informations. Ce document indique le montant des aides liés aux certificats d'économies d'énergies (CEE).<br /><br />";
                } else {
                    $body .= "<br />";
                }
                $body .= "Ainsi que l'attestation simplifiée pour la TVA applicable aux travaux dans les logements (Cerfa n° 13948*05) qui devra aussi être signée.<br /><br />";
            }

            if ($is_subvention) {
                $body .= "Veuillez également trouver en pièce jointe votre fiche de " . $nom_subvention . " pour pouvoir bénéficier de la remise qui vous a été accordée, il vous suffit pour cela de signer ce document le jour de l’installation.<br/><br/>";
            }

            $complement = '.<br /><br />';
            $egalement = ($cpt_fichetech > 0) ? 'également ' : '';
            if ($cpt_fichetech > 0) {
                if (!empty($link_fichetech)) {
                    if ($cpt_linkfichetech == 1) {
                        $complement = ' que vous pourrez ' . $egalement . 'trouver via le lien ci dessous : ';
                    } else {
                        $complement = ' que vous pourrez ' . $egalement . 'trouver via les liens ci dessous : ';
                    }
                    $complement .= "<br/>" . print_array($link_fichetech, 0);
                }
                if ($cpt_fichetech == 1) {
                    $body .= "Enfin, nous joignons à ce courriel la <strong>plaquette</strong> du produit choisi reprenant la totalité des caractéristiques techniques et fonctionnelles" . $complement;
                } else {
                    $body .= "Enfin, nous joignons à ce courriel les <strong>plaquettes</strong> des produits choisis reprenant la totalité des caractéristiques techniques et fonctionnelles" . $complement;
                }
            }

            $body .= "En cas de besoin ou pour toutes éventuelles questions, merci de nous contacter par téléphone au <strong>" . $body_tel . "</strong> ou par mail à " . $body_email . ", un technicien ne manquera pas de répondre à vos questions dans les meilleurs délais.<br /><br />";
            $body .= "Nous espérons que ce petit courriel explicatif vous donnera entière satisfaction et nous vous assurons mettre tout en œuvre pour que votre futur projet puisse devenir réalité.<br /><br />";

            $body .= '<center>
                    <table>
                        <tr>
                            <td align="center" bgcolor="#FFFFFF" class="appleLinkBodyTxt" style="padding:5px 0px 15px 0px; font-family:Helvetica, Arial, sans-serif; font-size:16px; line-height:20px; font-weight:normal;" valign="top">
                                <!--[if mso]>
                                  <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $signature_url . '" style="height:40px;v-text-anchor:middle;width:200px;" arcsize="10%" strokecolor="#497949" fillcolor="#5cb85c">
                                    <w:anchorlock/>
                                    <center style="color:#ffffff;font-family:sans-serif;font-size:13px;font-weight:bold;">Cliquer ici pour Signer les Documents</center>
                                  </v:roundrect>
                                <![endif]-->
                                <a href="' . $signature_url . '" style="background-color: #cce5ff;border: 1px solid #004085;border-radius:4px;color: #004085;display:inline-block;font-family:sans-serif;font-size:13px;font-weight:bold;line-height:40px;text-align:center;text-decoration:none;width:300px;-webkit-text-size-adjust:none;mso-hide:all;">Cliquer ici pour Signer les Documents</a>
                            </td>
						</tr>
					</table>';

            $body .= "<br /><br />Ou copier/coller le lien ci-dessous : <br />" . $signature_url . "</center><br /><br />";

            $body .= "Bonne réception,<br />";
            $body .= "<strong>Cordialement,</strong><br />";
            $email->Body = $body . "<br />" . $signature;
        } else if (strpos($path, 'ghe') == true) {
            $body = "<html>" . $recipient_gender_nom_prenom . ",<br /><br />";
            $body .= "Je vous prie de bien vouloir trouver ci-joint le devis.<br />";
            $body .= "En cas de besoin ou pour toutes éventuelles questions, merci de nous contacter par téléphone au " . $tel_body . " ou par mail à " . $email_body . ", un technicien ne manquera pas de répondre à vos questions dans les meilleurs délais.<br />";

            $body .= '<center>
                    <table>
                        <tr>
                            <td align="center" bgcolor="#FFFFFF" class="appleLinkBodyTxt" style="padding:5px 0px 15px 0px; font-family:Helvetica, Arial, sans-serif; font-size:16px; line-height:20px; font-weight:normal;" valign="top">
                                <!--[if mso]>
                                  <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $signature_url . '" style="height:40px;v-text-anchor:middle;width:200px;" arcsize="10%" strokecolor="#497949" fillcolor="#5cb85c">
                                    <w:anchorlock/>
                                    <center style="color:#ffffff;font-family:sans-serif;font-size:13px;font-weight:bold;">Cliquer ici pour Signer les Documents</center>
                                  </v:roundrect>
                                <![endif]-->
                                <a href="' . $signature_url . '" style="background-color: #cce5ff;border: 1px solid #004085;border-radius:4px;color: #004085;display:inline-block;font-family:sans-serif;font-size:13px;font-weight:bold;line-height:40px;text-align:center;text-decoration:none;width:300px;-webkit-text-size-adjust:none;mso-hide:all;">Cliquer ici pour Signer les Documents</a>
                            </td>
						</tr>
					</table>';

            $body .= "<br /><br />Ou copier/coller le lien ci-dessous : <br />" . $signature_url . "</center><br /><br />";

            $body .= "Bonne réception,<br />";
            $body .= "Cordialement,<br />";
            $email->Body = $body . "<br />" . $signature . "</html>";
        } else {

            $body = $recipient_gender_nom_prenom . ",<br /><br />";
            $body .= "Nos félicitations pour l'acceptation de votre dossier par Action Logement !<br />";
            $body .= "Avant de débuter les travaux vois la dernière étape a nous confirmer : <br />";
            $body .= "Comme convenu ensemble par téléphone, je vous prie de bien vouloir trouver ci joint le nouveau devis afin de préparer l'installation.<br />";
            $body .= "Nous vous prions de ne pas prendre en compte les éléments du devis et vous rassurons que l'installation sera bien faite conformément au devis signé en 2020 lors de l'instruction de votre dossier sur le portail d'action logement.<br />";
            $body .= "Ce devis a pour but UNIQUE  de pouvoir nous faire valoriser les certificat d'économies d'énergies qui ont vu une légère baisse sur l'année 2021.<br />";
            $body .= "Une fois votre devis mis a jour, notre service planning vous contactera dans les plus brèf délai afin de vous proposer une date d'installation <br />";
            $body .= "Nous restons à votre disposition pour toutes questions éventuelles. Veuillez nous contacter au 01 86 90 78 69 ou par mail à contact@leaderenergie.fr, nous ne manquerons pas de vous répondre dans les meilleurs délais.<br /><br />";
            $body .= "Nous espérons que ce courriel explicatif vous donne entière satisfaction et nous vous assurons de tout mettre en œuvre pour que projet puisse devenir réalité.";

            $body .= '<center>
                    <table>
                        <tr>
                            <td align="center" bgcolor="#FFFFFF" class="appleLinkBodyTxt" style="padding:5px 0px 15px 0px; font-family:Helvetica, Arial, sans-serif; font-size:16px; line-height:20px; font-weight:normal;" valign="top">
                                <!--[if mso]>
                                  <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $signature_url . '" style="height:40px;v-text-anchor:middle;width:200px;" arcsize="10%" strokecolor="#497949" fillcolor="#5cb85c">
                                    <w:anchorlock/>
                                    <center style="color:#ffffff;font-family:sans-serif;font-size:13px;font-weight:bold;">Cliquer ici pour Signer les Documents</center>
                                  </v:roundrect>
                                <![endif]-->
                                <a href="' . $signature_url . '" style="background-color: #cce5ff;border: 1px solid #004085;border-radius:4px;color: #004085;display:inline-block;font-family:sans-serif;font-size:13px;font-weight:bold;line-height:40px;text-align:center;text-decoration:none;width:300px;-webkit-text-size-adjust:none;mso-hide:all;">Cliquer ici pour Signer les Documents</a>
                            </td>
						</tr>
					</table>';

            $body .= "<br /><br />Ou copier/coller le lien ci-dessous : <br />" . $signature_url . "</center><br /><br />";

            $body .= "Bonne réception,<br />";
            $body .= "<strong>Cordialement,</strong><br />";
            $email->Body = $body . "<br />" . $signature;

            /*
        $body = $recipient_gender_nom_prenom. ",<br /><br />";
        if ($mandatString) {//$is_mandataire_anah && $prime_anah_finale) {
            $body .= "Comme convenu ensemble par téléphone, je vous prie de bien vouloir trouver ci joint votre <strong>mandat</strong> nous permettant d'effectuer en votre nom et pour votre compte les démarches administratives afin d'obtenir les différentes aides annoncées, <strong>l'attestation simplifiée pour la TVA</strong> applicable aux travaux dans les logements (Cerfa n° 13948*05) ainsi que votre <strong>Devis complet</strong> à signer électroniquement.";
            $body .= "<br /><br />";
        } else {
            $body .= "Comme convenu ensemble par téléphone, je vous prie de bien vouloir trouver ci joint, <strong>le contrat de l'AMO</strong> (Assistance à la Maîtrise d’Ouvrage), <strong>l'attestation simplifiée pour la TVA</strong> applicable aux travaux dans les logements (Cerfa n° 13948*05) ainsi que votre <strong>Devis complet</strong> à signer électroniquement afin que l'expert de l'AMO vous contacte dans les 3 à 5 jours pour venir effectuer votre diagnostic thermique à votre domicile.";
            $body .= "<br /><br />";
        }

        $body .= "Veuillez cliquer sur le lien en bas de page ou le copier/coller pour recevoir votre code sur votre téléphone mobile qui vous permet de signer électroniquement vos documents.<br /><br />";

        $body            .= "Afin de préparer votre dossier merci nous retourner par mail les documents suivants :<br />";
        $body            .= "TOUS LES DOCUMENTS DOIVENT ETRE LISIBLES ET ENVOYES PAR EMAIL à l’adresse suivante : " . $body_email. "<br /><br />";

        $body            .= "- CARTE D’IDENTITE DU DEMANDEUR <br />";
        $body            .= "- TAXE FONCIERE 2019 / 2020<br />";
        $body            .= "- AVIS D’IMPOTS 2019 / 2020<br />";
        $body            .= "- DERNIER BULLETIN DE SALAIRE EN DATE <br />";
        $body            .= "- LIVRET DE FAMILLE <br /><br />";

        $complement = '.<br /><br />';
        $egalement = ($cpt_fichetech > 0) ? 'également ' : '';
        if ($cpt_fichetech > 0) {
            if (!empty($link_fichetech)) {
                if ($cpt_linkfichetech == 1) {
                    $complement = ' que vous pourrez ' . $egalement . 'trouver via le lien ci dessous : ';
                } else {
                    $complement = ' que vous pourrez ' . $egalement . 'trouver via les liens ci dessous : ';
                }
                $complement .= "<br/>" . print_array($link_fichetech, 0);
            }
            if ($cpt_fichetech == 1) {
                $body .= "Enfin, nous joignons à ce courriel la <strong>plaquette</strong> du produit choisi reprenant la totalité des caractéristiques techniques et fonctionnelles" . $complement;
            } else {
                $body .= "Enfin, nous joignons à ce courriel les <strong>plaquettes</strong> des produits choisis reprenant la totalité des caractéristiques techniques et fonctionnelles" . $complement;
            }
        }

        $body .= "Nous restons à votre disposition pour toutes questions éventuelles. Veuillez nous contacter au " . $body_tel . " ou par mail à " . $body_email . ", nous ne manquerons pas de vous répondre dans les meilleurs délais.<br /><br />";
        $body .= "Nous espérons que ce courriel explicatif vous donne entière satisfaction et nous vous assurons de tout mettre en œuvre pour que projet puisse devenir réalité.<br /><br />";
        $body .= "<center><a href=" . $signature_url . ">Cliquer ici pour Signer les Documents</a><br /><br />Ou copier/coller le lien ci-dessous : <br />" . $signature_url . "</center><br /><br />";
        $body .= "Bonne réception,<br />";
        $body .= "<strong>Cordialement,</strong><br />";
        $email->Body = $body . "<br />" . $signature;
*/
        }

        if ($email->send()) {
            $msg .= "Devis envoye et pret a etre signe par le client.";
            $error = false;
        } else {
            $msg .= "Erreur lors de l'envoi du devis : " . $email->ErrorInfo;
        }
    } catch (Exception $e) {
        $msg .= "Erreur lors de l'envoi du devis : " . $email->ErrorInfo;
    }
}

if (empty($_SESSION['user_id']) or !$_SESSION['user_id'] or empty($_POST['devis_id'])) {
    //print_r($_POST);
    echo json_encode("Vous n'avez pas accès à cette fonctionnalité.");
    exit();
} else {
    //always require config file
    require_once($path . "/espace-rac/" . 'inc/db.php');
    $devis_id = $_POST['devis_id'];
    if ($_SESSION['user_power'] < 100) {
        // Cet utilisateu n'a pas de privilèges administrateurs
        $dbh = db_connect();
        $stmt = $dbh->prepare("SELECT c.user_id as user_id, c.telepro_id as telepro_id, c.commercial_id as commercial_id FROM rac_client c LEFT JOIN rac_devis d ON d.client_id = c.id WHERE d.id = :devis_id");
        $stmt->execute(array(
            'devis_id' => $devis_id
        ));
        $result = $stmt->fetch();
        $stmt->closeCursor();
        if (($result['user_id'] != $_SESSION['user_id']) && ($result['telepro_id'] != $_SESSION['user_id']) && ($result['commercial_id'] != $_SESSION['user_id'])) {
            // Cet utilisateur n'a pas créé le client
            echo json_encode("Vous ne pouvez faire signer de devis qu'à vos clients.");
            exit();
        }
    } else {
        $dbh = db_connect();
    }


    if (!defined("YOUSIGN_API_KEY") or YOUSIGN_API_KEY == null or YOUSIGN_API_KEY == '') {
        echo json_encode("Vous n'avez pas de cle API yousign. Veuillez vous creer un compte sur yousign, generer une cle API et la renseigner dans le parametre YOUSIGN_API_KEY de l'ecran de parametrage : " . YOUSIGN_API_KEY);
        exit();
    }


    require($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/fpdm/fpdm.php');
    require $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/PHPMailer/src/Exception.php';
    require $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/PHPMailer/src/PHPMailer.php';
    if (defined('SMTP') && !empty(SMTP))
        require $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'vendor/PHPMailer/src/SMTP.php';

    $positions = array();

    $sql = file_get_contents('./include/_devis_req_reel.sql');

    $statement = $dbh->prepare($sql);
    $statement->execute(array(
        'devis_id' => $devis_id
    ));

    if ($result = $statement->fetchAll()) {
        require_once($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'include/devis_req.php');

        $sender_email = $user_email_to_sender ? $user_email : EMAIL;
        $body_email = $user_email_to_body ? $user_email : EMAIL;
        $body_tel = $user_tel_to_body ? $user_telephone : TEL;

        # Important la signature doit être mise après "devis_req"
        try {
            $signature = include($_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'include/signatures.php');
        } catch (Exception $e) {
            $signature = SOCIETE . "<br /><br /><div style='color: #A9A9A9;'>" . DENOMINATION_SOCIALE . "<br />" . ADRESSE . " " . CODE_POSTAL . " " . VILLE . "<br />Siret " . SIRET . " - NAF " . NAF . "<br />Capital : " . CAPITAL . "</div>";
        }

        $documents_dir = $path . "/espace-rac/" . 'upload/documents/';
        if (!file_exists($documents_dir)) {
            mkdir($documents_dir, 0777, true);
        }

        if (strpos($path, 'asc2') == true) {
            $fichier = $documents_dir . 'bon-commande-' . $db_devis_id . '-' . $client_uuid . '.pdf';
            if (!file_exists($fichier)) {
                $file_path = getOverridedFiles('include/bon_commande2.php');
                require_once($file_path);
                $pdf->Output($fichier, 'f');
                $pdfString = $pdf->Output($fichier, "S");
            } else {
                if ($handle = fopen($fichier, 'r')) {
                    fclose($handle);
                    $pdfString = file_get_contents($fichier);
                } else {
                    echo json_encode("Bon de commande indisponible pour l'instant merci de patienter et de recommencer plus tard.");
                    exit();
                }
            }
        } else {
            $fichier = $documents_dir . 'devis-' . $db_devis_id . '-' . $client_uuid . '.pdf';
            if (!file_exists($fichier)) {
                $file_path = getOverridedFiles('include/devis2.php');
                require_once($file_path);
                $pdf->Output($fichier, 'f');
                $pdfString = $pdf->Output($fichier, "S");
            } else {
                if ($handle = fopen($fichier, 'r')) {
                    fclose($handle);
                    $pdfString = file_get_contents($fichier);
                } else {
                    echo json_encode("Devis indisponible pour l'instant merci de patienter et de recommencer plus tard.");
                    exit();
                }
            }
        }


        //$file_mandat = $path . "/espace-rac/" . 'templates/mandat-' . PERCEPTEUR_NAME . '.pdf';
        //$file_mandat = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/mandat_ville.pdf';
        //$is_mandat = $is_mandataire_anah && $prime_anah_finale && file_exists($file_mandat);

        $date_format = "d/m/Y H:i:s";
        $devis_date_c = DateTime::createFromFormat("Y-m-d H:i:s", $devis_date_req);
        $t1  = DateTime::createFromFormat($date_format, "01/05/2010 00:00:00");

        if ($devis_date_c >= $t1) {
            $file_mandat = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/mandat_ville_062022.pdf';
        } else {
            $file_mandat = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/mandat_ville.pdf';
        }


        $file_subvention = $path . "/espace-rac/" . 'templates/subvention-' . PERCEPTEUR_NAME . '.pdf';

        $is_mandat_administratif_financier = 0;
        $is_mandat_administratif = 0;
        $is_mandat_financier = 0;

        if ($mandataire_mpr_administratif_id != null && $mandataire_mpr_financier_id != null) {
            if ($mandataire_mpr_administratif_id == $mandataire_mpr_financier_id) {
                if ($is_mandataire_anah && $prime_anah_finale) {
                    $is_mandat_administratif_financier = 1;
                    $is_mandat_administratif = 0;
                    $is_mandat_financier = 0;
                } else {
                    $is_mandat_administratif_financier = 0;
                    $is_mandat_administratif = 1;
                    $is_mandat_financier = 0;
                }
            } else {
                if ($is_mandataire_anah && $prime_anah_finale) {
                    $is_mandat_administratif_financier = 0;
                    $is_mandat_administratif = 1;
                    $is_mandat_financier = 1;
                } else {
                    $is_mandat_administratif_financier = 0;
                    $is_mandat_administratif = 1;
                    $is_mandat_financier = 0;
                }
            }
        } else {
            if ($mandataire_mpr_administratif_id != null) {
                $is_mandat_administratif_financier = 0;
                $is_mandat_administratif = 1;
                $is_mandat_financier = 0;
            }
            if ($mandataire_mpr_financier_id != null && $is_mandataire_anah && $prime_anah_finale) {
                $is_mandat_administratif_financier = 0;
                $is_mandat_administratif = 0;
                $is_mandat_financier = 1;
            }
        }

        if ($not_to_send_mpr_financier) {
            $is_mandat_financier = 0;
        }
        if ($not_to_send_mpr_administratif) {
            $is_mandat_administratif = 0;
        }

        if ($is_mandat_administratif_financier == 0 && $is_mandat_administratif == 0 && $is_mandat_financier == 0) {
            $is_mandat = 0;
        } else {
            $is_mandat = 1;
        }

        if ($num_mpr2 != NULL) {
            /*
          $fichier_consentement = $documents_dir . 'attestation_consentement-' . $db_devis_id . '-' . $client_uuid . '.pdf';
          if (!file_exists($fichier_consentement)) {
              $file_path = getOverridedFiles('include/attestation_consentement.php');
              require_once($file_path);
              $pdf->Output($fichier_consentement, 'f');
              $attestationConsentementString = $pdf->Output($fichier_consentement, "S");
          } else {
              $attestationConsentementString = file_get_contents( $fichier_consentement );
          }
          */
            $travaux1 = '';
            $travaux2 = '';
            $travaux3 = '';
            $siret1 = '';
            $siret2 = '';
            $siret3 = '';
            $installateur1 = '';
            $installateur2 = '';
            $installateur3 = '';


            $cpt_travaux = 0;
            $added_travaux = array();
            foreach ($pa as $k => $v) {
                foreach ($v['produits'] as $key => $produit) {
                    foreach ($produit as $kk => $value) {
                        $cpt_travaux += 1;
                        $key = $value['vente_nom'];
                        if (in_array($key, $added_travaux)) {
                            continue;
                        } else {
                            $added_travaux[] = $key;
                        }
                        if ($cpt_travaux == 1) {
                            $travaux1 = $value['vente_nom'];
                        }
                        if ($cpt_travaux == 2) {
                            $travaux2 = $value['vente_nom'];
                        }
                        if ($cpt_travaux == 3) {
                            $travaux3 = $value['vente_nom'];
                        }
                    }
                }
            }


            $cpt_installateurs = 0;
            $added_installateurs = array();
            foreach ($pa as $k => $v) {
                foreach ($v['produits'] as $key => $produit) {
                    foreach ($produit as $kk => $value) {
                        $cpt_installateurs += 1;
                        $key = $value['installateur']['nom'] . "-" . $value['installateur']['siret'];
                        if (in_array($key, $added_installateurs)) {
                            continue;
                        } else {
                            $added_installateurs[] = $key;
                        }
                        if ($cpt_installateurs == 1) {
                            $installateur1 = $value['installateur']['nom'];
                            $siret1 = $value['installateur']['siret'];
                        }
                        if ($cpt_installateurs == 2) {
                            $installateur2 = $value['installateur']['nom'];
                            $siret2 = $value['installateur']['siret'];
                        }
                        if ($cpt_installateurs == 3) {
                            $installateur3 = $value['installateur']['nom'];
                            $siret3 = $value['installateur']['siret'];
                        }
                    }
                }
            }


            if (isset($mandataire_mpr_administratif_id) && isset($mandataire_mpr_financier_id) && $mandataire_mpr_administratif_id != null && $mandataire_mpr_financier_id != null) {
                if ($mandataire_mpr_administratif_id == $mandataire_mpr_financier_id) {
                    if ($is_mandataire_anah == 1) {
                        $chk1 = 0;
                        $chk2 = 0;
                        $chk3 = 1;
                        $nom_mandataire = $raison_sociale_mpr_administratif;
                    } else {
                        $chk1 = 1;
                        $chk2 = 0;
                        $chk3 = 0;
                        $nom_mandataire = $raison_sociale_mpr_administratif;
                    }
                } else {
                    if ($is_mandataire_anah == 1) {
                        $chk1 = 1;
                        $chk2 = 0;
                        $chk3 = 0;
                        $nom_mandataire = $raison_sociale_mpr_administratif;
                    } else {
                        $chk1 = 1;
                        $chk2 = 0;
                        $chk3 = 0;
                        $nom_mandataire = $raison_sociale_mpr_administratif;
                    }
                }
            } else {
                if (isset($mandataire_mpr_administratif_id) && $mandataire_mpr_administratif_id != null) {
                    $chk1 = 1;
                    $chk2 = 0;
                    $chk3 = 0;
                    $nom_mandataire = $raison_sociale_mpr_administratif;
                }
                if (isset($mandataire_mpr_financier_id) && $mandataire_mpr_financier_id != null && $is_mandataire_anah == 1) {
                    $chk1 = 0;
                    $chk2 = 1;
                    $chk3 = 0;
                    $nom_mandataire = $raison_sociale_mpr_financier;
                }
            }
            if (!isset($mandataire_mpr_administratif_id) && !isset($mandataire_mpr_financier_id)) {
                $chk1 = 0;
                $chk2 = 0;
                $chk3 = 0;
                $nom_mandataire = '';
            }


            if (strlen($client_adresse . ' ' . $client_cp . ' ' . $client_ville) <= 63) {
                $adresse = $client_adresse . ' ' . $client_cp . ' ' . $client_ville;
                $cp = '';
                $ville = '';
            } else {
                $adresse = $client_adresse;
                $cp = $client_cp;
                $ville = $client_ville;
            }

            $fields = array(
                'NOM' => $nom,
                'PRENOM' => $prenom,
                'FULL_ADRESSE' => ($client_adresse_impots != NULL && $client_adresse_impots != '') ? $client_adresse_impots . ' ' . $client_cp_impots . ' ' . $client_ville_impots : $client_adresse . ' ' . $client_cp . ' ' . $client_ville,
                'ADRESSE' => $adresse,
                'CODE_POSTAL' => $cp,
                'VILLE' => $ville,
                'ANNEE_MPR' => substr($num_mpr1, -1),
                'NUMERO_MPR' => $num_mpr2,
                'PRODUIT_1' => $travaux1,
                'PRODUIT_2' => $travaux2,
                'PRODUIT_3' => $travaux3,
                'INSTALLATEUR_1' => $installateur1,
                'INSTALLATEUR_2' => $installateur2,
                'INSTALLATEUR_3' => $installateur3,
                'NUMERO_SIRET_1' => $siret1,
                'NUMERO_SIRET_2' => $siret2,
                'NUMERO_SIRET_3' => $siret3,
                'TOTAL_TTC' => number_format($prix_total_ttc, 2, ',', ' '),
                'RAC' => number_format($rac, 2, ',', ' '),
                'CHK_1' => $chk1,
                'CHK_2' => $chk2,
                'CHK_3' => $chk3,
                'VILLE_2' => $client_ville,
                //'DATE' => '',
                'JOUR' => date('d'),
                'MOIS' => date('m'),
                'ANNEE' => date('Y'),
                'NOM_PRENOM' => $nom . ' ' . $prenom,
                'NOM_MANDATAIRE' => $nom_mandataire
            );

            $file = $file = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/cerfa_attestation_consentement.pdf';

            $pdf = new Pdf($file);
            $pdf->fillForm($fields);
            $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf->flatten();
            if ($pdf->execute() === false) {
                $pdferror = $pdf->getError();
                $attestationConsentementString = file_get_contents($file);
            } else {
                $attestationConsentementString = file_get_contents((string) $pdf->getTmpFile());
            }
        }

        $is_subvention = $subvention > 0 && file_exists($file_subvention);


        if ($is_mandat_administratif_financier == 1) {
            $short_genre = $genre == "Monsieur" ? 'mr' : 'mme';
            $prop = $proprietaire == 0 ? "proprietaire" : "autre";

            $fields = array(
                'NOM'    => $nom,
                'PRENOM'   => $prenom,
                'ADRESSE' => $client_adresse,
                'VILLE' => $client_ville,
                'CODE_POSTAL' => $client_cp,
                'NOM_PRENOM' => $nom . ' ' . $prenom,
                'CIVILITE_NOM_PRENOM' => $genre . ' ' . $nom . ' ' . $prenom,
                'CIVILITE' => $genre,
                'CIVILITE_SHORT' => $short_genre,
                'PROPRIETAIRE_LIBELLE' => $prop,
                'CHK_PROPRIETAIRE'  => ($proprietaire == 0 ? 1 : 0),
                'CHK_LOCATAIRE'  => ($proprietaire == 2 ? 1 : 0),
                'CHK_RES_PRINCIPALE'  => ($client_residence_principale == 1 ? 1 : 0),
                'CHK_RES_LOCATIVE'  => ($client_residence_principale == 0 ? 1 : 0),
                'CHK_MR' => ($genre == "Monsieur" ? 1 : 0),
                'CHK_MME' => ($genre == "Monsieur" ? 0 : 1),
                'CHK_1' => 1,
                'CHK_2' => 1,
                'CHK_3' => 1,
                'CHK_4' => 1,
                'CHK_5' => 1,
                'EMAIL' => $client_email,
                'TEL' =>  $client_telephone,
                'mandataire_mr' => 'On',
                'mandataire_mr2' => 1,
                'mandataire_mme' => 0,
                'NOM_MANDATAIRE' =>  $mandataire_mpr_financier_nom,
                'PRENOM_MANDATAIRE' =>  $mandataire_mpr_financier_prenom,
                'RAISON_SOCIALE' =>  $raison_sociale_mpr_financier,
                'RAISON_SOCIALE_FIN' => (strlen($raison_sociale_mpr_financier . ' - ' . $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom) > 36) ? $raison_sociale_mpr_financier : $raison_sociale_mpr_financier . ' - ' . $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom,
                'RAISON_SOCIALE_FIN2' => (strlen($raison_sociale_mpr_financier . ' - ' . $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom) > 36) ? $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom : '',
                'NUM_DOSSIER_MPR_1' => $num_mpr1,
                'NUM_DOSSIER_MPR_2' => $num_mpr2,
                'ADRESSE_MANDATAIRE' =>  $mandataire_mpr_financier_adresse,
                'CP_MANDATAIRE' =>  $mandataire_mpr_financier_code_postal . ' ' . $mandataire_mpr_financier_ville,
                'CP2_MANDATAIRE' =>  $mandataire_mpr_financier_code_postal,
                'VILLE_MANDATAIRE' =>  $mandataire_mpr_financier_ville,
                'EMAIL_MANDATAIRE' =>  $mandataire_mpr_financier_email,
                'TEL_MANDATAIRE' =>  $mandataire_mpr_financier_tel
            );


            $pdf = new Pdf($file_mandat);
            $pdf->fillForm($fields);
            $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf->flatten();
            if ($pdf->execute() === false) {
                $pdferror = $pdf->getError();
                //echo json_encode($pdferror);
                //exit();
                // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                // L'AJOUTE QUAND MEME MAIS ON NE LE REMPLIE PAS
                $mandatString_administratif_financier = file_get_contents($file_mandat);
            } else {
                $pdf2 = new Fpdi();
                $pdf2->AddPage();
                $pdf2->setSourceFile((string) $pdf->getTmpFile());
                $tplIdx = $pdf2->importPage(1);
                $pdf2->useTemplate($tplIdx, 10, 10, 200);
                $pdf2->AddPage();
                $tplIdx = $pdf2->importPage(2);
                $pdf2->useTemplate($tplIdx, 10, 10, 200);
                $pdf2->Image($mandataire_mpr_financier_tampon, 126, 257, 60);
                //$pdf2->Output('newpdf1.pdf', 'I');
                $mandatString_administratif_financier = $pdf2->Output('', 'S');
            }
        } else {
            if ($is_mandat_administratif == 1) {
                $short_genre = $genre == "Monsieur" ? 'mr' : 'mme';
                $prop = $proprietaire == 0 ? "proprietaire" : "autre";

                $fields = array(
                    'NOM'    => $nom,
                    'PRENOM'   => $prenom,
                    'ADRESSE' => $client_adresse,
                    'VILLE' => $client_ville,
                    'CODE_POSTAL' => $client_cp,
                    'NOM_PRENOM' => $nom . ' ' . $prenom,
                    'CIVILITE_NOM_PRENOM' => $genre . ' ' . $nom . ' ' . $prenom,
                    'CIVILITE' => $genre,
                    'CIVILITE_SHORT' => $short_genre,
                    'PROPRIETAIRE_LIBELLE' => $prop,
                    'CHK_PROPRIETAIRE'  => ($proprietaire == 0 ? 1 : 0),
                    'CHK_LOCATAIRE'  => ($proprietaire == 2 ? 1 : 0),
                    'CHK_RES_PRINCIPALE'  => ($client_residence_principale == 1 ? 1 : 0),
                    'CHK_RES_LOCATIVE'  => ($client_residence_principale == 0 ? 1 : 0),
                    'CHK_MR' => ($genre == "Monsieur" ? 1 : 0),
                    'CHK_MME' => ($genre == "Monsieur" ? 0 : 1),
                    'CHK_1' => 1,
                    'CHK_2' => 0,
                    'CHK_3' => 1,
                    'CHK_4' => 1,
                    'CHK_5' => 1,
                    'EMAIL' => $client_email,
                    'TEL' =>  $client_telephone,
                    'mandataire_mr' => 'On',
                    'mandataire_mr2' => 1,
                    'mandataire_mme' => 0,
                    'NOM_MANDATAIRE' =>  $mandataire_mpr_administratif_nom,
                    'PRENOM_MANDATAIRE' =>  $mandataire_mpr_administratif_prenom,
                    'RAISON_SOCIALE' =>  $raison_sociale_mpr_administratif,
                    'RAISON_SOCIALE_FIN' => (strlen($raison_sociale_mpr_administratif . ' - ' . $mandataire_mpr_administratif_nom . ' ' . $mandataire_mpr_administratif_prenom) > 36) ? $raison_sociale_mpr_administratif : $raison_sociale_mpr_administratif . ' - ' . $mandataire_mpr_administratif_nom . ' ' . $mandataire_mpr_administratif_prenom,
                    'RAISON_SOCIALE_FIN2' => (strlen($raison_sociale_mpr_administratif . ' - ' . $mandataire_mpr_administratif_nom . ' ' . $mandataire_mpr_administratif_prenom) > 36) ? $mandataire_mpr_administratif_nom . ' ' . $mandataire_mpr_administratif_prenom : '',
                    'NUM_DOSSIER_MPR_1' => $num_mpr1,
                    'NUM_DOSSIER_MPR_2' => $num_mpr2,
                    'ADRESSE_MANDATAIRE' =>  $mandataire_mpr_administratif_adresse,
                    'CP_MANDATAIRE' =>  $mandataire_mpr_administratif_code_postal . ' ' . $mandataire_mpr_administratif_ville,
                    'CP2_MANDATAIRE' =>  $mandataire_mpr_administratif_code_postal,
                    'VILLE_MANDATAIRE' =>  $mandataire_mpr_administratif_ville,
                    'EMAIL_MANDATAIRE' =>  $mandataire_mpr_administratif_email,
                    'TEL_MANDATAIRE' =>  $mandataire_mpr_administratif_tel
                );


                $pdf = new Pdf($file_mandat);
                $pdf->fillForm($fields);
                $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf->flatten();
                if ($pdf->execute() === false) {
                    $pdferror = $pdf->getError();
                    //echo json_encode($pdferror);
                    //exit();
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // L'AJOUTE QUAND MEME MAIS ON NE LE REMPLIE PAS
                    $mandatString_administratif = file_get_contents($file_mandat);
                } else {
                    $pdf2 = new Fpdi();
                    $pdf2->AddPage();
                    $pdf2->setSourceFile((string) $pdf->getTmpFile());
                    $tplIdx = $pdf2->importPage(1);
                    $pdf2->useTemplate($tplIdx, 10, 10, 200);
                    $pdf2->AddPage();
                    $tplIdx = $pdf2->importPage(2);
                    $pdf2->useTemplate($tplIdx, 10, 10, 200);
                    $pdf2->Image($mandataire_mpr_administratif_tampon, 126, 257, 60);
                    //$pdf2->Output('newpdf1.pdf', 'I');
                    $mandatString_administratif = $pdf2->Output('', 'S');
                }
            }

            if ($is_mandat_financier == 1) {
                $short_genre = $genre == "Monsieur" ? 'mr' : 'mme';
                $prop = $proprietaire == 0 ? "proprietaire" : "autre";

                $fields = array(
                    'NOM'    => $nom,
                    'PRENOM'   => $prenom,
                    'ADRESSE' => $client_adresse,
                    'VILLE' => $client_ville,
                    'CODE_POSTAL' => $client_cp,
                    'NOM_PRENOM' => $nom . ' ' . $prenom,
                    'CIVILITE_NOM_PRENOM' => $genre . ' ' . $nom . ' ' . $prenom,
                    'CIVILITE' => $genre,
                    'CIVILITE_SHORT' => $short_genre,
                    'PROPRIETAIRE_LIBELLE' => $prop,
                    'CHK_PROPRIETAIRE'  => ($proprietaire == 0 ? 1 : 0),
                    'CHK_LOCATAIRE'  => ($proprietaire == 2 ? 1 : 0),
                    'CHK_RES_PRINCIPALE'  => ($client_residence_principale == 1 ? 1 : 0),
                    'CHK_RES_LOCATIVE'  => ($client_residence_principale == 0 ? 1 : 0),
                    'CHK_MR' => ($genre == "Monsieur" ? 1 : 0),
                    'CHK_MME' => ($genre == "Monsieur" ? 0 : 1),
                    'CHK_1' => 0,
                    'CHK_2' => 1,
                    'CHK_3' => 1,
                    'CHK_4' => 1,
                    'CHK_5' => 1,
                    'EMAIL' => $client_email,
                    'TEL' =>  $client_telephone,
                    'mandataire_mr' => 'On',
                    'mandataire_mr2' => 1,
                    'mandataire_mme' => 0,
                    'NOM_MANDATAIRE' =>  $mandataire_mpr_financier_nom,
                    'PRENOM_MANDATAIRE' =>  $mandataire_mpr_financier_prenom,
                    'RAISON_SOCIALE' =>  $raison_sociale_mpr_financier,
                    'RAISON_SOCIALE_FIN' => (strlen($raison_sociale_mpr_financier . ' - ' . $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom) > 36) ? $raison_sociale_mpr_financier : $raison_sociale_mpr_financier . ' - ' . $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom,
                    'RAISON_SOCIALE_FIN2' => (strlen($raison_sociale_mpr_financier . ' - ' . $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom) > 36) ? $mandataire_mpr_financier_nom . ' ' . $mandataire_mpr_financier_prenom : '',
                    'NUM_DOSSIER_MPR_1' => $num_mpr1,
                    'NUM_DOSSIER_MPR_2' => $num_mpr2,
                    'ADRESSE_MANDATAIRE' =>  $mandataire_mpr_financier_adresse,
                    'CP_MANDATAIRE' =>  $mandataire_mpr_financier_code_postal . ' ' . $mandataire_mpr_financier_ville,
                    'CP2_MANDATAIRE' =>  $mandataire_mpr_financier_code_postal,
                    'VILLE_MANDATAIRE' =>  $mandataire_mpr_financier_ville,
                    'EMAIL_MANDATAIRE' =>  $mandataire_mpr_financier_email,
                    'TEL_MANDATAIRE' =>  $mandataire_mpr_financier_tel
                );


                $pdf = new Pdf($file_mandat);
                $pdf->fillForm($fields);
                $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf->flatten();
                if ($pdf->execute() === false) {
                    $pdferror = $pdf->getError();
                    //echo json_encode($pdferror);
                    //exit();
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // L'AJOUTE QUAND MEME MAIS ON NE LE REMPLIE PAS
                    $mandatString_financier = file_get_contents($file_mandat);
                } else {
                    $pdf2 = new Fpdi();
                    $pdf2->AddPage();
                    $pdf2->setSourceFile((string) $pdf->getTmpFile());
                    $tplIdx = $pdf2->importPage(1);
                    $pdf2->useTemplate($tplIdx, 10, 10, 200);
                    $pdf2->AddPage();
                    $tplIdx = $pdf2->importPage(2);
                    $pdf2->useTemplate($tplIdx, 10, 10, 200);
                    $pdf2->Image($mandataire_mpr_financier_tampon, 126, 257, 60);
                    //$pdf2->Output('newpdf1.pdf', 'I');
                    $mandatString_financier = $pdf2->Output('', 'S');
                }
            }
        }


        $stmt_document = $dbh->prepare("SELECT `filename`, `uniq_name`, d.date as devis_date FROM `rac_document_upload` u LEFT JOIN rac_devis d ON d.mandataire_mpr_financier_id = u.item_id WHERE doc_type = :doc_type AND d.id = :devis_id");
        $stmt_document->execute(array('doc_type' => 'mandat_mpr', 'devis_id' => $devis_id));
        $result_document = $stmt_document->fetch();
        $has_result_document = $stmt_document->rowCount();
        $stmt_document->closeCursor();

        if ($has_result_document && !empty($result_document['uniq_name'])) {
            $need_document_mandat_mpr = true;
            $nom_document_mpr = $result_document['uniq_name'];
            $devis_date = $result_document['devis_date'];

            $fields_document = array(
                'NOM_PRENOM' => $nom . ' ' . $prenom,
                'NOM' => $nom,
                'PRENOM' => $prenom,
                'CHK_MR' => ($row['client_genre'] == 0) ? '1' : '0',
                'CHK_MME' => ($row['client_genre'] == 1) ? '1' : '0',
                'TEL' => $client_telephone,
                'EMAIL' => $client_email,
                'RAISON_SOCIALE' => SOCIETE,
                'RAISON_SOCIALE_FIN' =>  SOCIETE,
                'RAISON_SOCIALE_FIN2' => '',
                'NUM_DOSSIER_MPR_1' => $num_mpr1,
                'NUM_DOSSIER_MPR_2' => $num_mpr2,
                'ADRESSE_FULL' => $client_adresse . ' ' . $client_cp . ' ' . $client_ville,
                'ADRESSE' => $client_adresse,
                'VILLE' => $client_ville,
                'CODE_POSTAL' => $client_cp,
                'DATE' => $doc_date,
                'MONTANT_MPR' => $prime_anah_finale
            );

            $file_document = $path . "/espace-rac/" . "templates/" . $result_document['filename'];

            $pdf_document_mpr = new Pdf($file_document);
            $pdf_document_mpr->fillForm($fields_document);
            $pdf_document_mpr->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf_document_mpr->flatten();
            if ($pdf_document_mpr->execute() === false) {
                $pdferror = $pdf_document_mpr->getError();
                /*echo json_encode($pdferror);
                exit();*/
                // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                // L'AJOUTE QUAND MEME MAIS ON NE LE REMPLIE PAS
                $pdf_document_mprString = file_get_contents($file_document);
                //$need_amo = false;
            } else {
                $pdf_document_mprString = file_get_contents((string)$pdf_document_mpr->getTmpFile());
            }
        } else {
            $need_document_mandat_mpr = false;
            $nom_document_mpr = '';
        }

        if (strpos($path, 'futurenv') == true) {
            $file_procuration = $path . "/espace-rac/" . 'templates/procuration-' . PERCEPTEUR_NAME . '.pdf';
        }

        if (strpos($path, 'futurenv') == true) {
            $fields_procuration = array(
                'ADRESSE' => $client_adresse,
                'VILLE' => $client_ville,
                'CODE_POSTAL' => $client_cp,
                'NOM_PRENOM' => $nom . ' ' . $prenom,
                'EMAIL' => $client_email,
                'VILLE_SIGNATURE' => 'PARIS',
                'DATE_SIGNATURE' => date('d/m/Y')
            );

            $pdf = new Pdf($file_procuration);
            $pdf->fillForm($fields_procuration);
            $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf->flatten();
            if ($pdf->execute() === false) {
                $pdferror = $pdf->getError();
                //echo json_encode($pdferror);
                //exit();
                // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                // L'AJOUTE QUAND MEME MAIS ON NE LE REMPLIE PAS
                $procurationString = file_get_contents($file_procuration);
            } else {
                $procurationString = file_get_contents((string) $pdf->getTmpFile());
            }
        }

        if ($is_subvention) {

            $sql = "SELECT d.id AS devis_id, d.surface_logement AS surface_logement, DATE_FORMAT(`date_pre_visite`, '%d/%m/%Y') AS date_pre_visite, c.id AS client_id,  c.residence_principale AS client_residence_principale, c.genre AS client_genre, c.nom AS client_nom, c.prenom AS client_prenom, c.email as client_email, c.proprietaire as client_proprietaire, c.autre_habitat as client_autre_habitat, c.telephone as client_telephone, c.adresse as client_adresse, c.code_postal as client_cp, c.ville as client_ville, u.nom as user_nom, u.email as user_email, u.telephone as user_telephone, u.mobile as user_mobile, u.poste as user_poste
            FROM   rac_devis AS d
            LEFT JOIN rac_client AS c ON c.id = d.client_id
            LEFT JOIN rac_user AS u ON u.user_id = c.user_id
            WHERE  d.id = :devis_id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(
                'devis_id' => $devis_id
            ));


            $row = $stmt->fetch();
            $genre = $row['client_genre'] == 0 ? "Monsieur" : "Madame";
            $nom = $row['client_nom'];
            $prenom = $row['client_prenom'];
            $client_adresse = $row['client_adresse'];
            $client_cp = $row['client_cp'];
            $client_ville = $row['client_ville'];
            $client_email = $row['client_email'];
            $client_telephone = $row['client_telephone'];
            $short_genre = $genre == "Monsieur" ? 'mr' : 'mme';

            $fields_req1 = array(
                'NOM' => $nom,
                'PRENOM' => $prenom,
                'ADRESSE' => $client_adresse,
                'VILLE' => $client_ville,
                'CODE_POSTAL' => $client_cp,
                'NOM_PRENOM' => $nom . ' ' . $prenom,
                'EMAIL' => $client_email,
                'TEL_FIXE' => '',
                'TEL' => $client_telephone,
                'FAIT_A' => $client_ville
            );

            $fields_devis_req = array(
                'MONTANT_DEVIS' => number_format($prix_total_ttc, 2, ',', ' '),
                'MONTANT_SUBVENTION' => number_format($montant_subvention, 2, ',', ' '),
                'MONTANT_MPR' => number_format($prime_anah_finale, 2, ',', ' '),
                'MONTANT_CEE' => number_format($prime_cee_finale, 2, ',', ' '),
                'MONTANT_REMISE' => number_format($montant_subvention, 2, ',', ' '),
                'MONTANT_BONUS' => number_format($montant_subvention, 2, ',', ' '),
                'TOTAL_AIDES' => number_format($total_aides, 2, ',', ' '),
                'RAC' => number_format($rac, 2, ',', ' '),
                'RESTE_A_CHARGE' => number_format(($prix_total_ttc - $total_aides), 2, ',', ' '),
                'RESTE_A_CHARGE_APRES_SUBVENTION' => number_format(($rac - $montant_subvention), 2, ',', ' '),
                'NUM_DEVIS' => $doc_num,
                'INSTALLATEURS' => SOCIETE,
                'DATE' => $devis_date
            );

            $fields_totaux = array_merge($fields_req1, $fields_devis_req);

            if (strpos($path, 'ghe') == true || strpos($path, 'sashayno') == true || strpos($path, 'doovision') == true || strpos($path, 'efe') == true) {
                $lettre_confirmation_devis = $documents_dir . 'lettre_confirmation_devis-' . $db_devis_id . '-' . $client_uuid . '.pdf';
                if (!file_exists($lettre_confirmation_devis)) {
                    $file_path = getOverridedFiles('include/lettre_devis.php');
                    require_once($file_path);
                    $pdf->Output($lettre_confirmation_devis, 'f');
                    $lettredevisString = $pdf->Output($lettre_confirmation_devis, 'S');
                } else {
                    $lettredevisString = file_get_contents($lettre_confirmation_devis);
                }
            } else {
                $pdf = new Pdf($file_subvention);
                $pdf->fillForm($fields_totaux);
                $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf->flatten();
                if ($pdf->execute() === false) {
                    $pdferror = $pdf->getError();
                    //echo json_encode($pdferror);
                    //exit();
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // L'AJOUTE QUAND MEME MAIS ON NE LE REMPLIE PAS
                    $subventionString = file_get_contents($file_subvention);
                } else {
                    $subventionString = file_get_contents((string) $pdf->getTmpFile());
                }
            }

            if (strpos($path, 'doovision') == true) {
                $pdf = new Pdf($file_subvention);
                $pdf->fillForm($fields_totaux);
                $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf->flatten();
                if ($pdf->execute() === false) {
                    $pdferror = $pdf->getError();
                    //echo json_encode($pdferror);
                    //exit();
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // L'AJOUTE QUAND MEME MAIS ON NE LE REMPLIE PAS
                    $subventionString = file_get_contents($file_subvention);
                } else {
                    $subventionString = file_get_contents((string)$pdf->getTmpFile());
                }
            }
        }


        /* Gestion TVA */
        $dbh = db_connect();
        $stmt_tva = $dbh->prepare("SELECT tva FROM `rac_devis_metas` WHERE devis_id = :devis_id AND tva IN('5.50', '10.00') ORDER BY FIELD(tva, '5.50', '10.00')");
        $stmt_tva->execute(array(
            'devis_id' => $devis_id
        ));
        $result_tva = $stmt_tva->fetch();
        $stmt_tva->closeCursor();

        if (isset($result_tva['tva']) && $result_tva['tva'] != NULL && !empty($result_tva['tva'])) {
            $cac15 = (($result_tva['tva'] == 5.5) ? "Oui" : "Off");
            $need_tva = true;
            $fields = array(
                'a1'    => $nom,
                'a2'  => $prenom,
                'a3' => $client_adresse,
                'a5' => $client_cp,
                'a4' => $client_ville,
                'cac1' => $cac1, //maison=1, immeuble collectif=2, appartement individuel=3, autre=4
                'cac2' => 1, //habitation=1, 50% habitation=2, x% habitation=3, autre=4
                'cac3' => $cac3,
                'a10' => $proprietaire ? $autre_habitat : "",
                'a11' => $client_ville,
                'a12' => (strpos($path, 'mes-radiateurs-gratuits') == true) ? $devis_date : '',
                'cac4' => "Oui", // Off
                'cac5' => "Oui", // Off
                'cac12' => "Oui", // Off
                'cac13' => "Oui", // Off
                'cac14' => "Oui", // Off
                'cac15' => $cac15 //"Oui" ou "Off"
            );

            $file = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/cerfa_13948_05.pdf';

            $pdf = new Pdf($file);
            $pdf->fillForm($fields);
            $pdf->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf->flatten();
            if ($pdf->execute() === false) {
                $pdferror = $pdf->getError();
                //echo json_encode($pdferror);
                //exit();
                // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                $need_tva = false;
            } else {
                $tvaString = file_get_contents((string) $pdf->getTmpFile());
            }
        } else {
            $need_tva = false;
        }


        $need_cc = false;

        if ($pollueur_is_cc_pdf_editable == 1) {
            $outputString  = 1;
            $file_path = getOverridedFiles('include/cc.php');
            if ($is_aide_cee and $prime_cee_finale and $cadre_contribution != '') {
                if ($id_pollueur != NULL) {
                    $image_pollueur = $path . "/espace-rac/" . 'upload/agents/' . $id_pollueur . '/' . $logo_pollueur;
                    if (file_exists($image_pollueur) && file_exists($image_signataire)) {
                        require_once($file_path);
                        $need_cc = true;
                    }
                }
            }
        }


        /* Gestion AMO */
        $dbh = db_connect();
        $stmt_ficheamo = $dbh->prepare("SELECT uniq_name, signature_coord, page_number FROM rac_devis d LEFT JOIN rac_document_upload doc ON d.amo_id = doc.item_id AND doc.`doc_type` = 'amo' LEFT JOIN rac_amo a ON d.amo_id = a.id WHERE d.id = :devis_id");
        $stmt_ficheamo->execute(array(
            'devis_id' => $devis_id
        ));
        $result_ficheamo = $stmt_ficheamo->fetch();
        $stmt_ficheamo->closeCursor();

        $fields_amo = array(
            'NOM'    => $nom,
            'PRENOM'   => $prenom,
            'ADRESSE' => $client_adresse,
            'VILLE' => $client_ville,
            'CODE_POSTAL' => $client_cp,
            'NOM_PRENOM' => $nom . ' ' . $prenom,
            'CIVILITE_NOM_PRENOM' => $genre . ' ' . $nom . ' ' . $prenom,
            'CHK_PROPRIETAIRE'  => ($proprietaire == 0 ? 1 : 0),
            'CHK_LOCATAIRE'  => ($proprietaire == 2 ? 1 : 0),
            'CHK_RES_PRINCIPALE'  => ($client_residence_principale == 1 ? 1 : 0),
            'CHK_RES_LOCATIVE'  => ($client_residence_principale == 0 ? 1 : 0),
            'CHK_MR' => ($genre == "Monsieur" ? 1 : 0),
            'CHK_MME' => ($genre == "Monsieur" ? 0 : 1),
            'CHK_1' => 1,
            'CHK_2' => 1,
            'CHK_3' => 1,
            'CHK_4' => 1,
            'CHK_5' => 1,
            'EMAIL' => $client_email,
            'TEL' =>  $client_telephone,
            'DATE_PREVISITE' => $date_pre_visite,
            'DEVIS_DATE' => $devis_date,
            'DEVIS_EXP' => $devis_expiration->format('d/m/Y'),
            'NUM_DEVIS' => implode("", explode("-", explode(" ", $devis_expiration->format('Y-m-d'))[0])) . "-" . $devis_id,
            'MONTANT_AMO' => $cout_amo
        );

        if ($result_ficheamo['uniq_name'] != NULL && !empty($result_ficheamo['uniq_name'])) {
            $need_amo = true;
            $nom_ficheamo = $result_ficheamo['uniq_name'];

            $signature_coord = $result_ficheamo['signature_coord'];
            $page_number = $result_ficheamo['page_number'];

            $file_amo = $path . "/espace-rac/" . 'upload/contrats_amo/' . $nom_ficheamo;

            $pdf_amo = new Pdf($file_amo);
            $pdf_amo->fillForm($fields_amo);
            $pdf_amo->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf_amo->flatten();
            if ($pdf_amo->execute() === false) {
                $pdferror = $pdf_amo->getError();
                /*echo json_encode($pdferror);
            exit();*/
                // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON 
                // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                $need_amo = false;
            } else {
                $amoString = file_get_contents((string) $pdf_amo->getTmpFile());
            }
        } else {
            $need_amo = false;
        }


        /* Gestion pour ASC2 */
        $need_mandat1_asc2 = false;
        $need_mandat2_asc2 = false;
        if (strpos($path, 'asc2.26770') == true) {
            $sql = "SELECT dm.`produit_id`, p.`ref` as produit_ref,
                           d.id AS devis_id, d.date AS devis_date, d.surface_logement AS surface_logement, DATE_FORMAT(`date_pre_visite`, '%d/%m/%Y') AS date_pre_visite, c.id AS client_id,  c.residence_principale AS client_residence_principale, c.genre AS client_genre, c.nom AS client_nom, c.prenom AS client_prenom, c.email as client_email, c.proprietaire as client_proprietaire, c.autre_habitat as client_autre_habitat, c.telephone as client_telephone, c.adresse as client_adresse, c.code_postal as client_cp, c.ville as client_ville, u.nom as user_nom, u.email as user_email, u.telephone as user_telephone, u.mobile as user_mobile, u.poste as user_poste
                    FROM `rac_devis_metas` dm 
                    INNER JOIN `rac_devis` d ON d.`id` = dm.`devis_id`
                    INNER JOIN `rac_produit` p ON p.`id` = dm.`produit_id`
                    LEFT JOIN rac_client AS c ON c.id = d.client_id
                    LEFT JOIN rac_user AS u ON u.user_id = c.user_id
                    WHERE `is_produit` = 1 AND dm.`devis_id` = :devis_id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(
                'devis_id' => $devis_id
            ));

            $row = $stmt->fetch();

            if ($row['produit_id'] == 20) {
                $genre = $row['client_genre'] == 0 ? "Monsieur" : "Madame";
                $nom = $row['client_nom'];
                $prenom = $row['client_prenom'];
                $client_adresse = $row['client_adresse'];
                $client_cp = $row['client_cp'];
                $client_ville = $row['client_ville'];
                $client_email = $row['client_email'];
                $client_telephone = $row['client_telephone'];
                $short_genre = $genre == "Monsieur" ? 'mr' : 'mme';

                $fields_req1 = array(
                    'NOM' => $nom,
                    'PRENOM' => $prenom,
                    'ADRESSE' => $client_adresse,
                    'VILLE' => $client_ville,
                    'CODE_POSTAL' => $client_cp,
                    'ADRESSE_FULL' => $client_adresse . ' ' . $client_cp . ', ' . $client_ville,
                    'ADRESSE_COMPLETE' => $client_adresse . ' ' . $client_cp . ', ' . $client_ville,
                    'NOM_PRENOM' => $nom . ' ' . $prenom,
                    'EMAIL' => $client_email,
                    'TEL_FIXE' => '',
                    'TEL' => $client_telephone,
                    'FAIT_A' => $client_ville
                );

                $need_mandat1_asc2 = true;
                $need_mandat2_asc2 = true;
                $file_mandat1_asc2 = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/mandat1_asc.pdf';
                $file_mandat2_asc2 = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/mandat2_asc.pdf';

                $pdf_mandat1_asc2 = new Pdf($file_mandat1_asc2);
                $pdf_mandat1_asc2->fillForm($fields_req1);
                $pdf_mandat1_asc2->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf_mandat1_asc2->flatten();
                if ($pdf_mandat1_asc2->execute() === false) {
                    $pdferror = $pdf_mandat1_asc2->getError();
                    /*echo json_encode($pdferror);
                    exit();*/
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                    $pdf_mandat1_asc2 = false;
                } else {
                    $mandat1_asc2_String = file_get_contents((string)$pdf_mandat1_asc2->getTmpFile());
                }

                $pdf_mandat2_asc2 = new Pdf($file_mandat2_asc2);
                $pdf_mandat2_asc2->fillForm($fields_req1);
                $pdf_mandat2_asc2->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf_mandat2_asc2->flatten();
                if ($pdf_mandat2_asc2->execute() === false) {
                    $pdferror = $pdf_mandat2_asc2->getError();
                    /*echo json_encode($pdferror);
                    exit();*/
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                    $pdf_mandat2_asc2 = false;
                } else {
                    $mandat2_asc2_String = file_get_contents((string)$pdf_mandat2_asc2->getTmpFile());
                }
            }
        }

        /* Gestion pour LE */
        $need_mandat_special_le = false;
        if (strpos($path, 'le.26770') == true) {
            $sql = "SELECT d.id AS devis_id, d.date AS devis_date, d.surface_logement AS surface_logement, DATE_FORMAT(`date_pre_visite`, '%d/%m/%Y') AS date_pre_visite, c.id AS client_id,  c.residence_principale AS client_residence_principale, c.genre AS client_genre, c.nom AS client_nom, c.prenom AS client_prenom, c.email as client_email, c.proprietaire as client_proprietaire, c.autre_habitat as client_autre_habitat, c.telephone as client_telephone, c.adresse as client_adresse, c.code_postal as client_cp, c.ville as client_ville, u.nom as user_nom, u.email as user_email, u.telephone as user_telephone, u.mobile as user_mobile, u.poste as user_poste
            FROM   rac_devis AS d
            LEFT JOIN rac_client AS c ON c.id = d.client_id
            LEFT JOIN rac_user AS u ON u.user_id = c.user_id
            WHERE  d.id = :devis_id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(
                'devis_id' => $devis_id
            ));

            $row = $stmt->fetch();
            $genre = $row['client_genre'] == 0 ? "Monsieur" : "Madame";
            $nom = $row['client_nom'];
            $prenom = $row['client_prenom'];
            $client_adresse = $row['client_adresse'];
            $client_cp = $row['client_cp'];
            $client_ville = $row['client_ville'];
            $client_email = $row['client_email'];
            $client_telephone = $row['client_telephone'];
            $short_genre = $genre == "Monsieur" ? 'mr' : 'mme';

            $fields_req1 = array(
                'NOM' => $nom,
                'PRENOM' => $prenom,
                'ADRESSE' => $client_adresse,
                'VILLE' => $client_ville,
                'CODE_POSTAL' => $client_cp,
                'ADRESSE_FULL' => $client_adresse . ' ' . $client_cp . ', ' . $client_ville,
                'ADRESSE_COMPLETE' => $client_adresse . ' ' . $client_cp . ', ' . $client_ville,
                'NOM_PRENOM' => $nom . ' ' . $prenom,
                'EMAIL' => $client_email,
                'TEL_FIXE' => '',
                'TEL' => $client_telephone,
                'FAIT_A' => $client_ville
            );

            $need_mandat_special_le = true;
            $file_mandat_special_le = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/mandat_special_le.pdf';

            $pdf_mandat_special_le = new Pdf($file_mandat_special_le);
            $pdf_mandat_special_le->fillForm($fields_req1);
            $pdf_mandat_special_le->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf_mandat_special_le->flatten();
            if ($pdf_mandat_special_le->execute() === false) {
                $pdferror = $pdf_mandat_special_le->getError();
                /*echo json_encode($pdferror);
                exit();*/
                // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                $pdf_mandat_special_le = false;
            } else {
                $mandat_special_leString = file_get_contents((string)$pdf_mandat_special_le->getTmpFile());
            }
        }

        /*Gestion pour leader-energie*/
        if (strpos($path, 'leader-energie') == true) {
            $sql = "SELECT * FROM rac_devis d LEFT JOIN rac_client c ON c.id = d.client_id  WHERE d.id = :devis_id";
            $statement = $dbh->prepare($sql);
            $statement->execute(array(
                'devis_id' => $devis_id
            ));

            if ($result = $statement->fetchAll()) {
                foreach ($result as $key => $row) {

                    $fields_doc_leader = array(
                        'nom_prenom' => $row['nom'] . ' ' . $row['prenom'],
                        'adresse' => $row['adresse'] . ' ' . $row['code_postal'] . ' ' . $row['ville'],
                        'mobile' => $row['telephone'],
                        'mail' => $row['email'],
                        '2ans_oui' => ($row['deux_ans'] == 1) ? 'Oui' : 'Non',
                        'habitation_maison' => ($row['maison'] == 1) ? 'Oui' : 'Non', //maison=1, immeuble collectif=2, appartement individuel=3, autre=4
                        'habitation_appartement' => ($row['maison'] != 1) ? 'Oui' : 'Non', //maison=1, immeuble collectif=2, appartement individuel=3, autre=4
                        'residence_principale' => ($row['residence_principale'] == 1 && $row['proprietaire'] == 0) ? 'Oui' : 'Non',
                        'superficie_m2' => $row['surface_logement'],
                        'chaudiere_fioul' => ($row['sub_energie_id'] == 2) ? 'Oui' : 'Non',
                        'chaudiere_gaz' => ($row['sub_energie_id'] == 1) ? 'Oui' : 'Non',
                        'chaudierecondensation_oui' => ($row['energie_id'] == 1 && ($row['sub_energie_id'] != 1 && $row['sub_energie_id'] != 2 && $row['sub_energie_id'] != 3 && $row['sub_energie_id'] != 4)) ? 'Oui' : 'Non',
                        'chaudierecondensation_non' => ($row['sub_energie_id'] == 1 || $row['sub_energie_id'] == 2) ? 'Oui' : 'Non',
                        'accord' => 'Oui'
                    );
                }

                $file_doc_leader = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/others/leader_energy/doc.pdf';

                $pdf_doc_leader = new Pdf($file_doc_leader);
                $pdf_doc_leader->fillForm($fields_doc_leader);
                $pdf_doc_leader->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf_doc_leader->flatten();
                if ($pdf_doc_leader->execute() === false) {
                    $pdferror = $pdf_doc_leader->getError();
                    /*echo json_encode($pdferror);
                  exit();*/
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                } else {
                    $docLeaderString = file_get_contents((string)$pdf_doc_leader->getTmpFile());
                }
            }
        }


        /*Gestion pour sibel-energie*/
        if (strpos($path, 'sibel-energie') == true) {
            $sql = "SELECT * FROM rac_devis d LEFT JOIN rac_client c ON c.id = d.client_id  WHERE d.id = :devis_id";
            $statement = $dbh->prepare($sql);
            $statement->execute(array(
                'devis_id' => $devis_id
            ));

            if ($result = $statement->fetchAll()) {
                foreach ($result as $key => $row) {

                    $fields_mandat_sibel = array(
                        'NOM_PRENOM' => $row['nom'] . ' ' . $row['prenom'],
                        'ADRESSE' => $row['adresse'] . ' ' . $row['code_postal'] . ' ' . $row['ville'],
                        'ADRESSE_INSTALLATION' => ($row['adresse_impots'] != NULL) ? $row['adresse_impots'] . ' ' . $row['code_postal_impots'] . ' ' . $row['ville_impots'] : $row['adresse'] . ' ' . $row['code_postal'] . ' ' . $row['ville']
                    );
                }

                $file_mandat_sibel1 = $path . "/espace-rac/templates/mandat_sibel1.pdf";
                $file_mandat_sibel2 = $path . "/espace-rac/templates/mandat_sibel2.pdf";
                $file_mandat_sibel3 = $path . "/espace-rac/templates/mandat_sibel3.pdf";

                $pdf_mandat_sibel1 = new Pdf($file_mandat_sibel1);
                $pdf_mandat_sibel2 = new Pdf($file_mandat_sibel2);
                $pdf_mandat_sibel3 = new Pdf($file_mandat_sibel3);

                $pdf_mandat_sibel1->fillForm($fields_mandat_sibel);
                $pdf_mandat_sibel1->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf_mandat_sibel1->flatten();
                if ($pdf_mandat_sibel1->execute() === false) {
                    $pdferror = $pdf_mandat_sibel1->getError();
                } else {
                    $mandat_sibel1String = file_get_contents((string)$pdf_mandat_sibel1->getTmpFile());
                }
                $pdf_mandat_sibel2->fillForm($fields_mandat_sibel);
                $pdf_mandat_sibel2->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf_mandat_sibel2->flatten();
                if ($pdf_mandat_sibel2->execute() === false) {
                    $pdferror = $pdf_mandat_sibel2->getError();
                } else {
                    $mandat_sibel2String = file_get_contents((string)$pdf_mandat_sibel2->getTmpFile());
                }
                $pdf_mandat_sibel3->fillForm($fields_mandat_sibel);
                $pdf_mandat_sibel3->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf_mandat_sibel3->flatten();
                if ($pdf_mandat_sibel3->execute() === false) {
                    $pdferror = $pdf_mandat_sibel3->getError();
                } else {
                    $mandat_sibel3String = file_get_contents((string)$pdf_mandat_sibel3->getTmpFile());
                }
            }
        }

        $need_lettre_engagement = 0;
        $need_refus_amo = 0;
        $need_liste_precos = 0;
        $need_subvention = 0;
        $need_offre_amo_et_liste_travaux_preconises = 0;



        $stmt = $dbh->prepare('SELECT d.`scenario_retenu_id`, d.`id_etude_energetique`, d.`is_renovation_globale` FROM `rac_devis` d WHERE d.`id` = :devis_id');
        $stmt->execute(array(
            'devis_id' => $devis_id
        ));
        while ($res_reno_globale = $stmt->fetch()) {
            if ($res_reno_globale['scenario_retenu_id'] != NULL && $res_reno_globale['id_etude_energetique'] != NULL && $res_reno_globale['scenario_retenu_id'] != 0 && $res_reno_globale['id_etude_energetique'] != 0 && $res_reno_globale['is_renovation_globale'] == 1 && $need_offre_amo_et_liste_travaux_preconises == 0) {
                $need_offre_amo_et_liste_travaux_preconises = 1;

                /*
                $offre_amo = $documents_dir . 'offre_amo-' . $db_devis_id . '-' . $client_uuid . '.pdf';
                if (!file_exists($offre_amo)) {
                    $file_path = getOverridedFiles('include/offre_amo.php');
                    require_once($file_path);
                    $pdf->Output($offre_amo, 'f');
                    $offreamoString = $pdf->Output($offre_amo, 'S');
                } else {
                    $offreamoString = file_get_contents($offre_amo);
                }
                */

                $liste_travaux_preconises = $documents_dir . 'liste_travaux_preconises-' . $db_devis_id . '-' . $client_uuid . '.pdf';
                if (!file_exists($liste_travaux_preconises)) {
                    $file_path2 = getOverridedFiles('include/liste_travaux_preconises.php');
                    require_once($file_path2);
                    $pdf->Output($liste_travaux_preconises, 'f');
                    $listetravauxpreconisesString = $pdf->Output($liste_travaux_preconises, 'S');
                } else {
                    $listetravauxpreconisesString = file_get_contents($liste_travaux_preconises);
                    //echo json_encode("file get contents OK2 : ".$listetravauxpreconisesString);
                    //exit();
                }
            }
        }




        /* Gestion pour CEDF */
        if (strpos($path, 'cedf') == true || strpos($path, 'ebs') == true || strpos($path, 'amyenv') == true) {


            $stmt = $dbh->prepare('SELECT dm.`ref`, dm.`nom`, d.`is_renovation_globale`, p.`ref` as produit_ref FROM `rac_devis_metas` dm INNER JOIN `rac_devis` d ON d.`id` = dm.`devis_id` INNER JOIN `rac_produit` p ON p.`id` = dm.`produit_id` WHERE `is_produit` = 1 AND dm.`devis_id` = :devis_id');
            $stmt->execute(array(
                'devis_id' => $devis_id
            ));
            while ($res_produit = $stmt->fetch()) {
                if (strpos($path, 'cedf') == true) {
                    if ($need_subvention == 0 && $res_produit['is_renovation_globale'] == 0 && $res_produit['produit_ref'] == 'BAR-EN-101' && $res_produit['ref'] == 'RAMPANT') {
                        $need_subvention = 1;
                    }
                }
                if ($res_produit['is_renovation_globale'] == 1 && $need_refus_amo == 0) {
                    $need_refus_amo = 1;
                }
                if ($res_produit['is_renovation_globale'] == 1 && $need_lettre_engagement == 0) {
                    $need_lettre_engagement = 1;
                }
                if ($res_produit['is_renovation_globale'] == 1 && $need_liste_precos == 0) {
                    $need_liste_precos = 1;
                }
            }


            /* Gestion Lettre engagement */
            //if($need_lettre_engagement == 1) {

            $fields_lettre_engagement = array(
                'NOM' => $nom,
                'PRENOM' => $prenom,
                'NOM_PRENOM' => $nom . ' ' . $prenom,
                'ADRESSE' => $client_adresse,
                'VILLE' => $client_ville,
                'CODE_POSTAL' => $client_cp
            );
            //$need_lettre_engagement = true;

            $file_lettre_engagement = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/others/ebs/engagement.pdf';

            $pdf_lettre_engagement = new Pdf($file_lettre_engagement);
            $pdf_lettre_engagement->fillForm($fields_lettre_engagement);
            $pdf_lettre_engagement->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
            $pdf_lettre_engagement->flatten();
            if ($pdf_lettre_engagement->execute() === false) {
                $pdferror = $pdf_lettre_engagement->getError();
                /*echo json_encode($pdferror);
                exit();*/
                // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                $need_lettre_engagement = false;
            } else {
                $lettreengagementString = file_get_contents((string)$pdf_lettre_engagement->getTmpFile());
            }
            //}


            $dbh = db_connect();
            $stmt_refus_amo = $dbh->prepare("SELECT d.`is_renovation_globale` FROM rac_devis d WHERE d.id = :devis_id");
            $stmt_refus_amo->execute(array(
                'devis_id' => $devis_id
            ));
            $result_refus_amo = $stmt_refus_amo->fetch();
            $stmt_refus_amo->closeCursor();

            if ($result_refus_amo['is_renovation_globale'] == 1) {
                /* Gestion refus amo */
                $fields_refus_amo = array(
                    'NOM' => $nom,
                    'PRENOM' => $prenom,
                    'NOM_PRENOM' => $nom . ' ' . $prenom,
                    'ADRESSE' => $client_adresse,
                    'VILLE' => $client_ville,
                    'CODE_POSTAL' => $client_cp
                );
                $need_refus_amo = true;

                $file_refus_amo = $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'templates/others/ebs/refus.pdf';

                $pdf_refus_amo = new Pdf($file_refus_amo);
                $pdf_refus_amo->fillForm($fields_refus_amo);
                $pdf_refus_amo->needAppearances(); // second parameter: false if field values are in ISO-8859-1, true if UTF-8
                $pdf_refus_amo->flatten();
                if ($pdf_refus_amo->execute() === false) {
                    $pdferror = $pdf_refus_amo->getError();
                    /*echo json_encode($pdferror);
            exit();*/
                    // ERREUR ON N'A PAS REUSSI A REMPLIR LE PDF ON
                    // NE L'AJOUTE PAS CAR SIGNATURE ELECTRONIQUE AUCUN SENS
                    $need_refus_amo = false;
                } else {
                    $refusamoString = file_get_contents((string)$pdf_refus_amo->getTmpFile());
                }
            }
        }
    } else {
        echo json_encode("Impossible d'envoyer le devis.");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        //try {
        if (strpos($path, 'mes-radiateurs-gratuits') == true)
            $pattern = "     le :";
        else if (strpos($path, 'reunion') == true && $pollueur_nom != 'TOTALENERGIES ELECTRICITE ET GAZ FRANCE')
            $pattern = "date d'acceptation (manuscrite) :";
        else if (strpos($path, 'guada') == true && in_array($type_paiement, array("maprimerenov", "comptant")))
            $pattern = "(cachet + nom";
        else
            $pattern = "     le : ........................................";
        ob_start();
        passthru('/usr/bin/python ' . $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'scripts/python/get_pdf_coordinate.py ' . $fichier . ' "' . $pattern . '"');
        $devis_coordinates_signature = ob_get_clean();
        $devis_coordinates_signature = json_decode($devis_coordinates_signature, true);

        if ($pollueur_nom == 'LSF ENERGIE (MANDATAIRE DE SCA PETROLE ET DERIVES)' || strtoupper($pollueur_nom) == 'PREMIUM ENERGY (MANDATAIRE DE SCA PETROLE ET DERIVES)' || strtoupper($pollueur_nom) == 'OAAN CONSULTING') {
            /*if($pollueur_nom == 'LSF ENERGIE (MANDATAIRE DE SCA PETROLE ET DERIVES)') {
                  $pattern = "signature du bénéficiaire :";
              } else {*/
            $pattern = "Signature du bénéficiaire :";
            //}

            ob_start();
            passthru('/usr/bin/python ' . $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'scripts/python/get_pdf_coordinate.py ' . $fichier . ' "' . $pattern . '"');
            $devis_coordinates_signature_lsf = ob_get_clean();
            $devis_coordinates_signature_lsf = json_decode($devis_coordinates_signature_lsf, true);

            $devis_coordinates_signature = array_merge($devis_coordinates_signature, $devis_coordinates_signature_lsf);
        }

        unlink($fichier);
        if (empty($devis_coordinates_signature)) {
            echo json_encode("Impossible de signer éléctroniquement ce devis (merci de contacter contact@ar-partners.fr)");
            exit();
        }


        if ($need_offre_amo_et_liste_travaux_preconises) {
            $patternListeTravaux = 'Fait à ' . strtoupper($client_ville);
            ob_start();
            passthru('/usr/bin/python ' . $_SERVER["DOCUMENT_ROOT"] . "/" . explode("/", $_SERVER["REQUEST_URI"])[1] . "/" . 'scripts/python/get_pdf_coordinate.py ' . $liste_travaux_preconises . ' "' . $patternListeTravaux . '"');
            $liste_travaux_preconises_coordinates_signature = ob_get_clean();
            $liste_travaux_preconises_coordinates_signature = json_decode($liste_travaux_preconises_coordinates_signature, true);

            if (empty($liste_travaux_preconises_coordinates_signature)) {
                echo json_encode("Impossible de signer éléctroniquement le document Offre à maitrise d'ouvrage (merci de contacter contact@ar-partners.fr). Pattern non trouvé : '" . $patternListeTravaux . "' dans le fichier " . $liste_travaux_preconises);
                exit();
            }
            //unlink($listetravauxpreconisesString);
        }

        $devis_positions = [];
        $devis_page_signatures = [];

        $listetravauxpreconises_page_signatures = [];
        $listetravauxpreconises_positions = [];
        $offreamo_page_signatures = [];
        $offreamo_positions = [];

        $signer_types = [];
        $signerDatas = array();
        foreach ($devis_coordinates_signature as $key => $signature_frames) {
            /////////////////// HOW IT WORKS ////////////////
            ///////////// $signature_frames[0] = llx
            ///////////// $signature_frames[1] = ury
            /////////////////////////////////////////////////
            $llx = intval($signature_frames[0]);
            $ury = intval($signature_frames[1] + 5);
            if (strpos($path, 'reunion') == true) {
                $urx = intval($signature_frames[0] + 280);
                $lly = intval(max($signature_frames[1] - 100, 0));
            } else {
                $urx = intval($signature_frames[0] + 180);
                $lly = intval(max($signature_frames[1] - 55, 0));
            }
            $devis_positions[] = $llx . "," . $lly . "," . $urx . "," . $ury;
            $devis_page_signatures[] = $signature_frames[2];
        }

        if ($need_offre_amo_et_liste_travaux_preconises) {
            foreach ($liste_travaux_preconises_coordinates_signature as $key => $signature_frames) {
                /////////////////// HOW IT WORKS ////////////////
                ///////////// $signature_frames[0] = llx
                ///////////// $signature_frames[1] = ury
                /////////////////////////////////////////////////
                $llx = intval($signature_frames[0] + 200);
                $ury = intval($signature_frames[1] - 45);
                if (strpos($path, 'reunion') == true) {
                    $urx = intval($signature_frames[0] + 280);
                    $lly = intval(max($signature_frames[1] - 100, 0));
                } else {
                    $urx = intval($signature_frames[0] + 380);
                    $lly = intval(max($signature_frames[1] - 105, 0));
                }
                $listetravauxpreconises_positions[] = $llx . "," . $lly . "," . $urx . "," . $ury;
                $listetravauxpreconises_page_signatures[] = $signature_frames[2];
            }
        }

        $pdfStrings = array();
        $positions = array();
        $page_numbers = array();
        $doc_ids = array();


        // l'anah n'accepte pas les mandats signés électroniquement
        // finalement Axel dit que c'est bon

        $date_format = "d/m/Y H:i:s";
        $devis_date_c = DateTime::createFromFormat("Y-m-d H:i:s", $devis_date_req);
        $t1  = DateTime::createFromFormat($date_format, "01/05/2010 00:00:00");

        if ($devis_date_c < $t1) {
            if ($is_mandat_administratif_financier) {
                $pdfStrings['mandat_administratif_financier'] = $mandatString_administratif_financier;
                $positions['mandat_administratif_financier'] = '124,70,278,132';
                $page_numbers['mandat_administratif_financier'] = intval(2);
                $doc_ids['mandat_administratif_financier'] = 1;
            } else {
                if ($is_mandat_administratif) {
                    $pdfStrings['mandat_administratif'] = $mandatString_administratif;
                    $positions['mandat_administratif'] = '124,70,278,132';
                    $page_numbers['mandat_administratif'] = intval(2);
                    $doc_ids['mandat_administratif'] = 1;
                }
                if ($is_mandat_financier) {
                    $pdfStrings['mandat_financier'] = $mandatString_financier;
                    $positions['mandat_financier'] = '124,70,278,132';
                    $page_numbers['mandat_financier'] = intval(2);
                    $doc_ids['mandat_financier'] = 8;
                }
            }
        } else {

            if ($num_mpr2 != NULL) {

                if ($is_mandat_administratif_financier) {
                    $pdfStrings['mandat_administratif_financier'] = $mandatString_administratif_financier;
                    $positions['mandat_administratif_financier'] = '124,70,278,132';
                    $page_numbers['mandat_administratif_financier'] = intval(2);
                    $doc_ids['mandat_administratif_financier'] = 1;
                } else {
                    if ($is_mandat_administratif) {
                        $pdfStrings['mandat_administratif'] = $mandatString_administratif;
                        $positions['mandat_administratif'] = '124,70,278,132';
                        $page_numbers['mandat_administratif'] = intval(2);
                        $doc_ids['mandat_administratif'] = 1;
                    }
                    if ($is_mandat_financier) {
                        $pdfStrings['mandat_financier'] = $mandatString_financier;
                        $positions['mandat_financier'] = '124,70,278,132';
                        $page_numbers['mandat_financier'] = intval(2);
                        $doc_ids['mandat_financier'] = 8;
                    }
                }
            }
        }
        if ($num_mpr2 != NULL) {
            /*
              $pdfStrings['attestation_consentement'] = $attestationConsentementString;
              $positions['attestation_consentement'] = '348,91,539,157';
              $page_numbers['attestation_consentement'] = intval(1);
              $doc_ids['attestation_consentement'] = 13;
              */
        }

        if ($need_amo) {
            $signature_coord = explode(";", $signature_coord);
            $page_number = explode(";", $page_number);
            $page_number = array_map('intval', $page_number);

            $pdfStrings['amo'] = $amoString;
            $positions['amo'] = $signature_coord;
            $page_numbers['amo'] = $page_number;
            $doc_ids['amo'] = 2;
        }

        if ($need_tva) {
            $pdfStrings['attestation_tva'] = $tvaString;
            $positions['attestation_tva'] = '239,92,396,143';
            $page_numbers['attestation_tva'] = intval(1);
            $doc_ids['attestation_tva'] = 3;
        }

        if ($need_lettre_engagement) {
            $pdfStrings['lettre_engagement'] = $lettreengagementString;
            $positions['lettre_engagement'] = '387,193,553,256';
            $page_numbers['lettre_engagement'] = intval(1);
            $doc_ids['lettre_engagement'] = 4;
        }
        if ($need_refus_amo) {
            $pdfStrings['refus_amo'] = $refusamoString;
            $positions['refus_amo'] = '316,107,481,169';
            $page_numbers['refus_amo'] = intval(1);
            $doc_ids['refus_amo'] = 5;
        }

        if ($need_offre_amo_et_liste_travaux_preconises) {
            $pdfStrings['liste_travaux_preconises'] = $listetravauxpreconisesString;
            $positions['liste_travaux_preconises'] = $listetravauxpreconises_positions;
            $page_numbers['liste_travaux_preconises'] = $listetravauxpreconises_page_signatures;
            $doc_ids['liste_travaux_preconises'] = 14;

            /*
              $pdfStrings['offre_amo'] = $offreamoString;
              $positions['offre_amo'] = '316,107,481,169';
              $page_numbers['offre_amo'] = intval(1);
              $doc_ids['offre_amo'] = 15;
              */
        }


        if (strpos($path, 'asc2.26770') && $need_mandat1_asc2 == true) {
            $pdfStrings['mandat1_asc2'] = $mandat1_asc2_String;
            $positions['mandat1_asc2'] = '78,92,252,176';
            $page_numbers['mandat1_asc2'] = intval(2);
            $doc_ids['mandat1_asc2'] = 16;

            $pdfStrings['mandat2_asc2'] = $mandat2_asc2_String;
            $positions['mandat2_asc2'] = '105,111,256,176';
            $page_numbers['mandat2_asc2'] = intval(1);
            $doc_ids['mandat2_asc2'] = 17;
        }

        //if(defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 0) {
        //if ($need_mandat_special_le) {
        //$page_number = explode(";", '1;2');
        //$page_number = array_map('intval', $page_number);

        if (strpos($path, 'le.26770') == true) {
            $signature_coord = explode(";", '355,100,517,178;355,100,517,178');
            $page_number = explode(";", '1;2');
            $page_number = array_map('intval', $page_number);

            $pdfStrings['mandat_special_le'] = $mandat_special_leString;
            $positions['mandat_special_le'] = $signature_coord;
            $page_numbers['mandat_special_le'] = $page_number;
            $doc_ids['mandat_special_le'] = 13;
        }
        //}
        //}



        if (strpos($path, 'leader-energie') == true) {
            $signature_coord = explode(";", '377,101,527,168;369,55,519,140');
            $page_number = explode(";", '1;5');
            $page_number = array_map('intval', $page_number);

            $pdfStrings['doc_leader'] = $docLeaderString;
            $positions['doc_leader'] = $signature_coord;
            $page_numbers['doc_leader'] = $page_number;
            $doc_ids['doc_leader'] = 6;
        }

        if (strpos($path, 'sibel-energie') == true) {
            $signature_coord1 = explode(";", '97,73,262,142');
            $page_number1 = explode(";", '1');
            $page_number1 = array_map('intval', $page_number1);
            $signature_coord2 = explode(";", '98,86,265,157');
            $page_number2 = explode(";", '1');
            $page_number2 = array_map('intval', $page_number2);
            $signature_coord3 = explode(";", '378,85,500,135');
            $page_number3 = explode(";", '1');
            $page_number3 = array_map('intval', $page_number3);

            $pdfStrings['mandat_sibel1'] = $mandat_sibel1String;
            $positions['mandat_sibel1'] = $signature_coord1;
            $page_numbers['mandat_sibel1'] = $page_number1;
            $doc_ids['mandat_sibel1'] = 10;

            $pdfStrings['mandat_sibel2'] = $mandat_sibel2String;
            $positions['mandat_sibel2'] = $signature_coord2;
            $page_numbers['mandat_sibel2'] = $page_number2;
            $doc_ids['mandat_sibel2'] = 11;

            $pdfStrings['mandat_sibel3'] = $mandat_sibel3String;
            $positions['mandat_sibel3'] = $signature_coord3;
            $page_numbers['mandat_sibel3'] = $page_number3;
            $doc_ids['mandat_sibel3'] = 12;
        }


        if (strpos($path, 'planitis') == true) {
            $signature_coord = explode(";", '220,267,403,341');
            $page_number = explode(";", '1');
            $page_number = array_map('intval', $page_number);

            $pdfStrings['doc_planitis'] = $subventionString;
            $positions['doc_planitis'] = $signature_coord;
            $page_numbers['doc_planitis'] = $page_number;
            $doc_ids['doc_planitis'] = 7;
        }

        if (strpos($path, 'futurenv') == true) {
            $signature_coord = explode(";", '83,220,262,299');
            $page_number = explode(";", '1');
            $page_number = array_map('intval', $page_number);

            $pdfStrings['procuration'] = $procurationString;
            $positions['procuration'] = $signature_coord;
            $page_numbers['procuration'] = $page_number;
            $doc_ids['procuration'] = 10;
        }

        $nom_subvention = "";
        if ($is_subvention) {
            $sql = "SELECT `doc_signature_coord`, `doc_signature_coord_V3`, `uniq_name` FROM `rac_document_upload` WHERE doc_type='subvention'";
            $statement = $dbh->prepare($sql);
            $statement->execute();
            $result_subvention = $statement->fetch();

            if (!empty($result_subvention)) {
                $nom_subvention = $result_subvention['uniq_name'];
                if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
                    if ($result_subvention['doc_signature_coord'] != '' && $result_subvention['doc_signature_coord'] != NULL) {
                        $signature_coord_subvention_V3 = $result_subvention['doc_signature_coord_V3'];
                        $positions['subvention'] = $signature_coord_subvention_V3;
                        $page_numbers['subvention'] = intval(1);
                        $doc_ids['subvention'] = 9;
                        $pdfStrings['subvention'] = $subventionString;
                    }
                } else {
                    if ($result_subvention['doc_signature_coord'] != '' && $result_subvention['doc_signature_coord'] != NULL) {
                        $signature_coord_subvention = $result_subvention['doc_signature_coord'];
                        $positions['subvention'] = $signature_coord_subvention;
                        $page_numbers['subvention'] = intval(1);
                        $doc_ids['subvention'] = 9;
                        $pdfStrings['subvention'] = $subventionString;
                    }
                }
            }
        }


        if ($need_document_mandat_mpr) {
            $pdfStrings['document_mpr'] = $pdf_document_mprString;

            $sql = "SELECT `filename`, `uniq_name`, `doc_page_number`, `doc_signature_coord` FROM `rac_document_upload` u LEFT JOIN rac_devis d ON d.mandataire_mpr_financier_id = u.item_id WHERE doc_type = 'mandat_mpr' AND d.id = :devis_id";
            $statement = $dbh->prepare($sql);
            $statement->execute(array('devis_id' => $devis_id));
            $result_document_mpr = $statement->fetch();

            if (!empty($result_document_mpr)) {
                $nom_document_mpr = $result_document_mpr['uniq_name'];

                if ($result_document_mpr['doc_signature_coord'] != '' && $result_document_mpr['doc_signature_coord'] != NULL) {
                    $signature_coord_subvention = $result_document_mpr['doc_signature_coord'];

                    $array_position_subvention['x'] = explode(',', $signature_coord_subvention)[0];
                    $array_position_subvention['y'] = explode(',', $signature_coord_subvention)[1];
                    $positions['document_mpr'] = $array_position_subvention;

                    if ($result_document_mpr['doc_page_number'] != NULL && $result_document_mpr['doc_page_number'] != '') {
                        $page_numbers['document_mpr'] = intval($result_document_mpr['doc_page_number']);
                    } else {
                        $page_numbers['document_mpr'] = intval(1);
                    }
                    $doc_ids['document_mpr'] = 10;
                }
            }
        }

        $pdfStrings['devis'] = $pdfString;
        $positions['devis'] = $devis_positions;
        $page_numbers['devis'] = $devis_page_signatures;
        $doc_ids['devis'] = $id;


        if (YOUSIGN_API_URL == "staging-api.yousign.com") {
            //$client_email = "contact@ar-partners.fr";
            //$client_telephone = "0186982316";
            $client_telephone = "0975129732";
        }

        $ex_date = $devis_expiration->format('Y-m-d');
        if (date('Y-m-d') > $ex_date) {
            echo json_encode("Devis Expiré ! Impossible de signer éléctroniquement ce devis.");
            exit();
        }

        /*
          try {
              if(defined('IS_B2B') && !empty(IS_B2B)) {
                  $signerDatas = array('lastname' => $nom, 'firstname' => $fonction.' - '.$prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone));
              } else {
                  $signerDatas = array('lastname' => $nom, 'firstname' => $prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone));
              }
              $results = send_document_for_signing($pdfStrings, $doc_ids, $signerDatas, $page_numbers, $positions, $ex_date, $devis_id, $signer_types);
          } catch (Exception $e) {
              if (strpos($e->getMessage(), 'members[0].phone:') == true) {
                  if(defined('IS_B2B') && !empty(IS_B2B)) {
                      $signerDatas = array('lastname' => $nom, 'firstname' => $fonction.' - '.$prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone, "", "+33"));
                  } else {
                      $signerDatas = array('lastname' => $nom, 'firstname' => $prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone, "", "+33"));
                  }
                  $results = send_document_for_signing($pdfStrings, $doc_ids, $signerDatas, $page_numbers, $positions, $ex_date, $devis_id, $signer_types);
              }
          }
          */

        if (defined('IS_B2B') && !empty(IS_B2B)) {
            $signerDatas = array('lastname' => $nom, 'firstname' => $fonction . ' - ' . $prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone));
        } else {
            $signerDatas = array('lastname' => $nom, 'firstname' => $prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone));
        }
        $results = send_document_for_signing($pdfStrings, $doc_ids, $signerDatas, $page_numbers, $positions, $ex_date, $devis_id, $signer_types);

        if (strpos($path, 'dev') == true) {
            //print_r($results);
        }
        // Mettre signature_id dans BDD dans devis !
        // 46f84b8e-ace7-4a6c-9220-51c60d20a84d
        if ($results) {

            if (strpos($results, 'members[0].phone:') == true && strpos($results, 'An error occured') == true) {
                $indicatifs = array('+262', '+590', '+594', '+596', '+33');
                if (defined('INDICATIF_TELEPHONIQUE') && !empty(trim(INDICATIF_TELEPHONIQUE)) && INDICATIF_TELEPHONIQUE != '+33') {
                    if (in_array(INDICATIF_TELEPHONIQUE, $indicatifs)) {
                        $indicatifs = array_diff($indicatifs, [INDICATIF_TELEPHONIQUE]);

                        for ($i = 0; $i < count($indicatifs); ++$i) {

                            if (defined('IS_B2B') && !empty(IS_B2B)) {
                                $signerDatas = array('lastname' => $nom, 'firstname' => $fonction . ' - ' . $prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone, "", $indicatifs[$i]));
                            } else {
                                $signerDatas = array('lastname' => $nom, 'firstname' => $prenom, 'email' => $client_email, 'city' => $client_ville, 'phone' => FormatTel2($client_telephone, "", $indicatifs[$i]));
                            }
                            $results = send_document_for_signing($pdfStrings, $doc_ids, $signerDatas, $page_numbers, $positions, $ex_date, $devis_id, $signer_types);

                            if (strpos($results, 'members[0].phone:') != true) {
                                break;
                            }
                        }
                    }
                }
            }





            //echo 'TEST : '.$results.'TESTT';
            $results = json_decode($results, true);
            if (isset($results['id'])) {
                if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
                    $signature_id = $results['id'];
                    $members = $results['signers'];
                } else {
                    $signature_id = str_replace("/procedures/", "", $results['id']);
                    $members = $results['members'];
                }

                foreach ($members as $key => $member) {
                    if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
                        $signature_url = $member['signature_link'] . "&disable_domain_validation=true";
                    } else {
                        $signature_url = 'https://' . YOUSIGN_WEB_URL . "/procedure/sign?members=" . $member['id'];
                    }
                    if (defined('YOUSIGN_SIGN_UI') && !empty(YOUSIGN_SIGN_UI))
                        $signature_url .= "&signatureUi=/signature_uis/" . YOUSIGN_SIGN_UI;

                    $msg .= send_email($user_nom, $sender_email, $user_email, $body_email, $body_tel, $genre . " " . $prenom . " " . $nom, $client_email, ($num_mpr2 != NULL) ? $attestationConsentementString : "", ($is_mandat_administratif_financier ? $mandatString_administratif_financier : ""), ($is_mandat_administratif ? $mandatString_administratif : ""), ($is_mandat_financier ? $mandatString_financier : ""), ($need_tva ? $tvaString : ""), ($need_cc ? $ccString : ""), (($is_subvention) ? $subventionString : ""), ($is_subvention && (strpos($path, 'ghe') == true || strpos($path, 'sashayno') == true || strpos($path, 'doovision') == true || strpos($path, 'efe') == true)) ? $lettredevisString : "", ((strpos($path, 'leader-energie') == true) ? $docLeaderString : ""), ((strpos($path, 'futurenv') == true) ? $procurationString : ""), ((strpos($path, 'sibel-energie') == true) ? $mandat_sibel1String : ""), ((strpos($path, 'sibel-energie') == true) ? $mandat_sibel2String : ""), ((strpos($path, 'sibel-energie') == true) ? $mandat_sibel3String : ""), ($need_amo ? $amoString : ""), ($need_refus_amo ? $refusamoString : ""), ($need_mandat_special_le ? $mandat_special_leString : ""), ($need_mandat1_asc2 ? $mandat1_asc2_String : ""), ($need_mandat2_asc2 ? $mandat2_asc2_String : ""), ($need_lettre_engagement ? $lettreengagementString : ""), $pdfString, $fichetechs_array, $signature_url, $signature, $path, $is_subvention, $nom_subvention, (($need_document_mandat_mpr) ? $pdf_document_mprString : ""), (($need_document_mandat_mpr) ? $nom_document_mpr : ""), $need_copy_mail, $devis_date_req, $num_mpr2);
                }

                if (!$error) {
                    try {
                        $dbh = db_connect();
                        $statement = $dbh->prepare("UPDATE rac_devis SET signature_id = :signature_id, signature_statut = :signature_statut, signature_url = :signature_url, signature_source = :signature_source WHERE id = :devis_id");

                        if (defined('IS_YOUSIGN_V3') && IS_YOUSIGN_V3 == 1) {
                            $statement->execute(array('signature_id' => $signature_id, 'signature_statut' => "Sent", 'signature_url' => $signature_url, 'signature_source' => 'yousign_v3', 'devis_id' => $devis_id));
                        } else {
                            $statement->execute(array('signature_id' => $signature_id, 'signature_statut' => "Sent", 'signature_url' => $signature_url, 'signature_source' => 'yousign', 'devis_id' => $devis_id));
                        }

                        if (isset($_POST['embed'])) {
                            echo json_encode(array("redirect_url" => $signature_url));
                        } else {
                            $json = json_encode($msg);
                            echo $json;
                            exit();
                        }
                    } catch (PDOException $e) {
                        // echo json_encode($e->getMessage());
                        echo json_encode("\nLe devis envoye au client ne doit pas etre signe! Veuillez le renvoyer pour signature.");
                        exit();
                        // Todo Delete file from docusign
                    }
                } else {
                    echo json_encode($msg);
                    exit();
                }
            } else {
                echo json_encode($results);
                exit();
            }
        } else {
            echo json_encode($results);
            exit();
        }
        /*
      } catch (Exception $e) {
        echo json_encode('Caught exception: ',  $e->getMessage());
        exit();
      }*/
    }
}
