<?php 

require 'start.php';


/* @var $db PDO */
/* @tpl Latte\Engine */

$stmt = $db->query("SELECT table_name
  FROM information_schema.tables
 WHERE table_schema='public'
   AND table_type='BASE TABLE'");
    if (isset($_POST['submit'])) {
        if (!empty($_POST['table']) && !empty($_POST['format'])){
            
            $format = $_POST['format'];
            $table = $_POST['table'];
            $fileName = 'export';
            $result = $db->prepare("SELECT * FROM ".$table);
            $result -> execute();
            $fields_amount = $result->columnCount();
            $rows_num = $result->rowCount();
            $content        =  "";
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
                                                          where table_name = :t');
                                $columns -> bindValue(":t",$table);
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
                    header('Content-Type: application/octet-stream');   
                    header("Content-Transfer-Encoding: Binary"); 
                    header("Content-disposition: attachment; filename=\"".$fileName."\"");  
                    echo $content; exit;
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
                    header('Content-Type: application/octet-stream');   
                    header("Content-Transfer-Encoding: UTF-8"); 
                    header("Content-disposition: attachment; filename=\"".$fileName."\"");  
                    echo $content; exit;
                    break;
                case "json":
                    for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                    {
                        $columns = $db->prepare('SELECT column_name
                                                        FROM information_schema.columns
                                                          where table_name = :t');
                        $columns -> bindValue(":t",$table);
                        $columns -> execute();
                        $column_amount  =   $columns->rowCount();
                        $column_names = array();
                        for ($j=0; $j<$column_amount; $j++){
                            $column = $columns->fetch(PDO::FETCH_NUM);
                            array_push($column_names, $column[0]);
                        }
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
                    header('Content-Type: application/octet-stream');   
                    header("Content-Transfer-Encoding: Binary"); 
                    header("Content-disposition: attachment; filename=\"".$fileName."\"");  
                    echo $content; exit;
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
                    header('Content-Type: application/octet-stream');   
                    header("Content-Transfer-Encoding: UTF-8"); 
                    header("Content-disposition: attachment; filename=\"".$fileName."\"");  
                    echo $content; exit;
                    break;
                case "html":
                    $content .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html>';
                    $content .= '<head><meta http-equiv="content-type" content="text/html; charset=windows-1250"><title>'.$table.'</title>';
                    $content .= '</head><body><table>';
                    $columns = $db->prepare('SELECT column_name
                                                        FROM information_schema.columns
                                                          where table_name = :t');
                    $columns -> bindValue(":t",$table);
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
                    header('Content-Type: application/octet-stream'); 
                    header("Content-Transfer-Encoding: UTF-8"); 
                    header("Content-disposition: attachment; filename=\"".$fileName."\"");  
                    echo $content; exit;
                    break;
            }
        }
    }
    $format = ['sql','txt','json','csv','html'];
    $tplVars["titulek"] = "Export";
    $tplVars["tabulky"] = $stmt->fetchAll();
    $tplVars["formaty"] = $format;
    $tpl->render("index.latte", $tplVars);
?>