<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */
    if ($_SESSION["user"]["typ"] != 1){
        header('Location: index.php');
    }
   
    //pokud nedostane parametr db, tak to není generování dat pro admina a jednoduše projde všechny studentské účty bez vygenerovaných dat
    if (!empty($_POST['db'])){
        $zdroj_databaze = $_POST['db'];
        //$cil_databaze = $_SESSION["user"]["login"];
        $uzivatele_bez_dat = array();
        $uzivatele_bez_dat[0]['login']=$_SESSION["user"]["login"];
        $stahni_po_vygenerovani = 1;
        $_SESSION["user"]["zadani"] = $_POST['db'];
    }    
    else{
        $zadani = $db->query('SELECT zadani, id_zadani from nastaveni.zadani where aktualni_zadani=1');
        $zadani = $zadani->fetch();
        $zdroj_databaze = $zadani['zadani'];
        $zdroj_databaze_id = $zadani['id_zadani'];
        $uzivatele_bez_dat = $db->query('select login from nastaveni.uzivatele where zadani is null and typ != 1');
        $uzivatele_bez_dat = $uzivatele_bez_dat -> fetchAll();
        //$cil_databaze = $_SESSION['novy_student'];
        $stahni_po_vygenerovani = 0;
    }
    foreach ($uzivatele_bez_dat as $uzivatel_bez_dat){
        $cil_databaze = $uzivatel_bez_dat['login'];
        
        $aktualizuj_zadani = $db->prepare("UPDATE nastaveni.uzivatele SET zadani = :db WHERE login = :log");
        $aktualizuj_zadani->bindvalue(":db", $zdroj_databaze);
        $aktualizuj_zadani->bindvalue(":log", $cil_databaze);
        $aktualizuj_zadani->execute();

        $tabulky = $db->prepare("SELECT tab.table_name as nazev, zpu.nazev as zpusob
        FROM nastaveni.zadani zad
        JOIN information_schema.tables tab
        ON tab.table_schema = zad.zadani
        INNER JOIN nastaveni.export exp
        ON tab.table_name = exp.tabulka AND zad.id_zadani = exp.id_zadani
        INNER JOIN nastaveni.zpusoby zpu
        ON exp.id_zpusoby = zpu.id_zpusoby
        WHERE zadani = :db
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
                
                //tato část je ale zodpovědná za náhodnost v datech - vymaže polovinu záznamů v označených tabulkách
                $smaz = $db->query("DELETE FROM ".$nazev." ORDER BY RAND() LIMIT ".$count);
            }
        }
        if ($stahni_po_vygenerovani == 1){
            header('Location: generuj_soubory.php');
            die();
        }
        else{
            //část s dotazy    
            $cil_databaze_otazky = $cil_databaze.'_otazky';
            $smaz = $db->query("DROP DATABASE IF EXISTS ".$cil_databaze_otazky);
            $create_db = $db->query('CREATE DATABASE '.$cil_databaze_otazky);
            $db -> query("use ".$cil_databaze_otazky);   
            $db -> query("CREATE TABLE `otazky` (
                        `id_otazky` int(11) NOT NULL AUTO_INCREMENT,
                        `kategorie` int(11) NOT NULL,
                        `dotazy_id` int(11) NOT NULL,
                        `text` varchar(1000) NOT NULL,
                        `s_q_l` varchar(1000) NOT NULL,
                        `spravna_odpoved` varchar(200) NOT NULL DEFAULT 'NULL',
                        `max_bodu` int(11) NOT NULL,
                        `ziskanych_bodu` decimal(10,2) NOT NULL DEFAULT 0,
                        `odpoved1` varchar(200) DEFAULT NULL,
                        `odpoved2` varchar(200) DEFAULT NULL,
                        `odpoved3` varchar(200) DEFAULT NULL,
                        `odpoved4` varchar(200) DEFAULT NULL,
                        PRIMARY KEY (`id_otazky`)
                       ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci");
            $db -> query("use nastaveni");  
            $kategorie = $db -> prepare("select id_kategorie, otazek, body from kategorie where id_zadani = :zad order by id_kategorie");
            $kategorie -> bindvalue (":zad", $zdroj_databaze_id);
            $kategorie -> execute();
            $kategorie = $kategorie -> fetchAll();
            $body_celkem = 0;
            foreach($kategorie as $kat){
                $db -> query("use nastaveni"); 
                $dotazy = $db -> prepare("select id_dotazy from dotazy where id_kategorie = :kat order by rand() limit ".$kat['otazek']);
                $dotazy -> bindvalue (":kat", $kat['id_kategorie']);
                $dotazy -> execute();
                $dotazy = $dotazy->fetchAll();
                foreach($dotazy as $dot){
                    $dotaz = $db -> prepare("select id_dotazy, text, s_q_l, id_kategorie, nazev as promenna, dotaz as promenna_sql
                                                from dotazy
                                                left join zadani_knihy_otazky.promenne on dotazy.s_q_l like CONCAT('%', promenne.nazev, '%')
                                                where id_dotazy = :dot");
                    $dotaz -> bindvalue (":dot", $dot['id_dotazy']);
                    $dotaz -> execute();
                    $dotaz = $dotaz->fetchAll();
                    //preg_match_all('/(?<!\w)\$\w+/',$dotaz['s_q_l'],$matches); dá do $matches[0] array všech proměnných
                    $dotaz_dotazyid = $dotaz[0]['id_dotazy'];
                    $dotaz_text = $dotaz[0]['text'];
                    $dotaz_sql = $dotaz[0]['s_q_l'];
                    $db -> query("use ".$cil_databaze);
                    foreach ($dotaz as $dot){
                        if(!empty($dot['promenna'])){
                            $promenna = $db -> query($dot['promenna_sql']);
                            $promenna = $promenna->fetch();
                            $dotaz_text = str_replace($dot['promenna'],$promenna[0],$dotaz_text);
                            $promenna[0] = str_replace("'","''", $promenna[0]);
                            $dotaz_sql = str_replace($dot['promenna'],$promenna[0],$dotaz_sql);
                        }
                    }
                    $odpoved = $db -> query($dotaz_sql);
                    $odpoved = $odpoved->fetch();
                    $odpoved = $odpoved[0];
                    if (!isset($odpoved)){
                        $odpoved = "NULL";
                    }
                    $db -> query("use ".$cil_databaze_otazky);
                    $otazka = $db -> prepare("INSERT INTO otazky (kategorie, dotazy_id, text, s_q_l, spravna_odpoved, max_bodu) values (:kat,:dotid,:text,:sql,:odpo,:bod)");
                    $otazka -> bindvalue (":kat", $kat['id_kategorie']);
                    $otazka -> bindvalue (":dotid", $dotaz_dotazyid);
                    $otazka -> bindvalue (":text", $dotaz_text);
                    $otazka -> bindvalue (":sql", $dotaz_sql);
                    $otazka -> bindvalue (":odpo", $odpoved);
                    $otazka -> bindvalue (":bod", $kat['body']);
                    $otazka -> execute();
                    $body_celkem=$body_celkem + $kat['body'];
                }
            }
            $max_body = $db -> prepare ("update nastaveni.uzivatele set max_body = :max where login = :log");
            $max_body -> bindvalue (":max", $body_celkem);
            $max_body -> bindvalue (":log", $cil_databaze);
            $max_body -> execute();


        }
    } 
    header('Location: studenti.php');
?>