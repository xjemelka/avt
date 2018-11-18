<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1){
    header('Location: sindex.php');
}
    /*$stmt = $db->query("SELECT schema_name AS nazev
    FROM information_schema.SCHEMATA
    WHERE schema_name NOT IN ('nastaveni', 'information_schema', 'mysql', 'performance_schema', 'phpmyadmin')
    AND schema_name LIKE 'zadani\_%'
    AND schema_name NOT LIKE '%\_otazky'");*/

    $stmt = $db->query("select zadani as nazev from nastaveni.zadani order by zadani");
    
    $tplVars["titulek"] = "Přehled zadání";
    $tplVars["databaze"] = $stmt->fetchAll();


    if(!empty($_POST['db'])){

        //ochrana aby nebyla vybrána neexistující/špatná db
        try {
            $stmt = $db->prepare("SELECT schema_name AS nazev
            FROM information_schema.SCHEMATA
            WHERE schema_name = :db
            and schema_name NOT IN ('nastaveni', 'information_schema', 'mysql', 'performance_schema', 'phpmyadmin')
            and schema_name like 'zadani_%'");
            $stmt->bindvalue(":db", $_POST['db']);
            $stmt->execute();

            $zadani = $stmt->fetch();
            if($zadani){
                $stmt = $db->prepare("SELECT id_zadani from nastaveni.zadani where zadani = :db");
                $stmt->bindvalue(":db", $_POST['db']);
                $stmt->execute();
                $existuje = $stmt -> fetch();
                if (isset($existuje['id_zadani'])){
                    $_SESSION['db'] = $_POST['db'];
                    $_SESSION['id_db'] = $existuje['id_zadani'];
                    header('Location: zadani.php');
                    exit;
                }
            }else{
                $tplVars['hlaska'] = "Databáze nenalezena.";
            } 
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }


    $tplVars["navigace"] = 1;
    $tpl->render("index.latte", $tplVars);
?>