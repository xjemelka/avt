<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1){
    header('Location: index.php');
}
    
    $studenti = $db->query("SELECT id_uzivatele, login, email, zadani, body, max_body,
                                CASE COALESCE(odpoved4, odpoved3, odpoved2, odpoved1)
                                    WHEN odpoved4 THEN 4
                                    WHEN odpoved3 THEN 3
                                    WHEN odpoved2 THEN 2
                                    WHEN odpoved1 THEN 1
                                    ELSE 0
                                END AS pocet_odpovedi 
                            from nastaveni.uzivatele where typ = 2");
    
    if(!empty($_POST['login'])){
        try {
            $hash = sha1('heslo');
            $zadani = $db->query('SELECT aktualni_zadani from nastaveni.zadani where id_zadani=1');
            $zadani = $zadani->fetch();
            $uzivatel = $db->prepare('INSERT into nastaveni.uzivatele (login, heslo, email, typ, zadani) values (:log,:hes,:ema,:typ,:zad)');
            $uzivatel -> bindValue(":log",$_POST['login']);
            $uzivatel -> bindValue(":hes",$hash);
            $uzivatel -> bindValue(":ema",$_POST['login'].'@mendelu.cz');
            $uzivatel -> bindValue(":typ",2);
            $uzivatel -> bindValue(":zad",$zadani['aktualni_zadani']);
            $uzivatel -> execute();
            $_SESSION['novy_student'] = $_POST['login'];
            $_SESSION['zadani_novy_student'] = $zadani['aktualni_zadani'];
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
            die($e->getMessage());
         }
    }

    $tplVars["studenti"] = $studenti->fetchAll();
    
    $tplVars["titulek"] = "Přehled studentů";
    $tplVars["navigace"] = 2;
    $tpl->render("studenti.latte", $tplVars);
?>

