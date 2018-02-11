<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */
    $zdroj_databaze = 'zadani_a';
    $cil_databaze = 'xtestovani';
    $tabulky = $db->prepare("SELECT tab.table_name as nazev
    FROM information_schema.tables tab
    INNER JOIN nastaveni.export exp
    ON tab.table_schema = exp.databaze AND tab.table_name = exp.tabulka
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
            $create->execute();
            $create = $create->fetch();          
            $create = preg_replace('/CREATE TABLE `'.$tabulka['nazev'].'`/', 'CREATE TABLE '.$cil_databaze.'.'.$tabulka['nazev'], $create['Create Table']);
            //vytvoření tabulek
            $create_tb = $db->query($create);
        }
?>