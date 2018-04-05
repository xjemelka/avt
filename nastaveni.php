<?php

require 'start.php';
require 'overeni.php';


if(!empty($_GET['login']) && $_SESSION["user"]["typ"] == 1 ){
        
    $stmt = $db->prepare("SELECT login FROM nastaveni.uzivatele WHERE login = :log");
    $stmt->bindvalue(":log", $_GET['login']);
    $stmt->execute();
    $uzivatel = $stmt ->fetch();
    if(!$uzivatel){
        header('Location: studenti.php');
    }
    $zmena_studenta = $uzivatel['login'];
    
}
else{
    $zmena_studenta = "";
}

if(!empty($_POST['psw1'])){
    
    try {
        if ($zmena_studenta!=""){
            $uzivatel = $zmena_studenta;
        }
        else{
            $uzivatel = $_SESSION["user"]["login"];
        }
        
        $stmt = $db->prepare("SELECT * FROM uzivatele WHERE login = :log");
        $stmt->bindvalue(":log", $uzivatel);
        $stmt->execute();
        
        $user = $stmt->fetch();
        if($user){
            if(password_verify($_POST['psw'],$user['heslo']) || $zmena_studenta!=""){
                if ($_POST['psw1']==$_POST['psw2']){
                    $hash = password_hash($_POST['psw1'], PASSWORD_DEFAULT);
                    $update = $db -> prepare("update nastaveni.uzivatele set heslo = :hes where login = :log");
                    $update -> bindValue(":hes",$hash);
                    $update -> bindValue(":log",$user['login']);
                    $update -> execute();
                    $tplVars['hlaska'] = "Heslo bylo úspěšně změněno.";
                
                }else{
                    $tplVars['hlaska'] = "Nové hesla se neshodují.";
                }
            }else{
                $tplVars['hlaska'] = "Původní heslo bylo nesprávně zadané.";
            }
        }else{
            $tplVars['hlaska'] = "Uzivatel nenalezen.";
        } 
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

$tplVars["uzivatel"] = $zmena_studenta;
$tplVars["titulek"] = "Změna hesla ".$zmena_studenta;
$tplVars["navigace"] = 9;
$tpl->render("nastaveni.latte", $tplVars);

