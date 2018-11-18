<?php 

require 'start.php';
require 'overeni.php';
require 'tfpdf.php';

/* @var $db PDO */
/* @tpl Latte\Engine */
    if ($_SESSION['user']['typ'] == 1 && isset($_POST['login'])){
        $login = $_POST['login'];
        $zadani = $db -> prepare("select zadani from nastaveni.uzivatele where login = :log");
        $zadani -> bindvalue(":log",$login);
        $zadani -> execute();
        $zadani = $zadani -> fetch();
        $zadani = $zadani['zadani'];
        if (isset($_POST['sql'])){
            $generuj_sql = 1;
        }
    }
    else{
    $login = $_SESSION["user"]["login"];
    $zadani = $_SESSION["user"]["zadani"];
    }
    $slozka = 'files/'.$login;
    if (!file_exists($slozka)){
        //pokud složka neexistuje pokračuj a založ, pokud existuje, pravděpodobně na to uživatel klikl dvakrát rychle za sebou
        mkdir($slozka, 0777);
        $databaze = $login;
        $tabulky = $db->prepare("SELECT exp.tabulka, form.nazev as format, zpu.nazev as zpusob
        FROM nastaveni.export exp
        INNER JOIN nastaveni.zadani zad
        ON exp.id_zadani = zad.id_zadani
        INNER JOIN nastaveni.formaty form
        ON exp.id_formaty = form.id_formaty
        INNER JOIN nastaveni.zpusoby zpu
        ON exp.id_zpusoby = zpu.id_zpusoby
        WHERE zad.zadani = :db");
        
        //tady je potřeba nabindovat databázi zadání! ne studentovu
        $tabulky->bindvalue(":db", $zadani);
        $tabulky->execute();
        $tabulky = $tabulky->fetchAll();
        
        foreach ($tabulky as $tabulka) {        
            if (isset($generuj_sql)){
                $format = "sql";
            }
            else{
                $format = $tabulka['format'];
            }
            $table = $tabulka['tabulka'];
            $fileName = $table;
            $result = $db->prepare("SELECT * FROM ".$databaze.".".$table);
            $result -> execute();
            $fields_amount = $result->columnCount();
            $rows_num = $result->rowCount();
            $content = "";
            if ($format=="nahodny"){
                $format = $db->query('SELECT nazev from nastaveni.formaty where nazev<>"nahodny" order by rand() limit 1');
                    $format = $format->fetch();
                    $format = $format['nazev'];
            }
            switch ($format) {
                case "sql":
                    for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                    {
                        while($row = $result->fetch(PDO::FETCH_NUM))
                        {   //first row
                            if ($st_counter == 0 )  
                            {
                                $content .= "INSERT INTO ".$table." (";
                                $columns = $db->prepare('SELECT column_name
                                                        FROM information_schema.columns
                                                          where table_name = :t
                                                          and table_schema = :db');
                                $columns -> bindValue(":t",$table);
                                $columns -> bindValue(":db",$databaze);
                                $columns -> execute();
                                $column_amount  =   $columns->rowCount();
                                for ($j=0; $j<$column_amount; $j++){
                                    $column = $columns->fetch(PDO::FETCH_NUM);
                                    $content .= $column[0];
                                    if ($j+1<$column_amount){
                                        $content .= ", ";
                                    }
                                }
                                $content .= ") VALUES";
                            }
                            //every row
                            $content .= "\r\n"."(";
                            for($j=0; $j<$fields_amount; $j++)  
                            { 
                                $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
                                if (isset($row[$j]))
                                {
                                    $content .= '"'.$row[$j].'"' ; 
                                }
                                else 
                                {   
                                    $content .= '""';
                                }     
                                if ($j<($fields_amount-1))
                                {
                                        $content.= ',';
                                }      
                            }
                            $content .=")";
                            //every row but last
                            if (!$st_counter+1==$rows_num) 
                            {   
                                $content .= ",";
                            } 
                            $st_counter=$st_counter+1;
                        }
                    }
                    $fileName = $fileName.".sql";
                    file_put_contents($slozka.'/'.$fileName, $content);
                    break;
                case "txt":
                    for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                    {
                        while($row = $result->fetch(PDO::FETCH_NUM))  
                        { 
                            //every row
                            for($j=0; $j<$fields_amount; $j++)  
                            { 
                                $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
                                if (isset($row[$j]))
                                {
                                    $content .= ''.$row[$j].'' ; 
                                }
                                else 
                                {   
                                    $content .= '""';
                                }     
                                if ($j<($fields_amount-1))
                                {
                                        $content.= ', ';
                                }      
                            }
                            //$content .= PHP_EOL;
                            $content .= "\r\n";
                            $st_counter=$st_counter+1;
                        }
                    }
                    $fileName = $fileName.".txt";
                    file_put_contents($slozka.'/'.$fileName, $content);
                    break;
                case "json":
                    $columns = $db->prepare('SELECT column_name
                                                        FROM information_schema.columns
                                                          where table_name = :t
                                                          and table_schema = :db');
                                $columns -> bindValue(":t",$table);
                                $columns -> bindValue(":db",$databaze);
                                $columns -> execute();
                    $column_amount  =   $columns->rowCount();
                    $column_names = array();
                    for ($j=0; $j<$column_amount; $j++){
                        $column = $columns->fetch(PDO::FETCH_NUM);
                        array_push($column_names, $column[0]);
                    }
                    for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                    {
                        
                        while($row = $result->fetch(PDO::FETCH_NUM))
                        {   //first row
                            if ($st_counter == 0 )  
                            {
                                $content .= "{"."\r\n";
                            }
                            //every row
                            $content .= '  "'.$table.'":{'."\r\n";
                            for($j=0; $j<$fields_amount; $j++)  
                            { 
                                $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
                                if (isset($row[$j]))
                                {
                                    if ($row[$j]!=""){
                                        $content .= '    "'.$column_names[$j].'": '.$row[$j]; 
                                    }   
                                    else{
                                        $content = substr($content, 0, -3);
                                    }
                                }
                                if ($j<($fields_amount-1))
                                {
                                    $content.= ','."\r\n";
                                }      
                                else {
                                    $content.= "\r\n";
                                }
                            }
                            $content .="  }"."\r\n";
                            //last row
                            if ($st_counter+1==$rows_num) 
                            {   
                                $content .= "}";
                            } 
                            $st_counter=$st_counter+1;
                        }
                    }
                    $fileName = $fileName.".json";
                    file_put_contents($slozka.'/'.$fileName, $content);
                    break;
                case "xml":
                    $primary_keys = $db->prepare('SELECT column_name
                                                        FROM information_schema.columns
                                                          where table_name = :t
                                                          and table_schema = :db
                                                          and column_key = "PRI"');
                                $primary_keys -> bindValue(":t",$table);
                                $primary_keys -> bindValue(":db",$databaze);
                                $primary_keys -> execute();
                    $primary_amount = $primary_keys->rowCount();
                    if ($primary_amount=1){
                        $primary_key = $primary_keys->fetch();
                        $primary_key = $primary_key[0];
                    }
                    else{
                        $primary_key = "";
                    }
                                
                    $columns = $db->prepare('SELECT column_name
                                                        FROM information_schema.columns
                                                          where table_name = :t
                                                          and table_schema = :db');
                    $columns -> bindValue(":t",$table);
                    $columns -> bindValue(":db",$databaze);
                    $columns -> execute();
                    $column_amount  =   $columns->rowCount();
                    $column_names = array();
                    for ($j=0; $j<$column_amount; $j++){
                        $column = $columns->fetch(PDO::FETCH_NUM);
                        array_push($column_names, $column[0]);
                    }
                    for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                    {
                        
                        while($row = $result->fetch(PDO::FETCH_NUM))
                        {   
                            if ($primary_key!=""){
                                for($j=0; $j<$fields_amount; $j++)  
                                { 
                                    if ($column_names[$j]==$primary_key){
                                        $id = " ".$primary_key."='".$row[$j]."'";
                                        //break;
                                    }
                                }
                            }
                            else{
                                $id = "";
                            }
                            $content .= '  <'.$table.$id.'>'."\r\n";
                            for($j=0; $j<$fields_amount; $j++)  
                            { 
                                $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
                                if (isset($row[$j]))
                                {
                                    if ($row[$j]!="" && $column_names[$j]!=$primary_key){
                                        $content .= '    <'.$column_names[$j].'>'.$row[$j].'</'.$column_names[$j].'>'; 
                                        $content.= "\r\n";
                                    } 
                                }
                            }
                            $content .= '  </'.$table.'>'."\r\n";
                        }
                    }
                    $fileName = $fileName.".xml";
                    file_put_contents($slozka.'/'.$fileName, $content);
                    break;    
                case "csv":
                    for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                    {
                        while($row = $result->fetch(PDO::FETCH_NUM))  
                        { 
                            //every row
                            for($j=0; $j<$fields_amount; $j++)  
                            { 
                                //$row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 

                               // if (strpos($row[$j], ',') !== false) {
                                    $row[$j] = str_replace('"', '""', $row[$j]);
                                    $row[$j] = '"'.$row[$j].'"';
                                //}
                                if (isset($row[$j]))
                                {
                                    $content .= $row[$j] ; 
                                }
                                else 
                                {   
                                    $content .= '""';
                                }     
                                if ($j<($fields_amount-1))
                                {
                                        $content.= ',';
                                }      
                            }
                            //$content .= PHP_EOL;
                            $content .= "\r\n";
                            $st_counter=$st_counter+1;
                        }
                    }
                    $fileName = $fileName.".csv";
                    file_put_contents($slozka.'/'.$fileName, $content); 
                    break;
                case "html":
                    $content .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html>';
                    $content .= '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8"><title>'.$table.'</title>';
                    $content .= '</head><body><table>';
                    $columns = $db->prepare('SELECT column_name
                                                        FROM information_schema.columns
                                                          where table_name = :t
                                                          and table_schema = :db');
                                $columns -> bindValue(":t",$table);
                                $columns -> bindValue(":db",$databaze);
                                $columns -> execute();
                    $column_amount  =   $columns->rowCount();
                    $column_names = array();
                        
                    $content .= '<tr>';
                        for ($j=0; $j<$column_amount; $j++){
                            $column = $columns->fetch(PDO::FETCH_NUM);
                            $content .= '<th>'.$column[0].'</th>';
                        }
                    $content .= '</tr>';
                    
                    for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                    {
                        
                        while($row = $result->fetch(PDO::FETCH_NUM))  
                        { 
                            //every row
                            $content .= '<tr>';
                            for($j=0; $j<$fields_amount; $j++)  
                            { 
                                if (isset($row[$j]))
                                {
                                    $content .= "<td>".$row[$j]."</td>" ; 
                                }
                                else 
                                {   
                                    $content .= '<td></td>';
                                }      
                            }
                            //$content .= PHP_EOL;
                            $content .= '</tr>';
                            $st_counter=$st_counter+1;
                        }
                    }
                    $content .= '</table></body></html>';
                    $fileName = $fileName.".html";
                    file_put_contents($slozka.'/'.$fileName, $content);
                    break;
            }
        }
        //vygeneruj pdf se zadáním
        $pdf = new tFPDF();
        $pdf->AddPage();
        $pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);

        $pdf->SetFont('DejaVu','',16);
        $pdf->Cell(0,0,'2. projekt do DBS');
        $pdf->Ln(15);

        $pdf->SetFont('DejaVu','',14);
        $pdf->Cell(0,0,'Logický model');
        $pdf->Ln(10);
        $obrazek = 'files/'.$zadani.'.png';
        if (file_exists($obrazek)){
            $pdf->Image($obrazek,null,null,0,($pdf-> w)-40);
            $pdf->Ln(10);
        }

        $pdf->Cell(0,0,'Zadání');
        $pdf->Ln(10);
        $text = $db->prepare("select text from nastaveni.zadani where zadani= :zad");
        $text ->bindValue(":zad", $zadani);
        $text ->execute();
        $text = $text->fetch();
        $pdf->SetFont('DejaVu','',10);
        $pdf->MultiCell(0,5,$text['text'],0,1);
        $pdf->Ln(10);

        $pdf->SetFont('DejaVu','',14);
        $pdf->Cell(0,0,'Příklady k řešení');
        $pdf->Ln(10);
        $pdf->SetFont('DejaVu','',10);
        $db -> query("use ".$databaze."_otazky");  
        $otazky = $db->query("select id_otazky,text,max_bodu from otazky order by id_otazky");
        $otazky = $otazky->fetchAll();
        foreach($otazky as $otazka){
            $pdf->MultiCell(0,5,$otazka['id_otazky'].". ".$otazka['text']." [".$otazka['max_bodu']."b]",0,1);
            $pdf->Ln(5);
        }
        $pdf->Output($slozka.'/zadani.pdf');
        
        //zazipuj všechny soubory ve složce a stáhni
        $zip = new ZipArchive;
        $download = $slozka.'.zip';
        if(file_exists($download)){
            unlink($download);
        }
        $zip->open($download, ZipArchive::CREATE);
        foreach (glob($slozka."/*.*") as $file) { /* Add appropriate path to read content of zip */
            $new_filename = substr($file,strrpos($file,'/') + 1);
            $zip->addFile($file,$new_filename);
        }
        $zip->close();
        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename = $download");
        header('Content-Length: ' . filesize($download));
        header("Location: $download");
        
        if (file_exists($download)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($download).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($download));
            readfile($download);
            
        }
        //smaž soubory a složku, aby šlo generovat znovu - zip smazat nemůžu, nestáhl by se, ale ten se smaže (pokud existuje) vždy před vytvořením zvlášť
        array_map('unlink', glob($slozka."/*.*"));
        rmdir($slozka);
        exit;
    }
    header('Location: index.php');
?>