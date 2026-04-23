<?php
require_once 'GoogleSheetService.php';
$client = new \Google_Client();
$client->setApplicationName('QL Bao Luu App');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAuthConfig(GOOGLE_AUTH_JSON_PATH);
$service = new \Google_Service_Sheets($client);
$response = $service->spreadsheets_values->get(SPREADSHEET_ID, 'Config_HuyHocPhan!A2:E2');
print_r($response->getValues());
