<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1 || (empty($_GET['student']) && empty($_POST['login']))){
    header('Location: index.php');
}
    if (empty($_POST['login'])){
        $existuje = $db->prepare("select count(*) pocet, id_uzivatele from nastaveni.uzivatele where login = :login");
        $existuje->bindValue(":login", $_GET['student']);
        $existuje->execute();
        $existuje = $existuje -> fetch();
        if ($existuje['pocet']<>1){
            header('Location: index.php');
        }
        $id_uzivatele = $existuje['id_uzivatele'];
    }
    
    if(!empty($_POST)){
        try {
            //$db -> query("use ".$_POST['login']."_otazky");
            $otazky = $db->prepare("select o.id_otazky, o.ziskanych_bodu from otazky o join uzivatele u on u.id_uzivatele = o.id_uzivatele where u.login = :login order by o.id_otazky");
            $otazky->bindValue(":login",  $_POST['login']);
            $otazky->execute();
            $otazky = $otazky->fetchAll();
            foreach ($otazky as $otazka) {
                if (isset($_POST['body'.$otazka['id_otazky']]) && $_POST['body'.$otazka['id_otazky']]!=$otazka['ziskanych_bodu']){
                    $update = $db -> prepare("update otazky set ziskanych_bodu = :bod where id_otazky = :ota");
                    $update -> bindValue(":bod",$_POST['body'.$otazka['id_otazky']]);
                    $update -> bindValue(":ota",$otazka['id_otazky']);
                    $update -> execute();
                }
            }
            header("Location: student.php?student=".$_POST['login']);
            die();
            
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    
    $stav = $db->prepare("select u.login, sum(ot.ziskanych_bodu) body, sum(ot.max_bodu) max_body, COALESCE(u.prvni_prihlaseni,'nepřihlášen') prvni_prihlaseni, COALESCE(od.pocet_odpovedi,0) pocet_odpovedi from uzivatele u
                            join otazky ot on ot.id_uzivatele = u.id_uzivatele
                            left join (select id_uzivatele, count(*) pocet_odpovedi from odevzdani group by id_uzivatele) od on od.id_uzivatele = u.id_uzivatele
                            where u.login = :login");
    $stav->bindValue(":login", $_GET['student']);
    $stav->execute();
    $stav = $stav->fetch();
    
    //$db -> query("use ".$_GET['student']."_otazky");
    /*originální otázky
    $otazky = $db->query("select id_otazky, max_bodu, ziskanych_bodu, text, s_q_l, odpoved1, odpoved2, odpoved3, odpoved4, spravna_odpoved, 
                                CASE BINARY spravna_odpoved
                                    WHEN odpoved4 THEN 4
                                    WHEN odpoved3 THEN 3
                                    WHEN odpoved2 THEN 2
                                    WHEN odpoved1 THEN 1
                                    ELSE 0
                                END AS spravna_pokolikate
                            from otazky order by id_otazky");*/
    /*otáky na novou db, co odpověď tolik řádků má každá otázka
    $otazky = $db->prepare("SELECT ot.id_otazky, ov.kolikate, ot.text, ot.s_q_l, ot.ziskanych_bodu, 
            ot.max_bodu, ot.spravna_odpoved, od.odpoved, IF(od.odpoved=ot.spravna_odpoved,1,0) spravne_zodpovezena FROM otazky ot
            left join odevzdani ov on ov.id_uzivatele = ot.id_uzivatele
            left join odpovedi od on od.id_odevzdani = ov.id_odevzdani and ot.id_otazky = od.id_otazky
            where ot.id_uzivatele = :id_uzivatele
            order by ot.id_otazky, ov.kolikate");
    $otazky->bindValue(":id_uzivatele", $id_uzivatele);
    $otazky->execute();*/
    
    $otazky_sablona = "SELECT ot.id_otazky, ot.text, ot.s_q_l, ot.ziskanych_bodu, 
            ot.max_bodu, ot.spravna_odpoved, 
            %sloupce%
            %case%
            FROM otazky ot
            %join%
            where ot.id_uzivatele = :id_uzivatele
            order by ot.id_otazky";
    if ($stav['pocet_odpovedi']>0){
        $otazky_sablona = str_replace("%case%", "CASE BINARY spravna_odpoved %case% ELSE 0 END AS spravna_pokolikate", $otazky_sablona);
        for ($i=1;$i<=$stav['pocet_odpovedi'];$i++){
            $otazky_sablona = str_replace("%join%", "left join odevzdani ov".$i." on ov".$i.".id_uzivatele = ot.id_uzivatele and ov".$i.".kolikate = ".$i." left join odpovedi od".$i." on od".$i.".id_odevzdani = ov".$i.".id_odevzdani and ot.id_otazky = od".$i.".id_otazky %join%", $otazky_sablona);
            $otazky_sablona = str_replace("%case%", "WHEN od".$i.".odpoved THEN ".$i." %case%", $otazky_sablona);
            $otazky_sablona = str_replace("%sloupce%", "od".$i.".odpoved odpoved".$i.", %sloupce%", $otazky_sablona);
        }
        $otazky_sablona = str_replace("%join%", "", $otazky_sablona);
        $otazky_sablona = str_replace("%case%", "", $otazky_sablona);
        $otazky_sablona = str_replace("%sloupce%", "", $otazky_sablona);

    }
    else{
        $otazky_sablona = str_replace("%join%", "", $otazky_sablona);
        $otazky_sablona = str_replace("%case%", "0 as spravna_pokolikate", $otazky_sablona);
        $otazky_sablona = str_replace("%sloupce%", "", $otazky_sablona);
    }
    $otazky = $db->prepare($otazky_sablona);
    $otazky->bindValue(":id_uzivatele", $id_uzivatele);
    $otazky->execute();
    
    /*
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
    $stav->execute();*/
    
    $stav_odpovedi = $db->prepare("select kolikate,cas_odevzdani from odevzdani
                            where id_uzivatele = :id_uzivatele");
    $stav_odpovedi->bindValue(":id_uzivatele", $id_uzivatele);
    $stav_odpovedi->execute();
    
    $tplVars["otazky"] = $otazky->fetchAll();
    $tplVars["stav"] = $stav;
    $tplVars["stav_odpovedi"] = $stav_odpovedi->fetchAll();
    
    $tplVars["titulek"] = "Přehled studenta ".$_GET['student'];
    $tplVars["navigace"] = 2;
    $tpl->render("student.latte", $tplVars);
?>
<script type="text/javascript">
function zobrazOdpoved(id) {
   document.getElementById('odpoved'+id).style.display = "none";
   document.getElementById('zobrazenaodpoved'+id).style.display = "block";
}
function zobrazOdpovedi() {
   var elements = document.getElementsByClassName('odpoved');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "none";
    }
   var elements = document.getElementsByClassName('zobrazenaodpoved');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "block";
    }
}

function zobrazSql(id) {
   document.getElementById('sql'+id).style.display = "table-row";
   document.getElementById('zobrazSql'+id).style.display = "none";
}

function zobrazSqlka() {
   var elements = document.getElementsByClassName('sql');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "table-row";
    }
   var elements = document.getElementsByClassName('zobrazSql');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "none";
    }
}

function zobrazText(id) {
   document.getElementById('text'+id).style.display = "table-row";
   document.getElementById('zobrazText'+id).style.display = "none";
}

function zobrazTexty(){
    var elements = document.getElementsByClassName('text');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "table-row";
    }
   var elements = document.getElementsByClassName('zobrazText');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "none";
    }
}

function skryjVse() {
   var elements = document.getElementsByClassName('odpoved');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "block";
    }
   var elements = document.getElementsByClassName('zobrazenaodpoved');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "none";
    }
        
   var elements = document.getElementsByClassName('sql');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "none";
    }
   var elements = document.getElementsByClassName('zobrazSql');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "block";
    }
    
    var elements = document.getElementsByClassName('text');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "none";
    }
   var elements = document.getElementsByClassName('zobrazText');
   for (var i = 0; i < elements.length; i++) {
        elements[i].style.display = "block";
    }
}
</script>
