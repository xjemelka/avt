<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1 or empty($_SESSION['db'])){
    header('Location: index.php');
}
    $db->query("USE ".$_SESSION['db']."_otazky");
    
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
                    $existuje = $db -> prepare("select count(*) pocet from kategorie where nazev = :kat");
                    $existuje->bindValue(":kat", $kat);
                    $existuje->execute();
                    $existuje = $existuje->fetch();
                    if ($existuje['pocet']==0){
                        $kategorie = $db->prepare('INSERT into kategorie (nazev, body, otazek) values (:kat,1,1)');
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
    if(!empty($_POST['smaz_kategorie_id'])){
         try {
             $smaz = $db->prepare("DELETE FROM dotazy WHERE kategorie = :id");
             $smaz -> bindValue(":id",$_POST['smaz_kategorie_id']);
             $smaz -> execute();
             $smaz = $db->prepare("DELETE FROM kategorie WHERE kategorie_id = :id");
             $smaz -> bindValue(":id",$_POST['smaz_kategorie_id']);
             $smaz -> execute();
             header('Location: '.$_SERVER['PHP_SELF']);
         } catch (Exception $ex) {
            die($ex->getMessage());
         }
    }
        $kategorie = $db->query("SELECT k.kategorie_id, k.nazev, k.otazek, k.body, count(d.dotazy_id) pocet FROM `kategorie` k
                                left join dotazy d on k.kategorie_id = d.kategorie
                                group by k.kategorie_id, k.nazev, k.otazek, k.body ");
    
    $tplVars["zadani"] = $_SESSION['db'];
    $tplVars["kategorie"] = $kategorie->fetchAll();
    
    $tplVars["titulek"] = "Nastavení kategorií zadání";
    $tplVars["navigace"] = 1;
    $tpl->render("kategorie.latte", $tplVars);
?>

