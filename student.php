<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1 or empty($_GET['student'])){
    header('Location: index.php');
}
    $existuje = $db->prepare("select count(*) pocet from nastaveni.uzivatele where login = :login");
    $existuje->bindValue(":login", $_GET['student']);
    $existuje->execute();
    $existuje = $existuje -> fetch();
    if ($existuje['pocet']==0){
        header('Location: index.php');
    }
    
    $db -> query("use ".$_GET['student']."_otazky");
    $otazky = $db->query("select id_otazky, text, s_q_l, odpoved1, odpoved2, odpoved3, odpoved4, spravna_odpoved, 
                                CASE spravna_odpoved
                                    WHEN odpoved4 THEN 4
                                    WHEN odpoved3 THEN 3
                                    WHEN odpoved2 THEN 2
                                    WHEN odpoved1 THEN 1
                                    ELSE 0
                                END AS spravna_pokolikate
                            from otazky order by id_otazky");
    
    $stav = $db->prepare("select 
                                    login,
                                    body,
                                    max_body,
                                    prvni_prihlaseni,
                                    odpoved1,
                                    odpoved2,
                                    odpoved3,
                                    odpoved4,
                                    CASE COALESCE(odpoved4, odpoved3, odpoved2, odpoved1)
                                        WHEN odpoved4 THEN 4
                                        WHEN odpoved3 THEN 3
                                        WHEN odpoved2 THEN 2
                                        WHEN odpoved1 THEN 1
                                        ELSE 0
                                    END AS pocet_odpovedi
                                    from nastaveni.uzivatele
                                    where login = :login");
    $stav->bindValue(":login", $_GET['student']);
    $stav->execute();
    
    $tplVars["otazky"] = $otazky->fetchAll();
    $tplVars["stav"] = $stav->fetch();
    
    $tplVars["titulek"] = "Přehled studenta ".$_GET['student'];
    $tplVars["navigace"] = 2;
    $tpl->render("student.latte", $tplVars);
?>

