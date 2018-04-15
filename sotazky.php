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
            $stav = $db->prepare("select 
                                    body,
                                    CASE COALESCE(odpoved4, odpoved3, odpoved2, odpoved1)
                                        WHEN odpoved4 THEN 4
                                        WHEN odpoved3 THEN 3
                                        WHEN odpoved2 THEN 2
                                        WHEN odpoved1 THEN 1
                                        ELSE 0
                                    END AS pocet_odpovedi,
                                    zadani
                                  from nastaveni.uzivatele
                                  where login = :login");
            $stav->bindValue(":login", $_SESSION["user"]["login"]);
            $stav->execute();
            $stav = $stav -> fetch();
            $strhavani = $db -> prepare("select strhavani from nastaveni.zadani where zadani = :zad");
            $strhavani -> bindValue(":zad",$stav['zadani']);
            $strhavani -> execute();
            $strhavani = $strhavani -> fetch();
            $strhavani = $strhavani['strhavani']*$stav['pocet_odpovedi']*0.01;
            $body = $stav['body'];
            $odpoved_cislo = "odpoved".($stav['pocet_odpovedi']+1);
            if ($stav['pocet_odpovedi']!=4){
                $db -> query("use ".$_SESSION["user"]["login"]."_otazky");
                $otazky = $db->query("select id_otazky, text, spravna_odpoved, max_bodu from otazky 
                                      where COALESCE(spravna_odpoved = COALESCE(odpoved4,odpoved3,odpoved2,odpoved1),0) = 0
                                      order by id_otazky");
                $otazky = $otazky -> fetchAll();
                $zodpovezeno = 0;
                foreach ($otazky as $otazka) {
                    if (!empty($_POST[$otazka['id_otazky']]) || is_numeric($_POST[$otazka['id_otazky']])){
                        $ziskanych_bodu = 0;
                        $vstup = $_POST[$otazka['id_otazky']];
                        if(strcasecmp($vstup, "NULL") == 0){
                            $vstup = "NULL";                            
                        }
                        if (strcmp($vstup, $otazka['spravna_odpoved']) == 0){
                            $ziskanych_bodu = round($otazka['max_bodu']*(1-$strhavani),2);
                            $body = $body + $ziskanych_bodu;
                        }
                        $zodpovezeno++;
                        $odpoved = $db -> prepare("update otazky set ".$odpoved_cislo."= :odpo, ziskanych_bodu= :bod where id_otazky = :otaz");
                        $odpoved->bindValue(":odpo", $vstup);
                        $odpoved->bindValue(":bod", $ziskanych_bodu);
                        $odpoved->bindValue(":otaz", $otazka['id_otazky']);
                        $odpoved->execute();
                    }
                }
                if ($zodpovezeno>0){
                    $zodpovedel = $db -> prepare("update nastaveni.uzivatele set ".$odpoved_cislo."=now(), body = :body where login = :login");
                    $zodpovedel->bindValue(":body", $body);
                    $zodpovedel->bindValue(":login", $_SESSION["user"]["login"]);
                    $zodpovedel->execute();
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    
    $db -> query("use ".$_SESSION["user"]["login"]."_otazky");
    $otazky = $db->query("select id_otazky, text, max_bodu, ziskanych_bodu, COALESCE(odpoved4,odpoved3,odpoved2,odpoved1) posledni_odpoved, COALESCE(BINARY spravna_odpoved = COALESCE(odpoved4,odpoved3,odpoved2,odpoved1),0) zodpovezeno_spravne from otazky order by id_otazky");
    $stav = $db->prepare("select 
                                    body,  max_body,
                                    CASE COALESCE(odpoved4, odpoved3, odpoved2, odpoved1)
                                        WHEN odpoved4 THEN 4
                                        WHEN odpoved3 THEN 3
                                        WHEN odpoved2 THEN 2
                                        WHEN odpoved1 THEN 1
                                        ELSE 0
                                    END AS pocet_odpovedi
                                    from nastaveni.uzivatele
                                    where login = :login");
    $stav->bindValue(":login", $_SESSION["user"]["login"]);
    $stav->execute();
    
    $tplVars["otazky"] = $otazky->fetchAll();
    $tplVars["stav"] = $stav->fetch();
    
    $tplVars["titulek"] = "Otázky";
    $tplVars["navigace"] = 2;
    $tpl->render("sotazky.latte", $tplVars);
?>