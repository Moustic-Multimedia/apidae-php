<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

$config = [];
require __DIR__ . "/requires.inc.php";

$client = new \ApidaePHP\Client($config);

/**
 * In a classical situation, Apidae should send an http request after every new export :
 *  - Nightly, daily, weekly or monthly depending on the project configuration
 *  - or every time user click on "Export exceptionnel", so this can happen anytime in a day : https://base.apidae-tourisme.com/diffuser/projet/XXXX#tab-operations-exceptionnelles
 * 
 * The notification is an http call on an url on your project configured in "Configuration technique" on your project :
 * https://base.apidae-tourisme.com/diffuser/projet/XXXX#tab-configuration-technique
 * 
 * When you receive the notification, just store it anywhere (database...) :
 * you should probably not make any treatment on the notification (because it's an http call that can have a short timeout).
 * 
 * In most cases, a cron is supposed to check if there is a new notification that require something on your side (extraction, indexation...)
 * This cron can be daily (prefer something after 6am GMT) or more frequent if you want to catch the exceptionnal exports that can happen during the day (manually triggered)
 * 
 * After your export/indexation, you have to notify Apidae that everything is correct on your side :
 * $client->confirmExport(['hash' => $exportNotification['urlConfirmation']]);
 * This will be displayed in Apidae so your client will know that the job is done on your side :
 * https://base.apidae-tourisme.com/diffuser/projet/XXXX#tab-dernieres-generations : "Etat : Généré et intégré"
 * 
 */

/*
     * Export
     */

// Notifications comes from a $_POST from Apidae
// Please note that $exportNotification can be defined in ../config.inc.php for testing this demo
// Here is an example of what a $_POST could contain
/*
        $exportNotification = array(
            "statut" => "SUCCESS",
            "reinitialisation" => "false",
            "projetId" => "672",
            "urlConfirmation" => "https://api.apidae-tourisme.com/api/v002/export/confirmation?hash=672_20150106-1344_V4BjvT",
            "ponctuel" => "true",
            "urlRecuperation" => "https://export.apidae-tourisme.com/exports/672_20150106-1344_V4BjvT.zip",
        ); 
     */


if (!isset($exportNotification)) {
    die('Please fill $exportNotification in examples/demo-export.php or config.inc.php');
}

/*
    if ($exportNotification['projetId'] != $projetId)
        die('It\'s a good practice to check if the notification is really meant for your current project, especially if you have multiple projects');
    */

/**
 * First we clean older tests
 */
/** @var bool $clean */
$clean = false;
try {
    $clean = $client->cleanExportFiles();
} catch (Exception $e) {
    echo $e->getMessage();
}

if (!$clean) {
    echo 'Clean operation failed : process stopped';
    die();
}

/**
 * Download and extract zip to temp directory ($config['exportDir'] in config.inc.php)
 */
/** @var Finder $exportFiles */
$exportFiles = false;
try {
    $exportFiles = $client->getExportFiles(['url' => $exportNotification['urlRecuperation']]);
} catch (Exception $e) {
    echo $e->getMessage();
}
if (!$exportFiles instanceof Finder) {
    '$exportFiles is not an instance of Finder : process stopped';
    die();
}

/**
 * Indexation process : we have all data we need in $exportFiles
 */
foreach ($exportFiles->name('objets_modifies-*') as $file) {

    /** @var SplFileInfo $file */
    echo $file->getRealpath() . "\n";

    /** @var array $json */
    $json = json_decode($file->getContents(), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'File content is not a valid JSON String' . "\n";
        continue;
    }
    /**
     * Do whatever you need to do with the datas (store in DB...)
     * In this example we only display json content
     */
    echo json_encode($json, JSON_PRETTY_PRINT) . "\n";
}

/**
 * After everything is done, you can confirmExport to change the export message of the project on Apidae from "Généré" to "Généré et intégré"
 * This is usefull for your client to know if you have correctly integrated every export, or is some of them has not been treated.
 */
$confirmation = $client->confirmExport(['hash' => $exportNotification['urlConfirmation']]);
