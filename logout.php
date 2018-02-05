<?php

require 'start.php';    //nebo session_start();
session_destroy();
header("Location: login.php");

