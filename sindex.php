<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 2){
    header('Location: index.php');
}
    
    $tplVars["titulek"] = "Zadání projektu";
    $tplVars["navigace"] = 1;
    $tpl->render("sindex.latte", $tplVars);
?>