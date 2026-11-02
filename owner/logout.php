<?php
session_start();

unset($_SESSION['owner_logged_in']);
unset($_SESSION['owner_name']);
unset($_SESSION['owner_email']);
unset($_SESSION['owner_phone']);

session_destroy();

header("Location: ../login.php");
exit;
?>