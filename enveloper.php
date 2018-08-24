<?php
/**
 * Created by PhpStorm.
 * User: pkheni
 * Date: 2018-06-28
 * Time: 1:51 PM
 */
require __DIR__ . '/vendor/autoload.php';

use mikehaertl\wkhtmlto\Image;
use mikehaertl\wkhtmlto\Pdf;

function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    $client->setScopes([
        Google_Service_Gmail::GMAIL_READONLY,
        Google_Service_Gmail::GMAIL_MODIFY
    ]);
    $client->setAuthConfig('client_secret.json');
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory('credentials.json');
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

function expandHomeDirectory($path)
{
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
    }
    return str_replace('~', realpath($homeDirectory), $path);
}

function getMessage($service, $userId, $messageId) {
    $res = [
        "user" => $userId,
        "message" => $messageId
    ];
    $message = $service->users_messages->get($userId, $messageId);
    $size = $message->getPayload()->getBody()->getSize();
    if ($size <= 0) {
        foreach ($message->getPayload()->getParts() as $part) {
            if ($part->getMimeType() === "text/html") {
                $raw = $part->getBody()->getData();
            }
        }
    } else {
        $raw = $message->getPayload()->getBody()->getData();
    }
    if (count($message->getPayload()->getHeaders()) > 0) {
        $subject = "";
        $date = "";
        foreach ($message->getPayload()->getHeaders() as $header) {
            if ($header->getName() === "Subject") {
                $subject = $header->getValue();
            } else if ($header->getName() === "Date") {
                $timestamp = strtotime($header->getValue());
                $dt = new DateTime("now", new DateTimeZone("America/Toronto")); //first argument "must" be a string
                $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
                $date = $dt->format('m_d_Y_H_i_s');
            }
        }
        if ($subject == "" || $date == "") {
            throw new Exception("Failed to parse headers for subject and date " . $messageId);
        } else {
            if (strstr($subject, "table", true) != false) {
                $subject = strstr($subject, "table", true);
                $subject = str_replace(" ", "", $subject);
            }
            $res["outputFileName"] = $subject . "_" . $date . "_" . rand(100, 999);

            //remove unwanted characters from filename
            $specialCharacters = ["\\", "/", ":", "*", "\"", "'", "<", ">", "|", "?", ".", "\n", "\r", "\t"];
            foreach ($specialCharacters as $character) {
                $res["outputFileName"] = str_replace($character, "", $res["outputFileName"]);
            }
        }
    } else {
        throw new Exception("Failed to find headers for message " . $messageId);
    }
    $switched = str_replace(['-', '_'], ['+', '/'], $raw);
    $body = base64_decode($switched);
    $res["body"] = $body;
    if (array_key_exists("body", $res) && array_key_exists("outputFileName", $res)) {
        return $res;
    } else {
        return false;
    }
}

function getLableId($userId, $service, $labelName) {
    $results = $service->users_labels->listUsersLabels($userId);

    if (count($results->getLabels()) == 0) {
        print "No labels found.\n";
    } else {
        foreach ($results->getLabels() as $label) {
            //printf("- %s %s %s\n", $label->getName(), $label->getId(), $labelName);
            if ($label->getName() === $labelName) {
                return $label->getId();
            }
        }
    }
    return false;
}

function createDirectory($path)
{
    if (file_exists($path)) {
        return true;
    }

    return @mkdir($path, 0777, true);
}

function createOutputFile($outputFileName, $pdf)
{
    $filePath = __DIR__ . "/Data/";
    if (!file_exists($filePath)) {
        if (!createDirectory($filePath)) {
            $lastError = error_get_last();
            $exMsg = 'Unable to create upload directory, '.$filePath.' due to the following error:'.PHP_EOL;
            $exMsg .= $lastError['message'].' on line '.$lastError['line'].' in file '.$lastError['file'];
            throw new \Exception($exMsg);
        }
    }

    $filePath .= $outputFileName;
    if (!$pdf->saveAs($filePath)) {
        throw new \Exception('Unable to create output file: ' . $filePath . " Error: " . $pdf->getError());
    }

//    echo 'Created ' . $filePath . PHP_EOL;
    return true;
}

function modifyMessage($service, $userId, $messageId, $labelsToAdd, $labelsToRemove) {
    $mods = new Google_Service_Gmail_ModifyMessageRequest();
    $mods->setAddLabelIds($labelsToAdd);
    $mods->setRemoveLabelIds($labelsToRemove);
    $message = $service->users_messages->modify($userId, $messageId, $mods);
    return $message;
}

function createPdfWkHtml($htmlFilePath, $message) {
    $options = array(
        'binary' => "C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe",
        'ignoreWarnings' => true,
        'commandOptions' => array(
            'useExec' => true    // Can help if generation fails without a useful error message
        )
    );

    //generate pdf
    $pdf = new Pdf($options);
    $pdf->addPage($htmlFilePath);
    createOutputFile($message["outputFileName"] . ".pdf", $pdf);
}

function createImageWkHtml($message, $htmlFilePath) {
    //convert html to image
    $imageFilePath = __DIR__ . '/Images/'. $message["message"] .'.png';
    $image = new Image($htmlFilePath);
    $image->setOptions(['quality' => 50]);
    $image->saveAs($imageFilePath);

    //get image height
    list($width, $height, $type, $attr) = getimagesize($imageFilePath);
    if ($height <= 0) {
        throw new Exception("Failed to get Image size for pdf");
    } else {
        $options['page-height'] = $height;
    }
}

function printCommands() {
    $months = 12;
    $years = [2015, 2016, 2017];
    $commands = [];
    $format = "php enveloper.php --start \"%s\" --end \"%s\"";
    $year = 2014;
    for ($i = 7; $i <= $months; $i++) {
        if ($i == 12) {
            $commands[] = sprintf($format, "$year/$i/1", ($year+1) . "/1/1");
        } else {
            $commands[] = sprintf($format, "$year/$i/1", "$year/" . ($i + 1) . "/1");
        }
    }
    foreach ($years as $year) {
        for ($i = 1; $i <= $months; $i++) {
            if ($i == 12) {
                $commands[] = sprintf($format, "$year/$i/1", ($year+1) . "/1/1");
            } else {
                $commands[] = sprintf($format, "$year/$i/1", "$year/" . ($i + 1) . "/1");
            }
        }
    }
    $year = 2018;
    for ($i = 1; $i <= 5; $i++) {
        if ($i == 12) {
            $commands[] = sprintf($format, "$year/$i/1", ($year+1) . "/1/1");
        } else {
            $commands[] = sprintf($format, "$year/$i/1", "$year/" . ($i + 1) . "/1");
        }
    }
    return $commands;
}

// command line arguments
$longOptions = ['start:', 'end:', 'printCommands', 'runCommands'];
$options = getopt('s:e:', $longOptions);
if (array_key_exists("printCommands", $options)) {
    var_dump(printCommands());
    exit;
} else if (array_key_exists("runCommands", $options)) {
    $commands = printCommands();
    foreach ($commands as $command) {
        echo $command . " => " . exec($command) . PHP_EOL;
    }
    exit;
} else if (!isset($options['start'], $options['end'])) {
    throw new Exception("please provide start and end parameters");
} else {
    $start = $options['start'];
    $end = $options['end'];
}

$dateStr = "after:$start before:$end";

try {

    // Get the API client and construct the service object.
    $client = getClient();
    $service = new Google_Service_Gmail($client);

    $userId = 'me';
    $savedLabelId = getLableId($userId, $service, 'SAVED');
    $fsavedLabelId = getLableId($userId, $service, 'FAILEDTOSAVE');
    //$labelId = getLableId($userId, $service, 'test/test');
//    $query = "from:(flag@email.flag.com) after:2014/07/01 before:2018/06/01 !label=SAVED !label=FAILEDTOSAVE";
    $query = "label=FAILEDTOSAVE !label=SAVED";

    $limit = 5000;

    while ($limit > 0) {
        try {
            $opt_param = array('q' => $query, 'maxResults' => 1);
            $messagesResponses = $service
                ->users_messages
                ->listUsersMessages(
                    $userId,
                    $opt_param
                );

            if (count($messagesResponses['messages']) == 0) {
                throw new Exception("No messages found.");
            } else {
                foreach ($messagesResponses->getMessages() as $messageResponse) {

                    //get parsed message
                    $message = getMessage($service, $userId, $messageResponse->getId());

                    //get html from message body
                    $html = mb_convert_encoding($message["body"], "HTML-ENTITIES", "UTF-8");

                    //write html into a file
                    $htmlFilePath = __DIR__ . "/Html/" . $message["outputFileName"] . ".html";
                    if (file_put_contents($htmlFilePath, $html) === false) {
                        modifyMessage($service, $userId, $messageResponse->getId(), [$fsavedLabelId], []);
                        var_dump(
                            "Failed", [
                                "messageId" => $messageResponse->getId(),
                                "fileName" => $message["outputFileName"] . ".html"
                            ]
                        );
                    } else {
                        modifyMessage($service, $userId, $messageResponse->getId(), [$savedLabelId], []);
                        var_dump(
                            "Created", [
                                "messageId" => $messageResponse->getId(),
                                "fileName" => $message["outputFileName"] . ".html"
                            ]
                        );
                    }
                }
            }
            $limit--;
        } catch (Exception $e) {
            var_dump("Error", ["message" => $e->getMessage()]);
            if ($e->getMessage() === "No messages found.") {
                exit;
            }
        }
    }
} catch (Exception $e) {
    //TODO write message id into one file or log to kibana
    $myfile = fopen(__DIR__ . "/failedLog.csv", "a") or die("Unable to open file!");
    $write = [
        $e->getMessage()
    ];
    fputcsv($myfile, $write);
    fclose($myfile);
}