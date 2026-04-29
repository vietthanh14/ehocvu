<?php
require_once __DIR__ . '/../core/HuyHocPhanService.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Security.php';

Security::requireAuth();

$service = new HuyHocPhanService();
$courses = $service->getCoursesCatalog();

Response::success('', ['courses' => $courses]);
