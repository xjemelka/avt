<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 2){
    header('Location: index.php');
}


    
    if(!empty($_POST)){
        try {
            $stav = $db->prepare("select COALESCE(od.kolikate,0) pocet_odpovedi, z.strhavani, z.max_odevzdani from uzivatele u
                            join zadani z on z.zadani=u.zadani
                            left join (select id_uzivatele,kolikate from odevzdani where id_odevzdani in (select max(id_odevzdani) from odevzdani group by id_uzivatele)) od on od.id_uzivatele = u.id_uzivatele
                            where u.id_uzivatele = :iduz");
            $stav->bindValue(":iduz", $_SESSION["user"]["id_uzivatele"]);
            $stav->execute();
            $stav = $stav -> fetch();
            $strhavani = $stav['strhavani']*$stav['pocet_odpovedi']*0.01;
            $odpoved_cislo = "odpoved".($stav['pocet_odpovedi']+1);
            if ($stav['pocet_odpovedi']<$stav['max_odevzdani']){
                //$db -> query("use ".$_SESSION["user"]["login"]."_otazky");
                
                $zodpovedel = $db -> prepare("insert into odevzdani (id_uzivatele,kolikate) values (:iduz,:kolik)");
                $zodpovedel->bindValue(":iduz", $_SESSION["user"]["id_uzivatele"]);
                $zodpovedel->bindValue(":kolik", $stav['pocet_odpovedi']+1);
                $zodpovedel->execute();
                
                $odevzdani = $db -> prepare("select id_odevzdani from odevzdani where id_uzivatele = :iduz and kolikate = :kolik");
                $odevzdani->bindValue(":iduz", $_SESSION["user"]["id_uzivatele"]);
                $odevzdani->bindValue(":kolik", $stav['pocet_odpovedi']+1);
                $odevzdani->execute();
                $odevzdani = $odevzdani->fetch();
                $odevzdani = $odevzdani['id_odevzdani'];
                
                
                $otazky = $db->prepare("select id_otazky, text, spravna_odpoved, max_bodu from otazky 
                                      where ziskanych_bodu = 0
                                      and id_uzivatele = :iduz");
                $otazky->bindValue(":iduz", $_SESSION["user"]["id_uzivatele"]);
                $otazky->execute();
                $otazky = $otazky -> fetchAll();
                
                foreach ($otazky as $otazka) {
                    if (!empty($_POST[$otazka['id_otazky']]) || is_numeric($_POST[$otazka['id_otazky']])){
                        $vstup = $_POST[$otazka['id_otazky']];
                        
                        if(strcasecmp($vstup, "NULL") == 0){
                            $vstup = "NULL";                            
                        }
                        if (strcmp($vstup, $otazka['spravna_odpoved']) == 0){
                            $ziskanych_bodu = round($otazka['max_bodu']*(1-$strhavani),2); 
                            $odpoved = $db -> prepare("update otazky set ziskanych_bodu= :bod where id_otazky = :otaz");
                            $odpoved->bindValue(":bod", $ziskanych_bodu);
                            $odpoved->bindValue(":otaz", $otazka['id_otazky']);
                            $odpoved->execute();
                        }

                        $odpoved = $db -> prepare("insert into odpovedi (id_otazky,id_odevzdani,odpoved) values (:idot,:idod,:od)");
                        $odpoved->bindValue(":idot", $otazka['id_otazky']);
                        $odpoved->bindValue(":idod", $odevzdani);
                        $odpoved->bindValue(":od", $vstup);
                        $odpoved->execute();
                    }
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    
    //$db -> query("use ".$_SESSION["user"]["login"]."_otazky");
    $otazky = $db->prepare("select ot.id_otazky, ot.text, ot.max_bodu, ot.ziskanych_bodu, od.odpoved posledni_odpoved, COALESCE(BINARY spravna_odpoved = od.odpoved,0) zodpovezeno_spravne from otazky ot
                            left join (select id_otazky,odpoved from odpovedi where id_odpovedi in (select max(id_odpovedi) from odpovedi group by id_otazky)) od on od.id_otazky=ot.id_otazky
                            where ot.id_uzivatele = :iduz
                            order by id_otazky");
    $otazky->bindValue(":iduz", $_SESSION["user"]["id_uzivatele"]);
    $otazky->execute();
    $stav = $db->prepare("select sum(ot.ziskanych_bodu) body, sum(ot.max_bodu) max_body, COALESCE(od.kolikate,0) pocet_odpovedi, z.max_odevzdani from otazky ot
							join uzivatele uz on uz.id_uzivatele = ot.id_uzivatele
                            join zadani z on z.zadani = uz.zadani
                            left join (select id_uzivatele,kolikate from odevzdani where id_odevzdani in (select max(id_odevzdani) from odevzdani group by id_uzivatele)) od on od.id_uzivatele = ot.id_uzivatele
                            where ot.id_uzivatele = :iduz");
    $stav->bindValue(":iduz", $_SESSION["user"]["id_uzivatele"]);
    $stav->execute();
    
    $tplVars["otazky"] = $otazky->fetchAll();
    $tplVars["stav"] = $stav->fetch();
    
    $tplVars["titulek"] = "Otázky";
    $tplVars["navigace"] = 2;
    $tpl->render("sotazky.latte", $tplVars);
?>