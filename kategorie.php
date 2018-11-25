<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1 or empty($_SESSION['db']) or empty($_SESSION['id_db'])){
    header('Location: index.php');
}
    //$db->query("USE ".$_SESSION['db']."_otazky");
    
    if(!empty($_POST['kategorie'])){
        try {
            $vstup = ($_POST['kategorie']);
            if (strpos($vstup, ';') !== false) {
                $kategorie = explode(";", $vstup);
            }
            else{
                $kategorie = array();
                $kategorie[0] = $vstup;
            }
            foreach ($kategorie as $kat){
                $kat = trim($kat);
                if (strlen($kat)>0){
                    $existuje = $db -> prepare("select count(*) pocet from kategorie where id_zadani = :zad and nazev = :kat");
                    $existuje->bindValue(":zad", $_SESSION['id_db']);
                    $existuje->bindValue(":kat", $kat);
                    $existuje->execute();
                    $existuje = $existuje->fetch();
                    if ($existuje['pocet']==0){
                        $kategorie = $db->prepare('INSERT into kategorie (id_zadani, nazev, body, otazek) values (:zad,:kat,1,1)');
                        $kategorie -> bindValue(":zad", $_SESSION['id_db']);
                        $kategorie -> bindValue(":kat",$kat);
                        $kategorie -> execute();
                    }
                }
            }
            header('Location: '.$_SERVER['PHP_SELF']);
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    if(!empty($_POST['smaz_id_kategorie'])){
         try {
             $smaz = $db->prepare("DELETE FROM dotazy WHERE id_kategorie = :id");
             $smaz -> bindValue(":id",$_POST['smaz_id_kategorie']);
             $smaz -> execute();
             $smaz = $db->prepare("DELETE FROM kategorie WHERE id_kategorie = :id");
             $smaz -> bindValue(":id",$_POST['smaz_id_kategorie']);
             $smaz -> execute();
             header('Location: '.$_SERVER['PHP_SELF']);
         } catch (Exception $ex) {
            die($ex->getMessage());
         }
    }
        $kategorie = $db->prepare("SELECT k.id_kategorie, k.nazev, k.otazek, k.body, count(d.id_dotazy) pocet FROM `kategorie` k
                                left join dotazy d on k.id_kategorie = d.id_kategorie
                                where k.id_zadani = :zad
                                group by k.id_kategorie, k.nazev, k.otazek, k.body ");
        $kategorie -> bindValue(":zad", $_SESSION['id_db']);
        $kategorie -> execute();
    
    $tplVars["zadani"] = $_SESSION['db'];
    $tplVars["kategorie"] = $kategorie->fetchAll();
    
    $tplVars["titulek"] = "Nastavení kategorií zadání";
    $tplVars["navigace"] = 1;
    $tpl->render("kategorie.latte", $tplVars);
?>

