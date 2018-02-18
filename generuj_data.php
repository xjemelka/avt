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
        //část s dotazy    
        $cil_databaze_otazky = $cil_databaze.'_otazky';
        $zdroj_databaze = $zdroj_databaze.'_otazky';
        $smaz = $db->query("DROP DATABASE IF EXISTS ".$cil_databaze_otazky);
        $create_db = $db->query('CREATE DATABASE '.$cil_databaze_otazky);
        $db -> query("use ".$cil_databaze_otazky);   
        $db -> query("CREATE TABLE `otazky` (
                    `id_otazky` int(11) NOT NULL AUTO_INCREMENT,
                    `kategorie` int(11) NOT NULL,
                    `dotazy_id` int(11) NOT NULL,
                    `text` varchar(1000) NOT NULL,
                    `s_q_l` varchar(1000) NOT NULL,
                    `odpoved` varchar(200) NULL,
                    PRIMARY KEY (`id_otazky`)
                   ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci");
        $db -> query("use ".$zdroj_databaze);   
        $kategorie = $db -> query("select distinct kategorie from dotazy order by kategorie");
        $kategorie = $kategorie -> fetchAll();
        foreach($kategorie as $kat){
            $db -> query("use ".$zdroj_databaze);  
            $dotaz = $db -> prepare("select dotazy_id, text, s_q_l, kategorie, nazev as promenna, dotaz as promenna_sql
                                        from (select * from dotazy where kategorie = :kat order by rand() limit 1) dotazy
                                        left join zadani_knihy_otazky.promenne on dotazy.s_q_l like CONCAT('%', promenne.nazev, '%')");
            $dotaz -> bindvalue (":kat", $kat['kategorie']);
            $dotaz -> execute();
            $dotaz = $dotaz->fetchAll();
            //preg_match_all('/(?<!\w)\$\w+/',$dotaz['s_q_l'],$matches); dá do $matches[0] array všech proměnných
            $dotaz_dotazyid = $dotaz[0]['dotazy_id'];
            $dotaz_text = $dotaz[0]['text'];
            $dotaz_sql = $dotaz[0]['s_q_l'];
            $db -> query("use ".$cil_databaze);
            foreach ($dotaz as $dot){
                if(!empty($dot['promenna'])){
                    $promenna = $db -> query($dot['promenna_sql']);
                    $promenna = $promenna->fetch();
                    $dotaz_text = str_replace($dot['promenna'],$promenna[0],$dotaz_text);
                    $dotaz_sql = str_replace($dot['promenna'],$promenna[0],$dotaz_sql);
                }
            }
            echo $dotaz_sql."</br></br>";
            $odpoved = $db -> query($dotaz_sql);
            $odpoved = $odpoved->fetch();
            $db -> query("use ".$cil_databaze_otazky);
            $otazka = $db -> prepare("INSERT INTO otazky (kategorie, dotazy_id, text, s_q_l, odpoved) values (:kat,:dotid,:text,:sql,:odpo)");
            $otazka -> bindvalue (":kat", $kat['kategorie']);
            $otazka -> bindvalue (":dotid", $dotaz_dotazyid);
            $otazka -> bindvalue (":text", $dotaz_text);
            $otazka -> bindvalue (":sql", $dotaz_sql);
            $otazka -> bindvalue (":odpo", $odpoved[0]);
            $otazka -> execute();
        }
        
        header('Location: studenti.php');
    }
?>