<?php 

require 'start.php';
require 'overeni.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

//ověřuje jestli je učitel - později přesunout takové podmínky do souborů jako je overeni.php pro každé oprávnění zvlášť
if ($_SESSION["user"]["typ"] != 1 or empty($_SESSION['db'])){
    header('Location: index.php');
}
$obrazek = "files/" . $_SESSION['db'] . ".png";
    

    if(isset($_POST["image"])) {
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo(basename($_FILES["fileToUpload"]["name"]),PATHINFO_EXTENSION));
        $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
        $chyba = "";
        if($check == false) {
            $chyba = "Soubor není obrázek";
            $uploadOk = 0;
        }
        // Check file size
        if ($_FILES["fileToUpload"]["size"] > 5000000 && $uploadOk == 1) {
            $chyba = "Obrázek je příliš veliký";
            $uploadOk = 0;
        }
        // Allow certain file formats
        if($imageFileType != "png" && $uploadOk == 1) {
            $chyba = "Lze nahrát pouze soubor s příponou .png";
            $uploadOk = 0;
        }
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            $tplVars['hlaska'] = "Obrázek nebyl nahrán z důvodu: ".$chyba;
        // if everything is ok, try to upload file
        } else {
            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $obrazek)) {
                $tplVars['hlaska'] = "Obrázek byl nahrán";
            } else {
                $tplVars['hlaska'] = "Nastala chyba při nahrávání obrázku";
            }
        }
    }
    
    if(!empty($_POST["nastaveni"])){
        try {
            if (isset($_POST['text'])){
                $update = $db->prepare("update nastaveni.zadani set text = :text where zadani = :zad");
                $update -> bindValue(":text", $_POST['text']);
                $update -> bindValue(":zad", $_SESSION['db']);
                 $update -> execute();
            }
            if (isset($_POST['aktualni_zadani'])){
                $db -> query("update nastaveni.zadani set aktualni_zadani = 0");
                $update = $db->prepare("update nastaveni.zadani set aktualni_zadani = 1 where zadani = :zad");
                $update -> bindValue(":zad", $_SESSION['db']);
                $update -> execute();
            }
            if (isset($_POST['strhavani'])){
                $update = $db->prepare("update nastaveni.zadani set strhavani = :strh where zadani = :zad");
                $update -> bindValue(":strh", $_POST['strhavani']);
                $update -> bindValue(":zad", $_SESSION['db']);
                $update -> execute();
            }
            if (isset($_POST['deadline'])){
                $update = $db->prepare("update nastaveni.zadani set deadline_odevzdani = :deadline where zadani = :zad");
                $update -> bindValue(":deadline", $_POST['deadline']);
                $update -> bindValue(":zad", $_SESSION['db']);
                $update -> execute();
            }
            $tplVars['hlaska'] = "Nastavení úspěšně aktualizováno";
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    if (file_exists($obrazek)){
        $tplVars["obrazek"]=$obrazek;
    }
    else{
        $tplVars["obrazek"]="";
    }
    
    $info = $db->prepare("select aktualni_zadani, text, strhavani, deadline_odevzdani FROM nastaveni.zadani where zadani = :zad");
    $info -> bindValue(":zad", $_SESSION['db']);
    $info->execute();

    $tplVars["info"] = $info->fetch();
    $tplVars["db"] = $_SESSION['db'];
    
    $tplVars["titulek"] = "Nastavení ".$_SESSION['db'];
    $tplVars["navigace"] = 1;
    $tpl->render("definice.latte", $tplVars);
?>

