<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["type"] != 1 or (empty($_POST['db']) and empty($_SESSION['db']))){
    header('Location: index.php');
}
    
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
    
    $tabulky = $db->prepare("SELECT tab.table_name as nazev, exp.id_formaty, exp.id_zpusoby
    FROM information_schema.tables tab
    LEFT JOIN nastaveni.export exp
    ON tab.table_schema = exp.databaze AND tab.table_name = exp.tabulka
    WHERE tab.table_schema= :db
    AND tab.table_type='BASE TABLE'");
    $tabulky->bindvalue(":db", $_SESSION['db']);
    $tabulky->execute();
    
    $format = $db->prepare("select id_formaty, nazev FROM nastaveni.formaty");
    $format->execute();
    $zpusob = $db->prepare("select id_zpusoby, nazev FROM nastaveni.zpusoby");
    $zpusob->execute();

    $tplVars["tabulky"] = $tabulky->fetchAll();
    $tplVars["formaty"] = $format->fetchAll();
    $tplVars["zpusoby"] = $zpusob->fetchAll();
    
    $tplVars["titulek"] = "Nastavení tabulek";
    $tplVars["navigace"] = 1;
    $tpl->render("tabulky.latte", $tplVars);
?>