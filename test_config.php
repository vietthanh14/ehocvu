<?php 
require 'GoogleSheetService.php'; 
$s = GoogleSheetService::getInstance(); 
print_r($s->getHuyHocPhanConfig());
