<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1 or empty($_SESSION['db'])){
    header('Location: index.php');
}
    
    $dotaz_tabulky = "SELECT tab.table_name as nazev, exp.id_formaty, exp.id_zpusoby, exp.poradi
    FROM information_schema.tables tab
    LEFT JOIN nastaveni.export exp
    ON tab.table_schema = exp.databaze AND tab.table_name = exp.tabulka
    WHERE tab.table_schema= :db
    AND tab.table_type='BASE TABLE'";
    
    $tabulky = $db->prepare($dotaz_tabulky);
    $tabulky->bindvalue(":db", $_SESSION['db']);
    $tabulky->execute();
   
    $format = $db->prepare("select id_formaty, nazev FROM nastaveni.formaty");
    $format->execute();
    $zpusob = $db->prepare("select id_zpusoby, nazev FROM nastaveni.zpusoby");
    $zpusob->execute();
    $info = $db->prepare("select aktualni_zadani, strhavani FROM nastaveni.zadani where zadani = :zad");
    $info -> bindValue(":zad", $_SESSION['db']);
    $info->execute();
    
    if(!empty($_POST)){
        try {
            $tabulky2 = $db->prepare($dotaz_tabulky);
            $tabulky2->bindvalue(":db", $_SESSION['db']);
            $tabulky2->execute();
            $tabulky2 = $tabulky2->fetchAll();
            $chyba = 0;
            foreach ($tabulky2 as $tabulka) {
                //kontrola vyplnění údajů
                if (empty($_POST[$tabulka['nazev']."_format"]) || empty($_POST[$tabulka['nazev']."_zpusob"]) || empty($_POST[$tabulka['nazev']."_poradi"]) ){
                    $chyba = 1;
                }
            }
            if($chyba==1){
                $tplVars['hlaska'] = "Nebylo zadáno vše";
                $tplVars["form"] = $_POST;
            }
            else{
                //update nebo insert nastavení, vstup je platný
                $tabulky3 = $db->prepare($dotaz_tabulky);
                $tabulky3->bindvalue(":db", $_SESSION['db']);
                $tabulky3->execute();
                $tabulky3 = $tabulky3->fetchAll();
                foreach ($tabulky2 as $tabulka) {
                    if (empty($tabulka['id_formaty'])){
                        $insert = $db->prepare("insert into nastaveni.export (databaze,tabulka,id_formaty,id_zpusoby,poradi) values (:db, :tb, :fo, :zp, :po)");
                        $insert->bindValue(":db", $_SESSION['db']);
                        $insert->bindValue(":tb", $tabulka['nazev']);
                        $insert->bindValue(":fo", $_POST[$tabulka['nazev']."_format"]);
                        $insert->bindValue(":zp", $_POST[$tabulka['nazev']."_zpusob"]);
                        $insert->bindValue(":po", $_POST[$tabulka['nazev']."_poradi"]);
                        $insert->execute();
                    }
                    else{
                        $update = $db->prepare("update nastaveni.export set id_formaty=:fo,id_zpusoby=:zp,poradi=:po where databaze=:db and tabulka=:tb");
                        $update->bindValue(":db", $_SESSION['db']);
                        $update->bindValue(":tb", $tabulka['nazev']);
                        $update->bindValue(":fo", $_POST[$tabulka['nazev']."_format"]);
                        $update->bindValue(":zp", $_POST[$tabulka['nazev']."_zpusob"]);
                        $update->bindValue(":po", $_POST[$tabulka['nazev']."_poradi"]);
                        $update->execute();
                    }
                }
                //pokud zadání nemá záznam mezi nastavením zadání, přidej ho a uprav aktuální/strhávání
                $existuje = $db -> prepare("select count(*) pocet from nastaveni.zadani where zadani = :zad");
                $existuje -> bindValue(":zad", $_SESSION['db']);
                $existuje -> execute();
                $existuje = $existuje->fetch();
                if ($existuje['pocet']==0){
                    $insert = $db->prepare("insert into nastaveni.zadani (zadani) values (:zad)");
                    $insert -> bindValue(":zad", $_SESSION['db']);
                    $insert -> execute();
                }
                if (isset($_POST['aktualni_zadani'])){
                    $db -> query("update nastaveni.zadani set aktualni_zadani = 0");
                    $update = $db->prepare("update nastaveni.zadani set aktualni_zadani = 1 where zadani = :zad");
                    $update -> bindValue(":zad", $_SESSION['db']);
                    $update -> execute();
                }
                if (isset($_POST['strhavani'])){
                    $update = $db->prepare("update nastaveni.zadani set strhavani = :strh where zadani = :zad");
                    $update -> bindValue(":strh", $_POST['strhavani']);
                    $update -> bindValue(":zad", $_SESSION['db']);
                    $update -> execute();
                }
                $tplVars['hlaska'] = "Nastavení úspěšně aktualizováno";
                $tplVars["form"] = $_POST;
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    $tplVars["tabulky"] = $tabulky->fetchAll();
    $tplVars["formaty"] = $format->fetchAll();
    $tplVars["zpusoby"] = $zpusob->fetchAll();
    $tplVars["info"] = $info->fetch();
    $tplVars["db"] = $_SESSION['db'];
    
    $tplVars["titulek"] = "Nastavení tabulek";
    $tplVars["navigace"] = 1;
    $tpl->render("tabulky.latte", $tplVars);
?>

