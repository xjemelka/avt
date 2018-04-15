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
        $existuje = $db->prepare("select count(*) pocet from nastaveni.uzivatele where login = :login");
        $existuje->bindValue(":login", $_GET['student']);
        $existuje->execute();
        $existuje = $existuje -> fetch();
        if ($existuje['pocet']==0){
            header('Location: index.php');
        }
    }
    
    if(!empty($_POST)){
        try {
            $db -> query("use ".$_POST['login']."_otazky");
            $otazky = $db->query("select id_otazky, ziskanych_bodu from otazky order by id_otazky");
            $otazky = $otazky->fetchAll();
            $zmena = 0;
            foreach ($otazky as $otazka) {
                if (isset($_POST['body'.$otazka['id_otazky']]) && $_POST['body'.$otazka['id_otazky']]!=$otazka['ziskanych_bodu']){
                    $zmena+= $_POST['body'.$otazka['id_otazky']]-$otazka['ziskanych_bodu'];
                    $update = $db -> prepare("update otazky set ziskanych_bodu = :bod where id_otazky = :ota");
                    $update -> bindValue(":bod",$_POST['body'.$otazka['id_otazky']]);
                    $update -> bindValue(":ota",$otazka['id_otazky']);
                    $update -> execute();
                }
            }
            if ($zmena!=0){
                $update = $db -> prepare("update nastaveni.uzivatele set body = body+:bod where login=:log");
                $update -> bindValue(":bod",$zmena);
                $update -> bindValue(":log",$_POST['login']);
                $update -> execute();
            }
            header("Location: student.php?student=".$_POST['login']);
            die();
            
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    
    $db -> query("use ".$_GET['student']."_otazky");
    $otazky = $db->query("select id_otazky, max_bodu, ziskanych_bodu, text, s_q_l, odpoved1, odpoved2, odpoved3, odpoved4, spravna_odpoved, 
                                CASE BINARY spravna_odpoved
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
