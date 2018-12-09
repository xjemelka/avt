<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1){
    header('Location: index.php');
}
    
    if(!empty($_POST['login'])){
        try {
            $vstup = ($_POST['login']);
            if (strpos($vstup, ';') !== false) {
                $studenti = explode(";", $vstup);
            }
            else{
                $studenti = array();
                $studenti[0] = $vstup;
            }
            foreach ($studenti as $student){
                $student = trim($student);
                if (strlen($student)>0){
                    $existuje = $db -> prepare("select count(*) pocet from nastaveni.uzivatele where login = :log");
                    $existuje->bindValue(":log", $student);
                    $existuje->execute();
                    $existuje = $existuje->fetch();
                    if ($existuje['pocet']==0){
                        $hash = password_hash("heslo", PASSWORD_DEFAULT);
                        $uzivatel = $db->prepare('INSERT into nastaveni.uzivatele (login, heslo, email, typ) values (:log,:hes,:ema,:typ)');
                        $uzivatel -> bindValue(":log",$student);
                        $uzivatel -> bindValue(":hes",$hash);
                        $uzivatel -> bindValue(":ema",$student.'@mendelu.cz');
                        $uzivatel -> bindValue(":typ",2);
                        $uzivatel -> execute();
                    }
                }
            }
            header('Location: generuj_data.php');
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    if(!empty($_POST['smaz_id_uzivatele'])){
         try {
             $smaz = $db->query("DROP DATABASE IF EXISTS ".$_POST['smaz_login_uzivatele']);
             $smaz = $db->query("DROP DATABASE IF EXISTS ".$_POST['smaz_login_uzivatele']."_otazky");
             $smaz = $db->prepare("DELETE FROM nastaveni.uzivatele WHERE id_uzivatele = :id");
             $smaz -> bindValue(":id",$_POST['smaz_id_uzivatele']);
             $smaz -> execute();
             header('Location: studenti.php');
         } catch (Exception $ex) {
            die($ex->getMessage());
         }
    }
    
        $studenti = $db->query("SELECT u.id_uzivatele, u.login, u.email, u.zadani, COALESCE(ot.body,0) body, COALESCE(ot.max_body,0) max_body, COALESCE(od.kolikate,0) pocet_odpovedi from nastaveni.uzivatele u
                                left join (select id_uzivatele,kolikate from odevzdani where id_odevzdani in (select max(id_odevzdani) from odevzdani group by id_uzivatele)) od on od.id_uzivatele = u.id_uzivatele
                                left join (select id_uzivatele, sum(max_bodu) max_body, sum(ziskanych_bodu) body from otazky group by id_uzivatele) ot on ot.id_uzivatele = u.id_uzivatele
                                where u.typ = 2
                                order by u.login");
    

        
    $tplVars["studenti"] = $studenti->fetchAll();
    
    $tplVars["titulek"] = "Přehled studentů";
    $tplVars["navigace"] = 2;
    $tpl->render("studenti.latte", $tplVars);
?>

