<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */
    $zdroj_databaze = 'zadani_a';
    $cil_databaze = 'xtestovani';
    $tabulky = $db->prepare("SELECT tab.table_name as nazev, zpu.nazev as zpusob
    FROM information_schema.tables tab
    INNER JOIN nastaveni.export exp
    ON tab.table_schema = exp.databaze AND tab.table_name = exp.tabulka
    INNER JOIN nastaveni.zpusoby zpu
    ON exp.id_zpusoby = zpu.id_zpusoby
    WHERE tab.table_schema= :db
    AND tab.table_type='BASE TABLE'
    ORDER BY exp.poradi_generovani");
    $tabulky->bindvalue(":db", $zdroj_databaze);
    $tabulky->execute();
    $tabulky = $tabulky->fetchAll();
    //vytvoření databázového schéma
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
                $count = round($count['pocet']/2,PHP_ROUND_HALF_DOWN);
                
                //mariaDB neumí moc where in (... limit), proto musí být obalena v ještě jednom selectu
                $smaz = $db->query("DELETE FROM ".$nazev." WHERE ".$primarni_klic." IN (SELECT * FROM (SELECT ".$primarni_klic." FROM ".$nazev." ORDER BY RAND() LIMIT ".$count.") as t)");
            }
        }
?>