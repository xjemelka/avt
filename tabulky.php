<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["type"] = 1){

    $stmt = $db->prepare("SELECT table_name as nazev
    FROM information_schema.tables
    WHERE table_schema= :db
    AND table_type='BASE TABLE'");
    $stmt->bindvalue(":db", $_SESSION['db']);
            $stmt->execute();
    



    if(!empty($_POST['db'])){

        //ochrana aby nebyla vybrána neexistující/špatná db
        try {
            $stmt = $db->prepare("SELECT schema_name AS nazev
            FROM information_schema.SCHEMATA
            WHERE schema_name = :db
            and schema_name NOT IN ('nastaveni', 'information_schema', 'mysql', 'performance_schema', 'phpmyadmin')");
            $stmt->bindvalue(":db", $_POST['db']);
            $stmt->execute();

            $db = $stmt->fetch();
            if($db){
                $_SESSION['db'] = $_POST['db'];
                header('Location: tabulky.php');
                exit;
            }else{
                $tplVars['hlaska'] = "Databáze nenalezena.";
            } 
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

}
    $format = ['sql','txt','json','csv','html','náhodný'];
    $zpusob = ['vše','částečně'];
    
    $tplVars["tabulky"] = $stmt->fetchAll();
    $tplVars["formaty"] = $format;
    $tplVars["zpusoby"] = $zpusob;
    
    $tplVars["titulek"] = "Nastavení tabulek";
    $tplVars["navigace"] = 1;
    $tpl->render("tabulky.latte", $tplVars);
?>