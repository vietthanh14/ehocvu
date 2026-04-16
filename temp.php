<?php
$reqs = json_decode(file_get_contents('cache/requests_all.json'), true);
$students = [];
foreach($reqs as $i => $row) {
    if ($i === 0) {
        $students[] = ['Mã SV', 'Họ tên', 'Ngày sinh', 'SDT', 'Tên khoa', 'Tên hệ', 'Tên lớp', 'Chuyên ngành', 'Niên khóa'];
        continue;
    }
    if (count($row) > 10) {
        $sv = [$row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9]];
        $students[] = $sv;
    }
}
file_put_contents('cache/students_all.json', json_encode($students, JSON_UNESCAPED_UNICODE));
echo 'done';
