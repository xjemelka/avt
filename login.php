<?php

require 'start.php';

$tplVars["form"] = [
    "log" => ""
];

if(!empty($_POST['log']) && !empty($_POST['psw'])){
    
    try {
        
        $stmt = $db->prepare("SELECT * FROM uzivatele WHERE login = :log");
        $stmt->bindvalue(":log", $_POST['log']);
        $stmt->execute();
        
        $user = $stmt->fetch();
        if($user){
            $hash = sha1($_POST['psw']);
            if($hash == $user['heslo']){
                if (!isset($user['prvni_prihlaseni'])){
                    $stmt = $db->prepare("UPDATE uzivatele SET prvni_prihlaseni = now() WHERE login = :log");
                    $stmt->bindvalue(":log", $_POST['log']);
                    $stmt->execute();
                }
                $_SESSION['user'] = $user;
                header('Location: index.php');
                exit;
            }else{
                $tplVars['hlaska'] = "Heslo se neshoduje.";
                $tplVars["form"] = $_POST;
            }
        }else{
            $tplVars['hlaska'] = "Uzivatel nenalezen.";
        } 
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

$tplVars["titulek"] = "Přihlášení";
$tpl->render("login.latte", $tplVars);

