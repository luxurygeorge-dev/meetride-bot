<?php
// Простой тест webhook'а
$log_message = date('Y-m-d H:i:s') . " - GET: " . print_r($_GET, true) . "\n";
$log_message .= date('Y-m-d H:i:s') . " - POST: " . print_r($_POST, true) . "\n";
$log_message .= date('Y-m-d H:i:s') . " - REQUEST: " . print_r($_REQUEST, true) . "\n";
$log_message .= date('Y-m-d H:i:s') . " - INPUT: " . file_get_contents('php://input') . "\n";

file_put_contents('/root/meetride/webhook_test.log', $log_message, FILE_APPEND);

echo "OK - " . date('Y-m-d H:i:s');
?>
