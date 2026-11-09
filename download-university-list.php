<?php
require_once __DIR__ . '/university-list.php';
$file = UNIVERSITY_LIST_FILE;

if (!file_exists($file)) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="University_List.xlsx"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));

readfile($file);
exit;
?>
