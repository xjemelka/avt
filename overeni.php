<?php

if(empty($_SESSION["user"])){
    header("Location: login.php");
    exit;
}

$tplVars["user"] = $_SESSION["user"];

