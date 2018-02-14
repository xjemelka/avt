<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */
    if ($_SESSION["user"]["typ"] != 1 && (empty($_POST['db']) && (empty($_SESSION['novy_student']) || empty($_SESSION['zadani_novy_student']) ))){
        header('Location: index.php');
    }
   
    //pokud nedostane parametr db, tak to není generování dat pro admina a jednoduše projde všechny studentské účty bez vygenerovaných dat
    if (!empty($_POST['db'])){
        $zdroj_databaze = $_POST['db'];
        $cil_databaze = $_SESSION["user"]["login"];
        $stahni_po_vygenerovani = 1;
        $_SESSION["user"]["zadani"] = $_POST['db'];
    }    
    else{
        $zdroj_databaze = $_SESSION['zadani_novy_student'];
        $cil_databaze = $_SESSION['novy_student'];
        $stahni_po_vygenerovani = 0;
        unset ($_SESSION['zadani_novy_student']);
        unset ($_SESSION['novy_student']);
    }
    $aktualizuj_zadani = $db->prepare("UPDATE nastaveni.uzivatele SET zadani = :db WHERE login = :log");
    $aktualizuj_zadani->bindvalue(":db", $zdroj_databaze);
    $aktualizuj_zadani->bindvalue(":log", $cil_databaze);
    $aktualizuj_zadani->execute();
    
    $tabulky = $db->prepare("SELECT tab.table_name as nazev, zpu.nazev as zpusob
    FROM information_schema.tables tab
    INNER JOIN nastaveni.export exp
    ON tab.table_schema = exp.databaze AND tab.table_name = exp.tabulka
    INNER JOIN nastaveni.zpusoby zpu
    ON exp.id_zpusoby = zpu.id_zpusoby
    WHERE tab.table_schema= :db
    AND tab.table_type='BASE TABLE'
    ORDER BY exp.poradi");
    $tabulky->bindvalue(":db", $zdroj_databaze);
    $tabulky->execute();
    $tabulky = $tabulky->fetchAll();
    //vytvoření databázového schéma
    $smaz = $db->query("DROP DATABASE IF EXISTS ".$cil_databaze);
    $create_db = $db->query('CREATE DATABASE '.$cil_databaze);
        foreach ($tabulky as $tabulka) {   
            $create = $db->query("SHOW CREATE TABLE ".$zdroj_databaze.'.'.$tabulka['nazev']);
            //$create = $db->prepare("SHOW CREATE TABLE :tb");
            //$create->bindvalue(":tb", $zdroj_databaze.'.'.$tabulka['nazev']);
            
            $create = $create->fetch();          
            $create = preg_replace('/CREATE TABLE `'.$tabulka['nazev'].'`/', 'CREATE TABLE '.$cil_databaze.'.'.$tabulka['nazev'], $create['Create Table']);
            //vytvoření tabulek
            $create_tb = $db->query($create);
            
            $copy = $db->query("INSERT INTO ".$cil_databaze.'.'.$tabulka['nazev']." (SELECT * FROM ".$zdroj_databaze.'.'.$tabulka['nazev'].")");
        }
        foreach ($tabulky as $tabulka) {  
            if ($tabulka['zpusob']=="castecne"){
                $nazev = $cil_databaze.".".$tabulka['nazev'];
                $primarni_klic = $db->prepare("SELECT k.column_name sloupec
                FROM information_schema.table_constraints t
                JOIN information_schema.key_column_usage k
                USING(constraint_name,table_schema,table_name)
                WHERE t.constraint_type='PRIMARY KEY'
                  AND t.table_schema= :db
                  AND t.table_name= :tb");
                $primarni_klic->bindvalue(":db", $cil_databaze);
                $primarni_klic->bindvalue(":tb", $tabulka['nazev']);
                $primarni_klic->execute();
                $primarni_klic = $primarni_klic->fetch();
                $primarni_klic = $primarni_klic['sloupec'];
                
                $count = $db->query("SELECT count(*) pocet FROM ".$nazev);
                $count = $count->fetch();
                $count = round($count['pocet']/2,0,PHP_ROUND_HALF_DOWN);
                
                //mariaDB neumí moc where in (... limit), proto musí být obalena v ještě jednom selectu
                //tato část je ale zodpovědná za náhodnost v datech - vymaže polovinu záznamů v označených tabulkách
                $smaz = $db->query("DELETE FROM ".$nazev." WHERE ".$primarni_klic." IN (SELECT * FROM (SELECT ".$primarni_klic." FROM ".$nazev." ORDER BY RAND() LIMIT ".$count.") as t)");
            }
        }
    if ($stahni_po_vygenerovani == 1){
        header('Location: generuj_soubory.php');
    }
    else{
        header('Location: studenti.php');
    }
?>