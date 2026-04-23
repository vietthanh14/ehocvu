<?php
require_once __DIR__ . '/../core/GoogleSheetService.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Security.php';

Security::requireAuth();

$service = GoogleSheetService::getInstance();
$courses = $service->getCoursesCatalog();

Response::success('', ['courses' => $courses]);
