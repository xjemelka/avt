<?php 
    //ENTER THE RELEVANT INFO BELOW
    $mysqlUserName      = "root";
    $mysqlPassword      = "";
    $mysqlHostName      = "localhost";
    $DbName             = "test";
    $backup_name        = "mybackup.sql";
    $tables             = "export2";
    $format             = "txt";

   //or add 5th parameter(array) of specific tables:    array("mytable1","mytable2","mytable3") for multiple tables

    Export_Database($mysqlHostName,$mysqlUserName,$mysqlPassword,$DbName,  $tables, $backup_name=false, $format);

    function Export_Database($host,$user,$pass,$name,  $tables, $backup_name=false, $format)
    {
        $tables = ["export2"];
        $mysqli = new mysqli($host,$user,$pass,$name); 
        $mysqli->select_db($name); 
        $mysqli->query("SET NAMES 'utf8'");

        $queryTables    = $mysqli->query('SHOW TABLES'); 
        while($row = $queryTables->fetch_row()) 
        { 
            $target_tables[] = $row[0]; 
        }   
        if($tables !== false) 
        { 
            $target_tables = array_intersect( $target_tables, $tables); 
        }
        foreach($target_tables as $table)
        {
            
        
        $result         =   $mysqli->query('SELECT * FROM '.$table);  
        $fields_amount  =   $result->field_count;  
        $rows_num=$mysqli->affected_rows;     
        $res            =   $mysqli->query('SHOW CREATE TABLE '.$table); 
        $TableMLine     =   $res->fetch_row();
        $content        =  "";
        switch ($format) {
            case "sql":
                for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                {
                    while($row = $result->fetch_row())  
                    { //when started (and every after 100 command cycle):
                        if ($st_counter == 0 )  
                        {
                            $content .= "INSERT INTO ".$table." (";
                            $columns = $mysqli->query('SHOW COLUMNS FROM '.$table);
                            $column_amount  =   $mysqli->affected_rows;
                            for ($j=0; $j<$column_amount; $j++){
                                $column = $columns->fetch_row();
                                $content .= $column[0];
                                if ($j+1<$column_amount){
                                    $content .= ", ";
                                }
                            }
                            $content .= ") VALUES";
                        }
                        $content .= PHP_EOL."(";
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
                        //every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler
                        if (!$st_counter+1==$rows_num) 
                        {   
                            $content .= ",";
                        } 
                        $st_counter=$st_counter+1;
                    }
                } $content .="\n\n\n";
                //$backup_name = $backup_name ? $backup_name : $name."___(".date('H-i-s')."_".date('d-m-Y').")__rand".rand(1,11111111).".sql";
                $backup_name = $backup_name ? $backup_name : $name.".sql";
                header('Content-Type: application/octet-stream');   
                header("Content-Transfer-Encoding: Binary"); 
                header("Content-disposition: attachment; filename=\"".$backup_name."\"");  
                echo $content; exit;
                break;
            case "txt":
                for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
                {
                    while($row = $result->fetch_row())  
                    { 
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
                        $content .= PHP_EOL;
                        $st_counter=$st_counter+1;
                    }
                } $content .="\n\n\n";
                //$backup_name = $backup_name ? $backup_name : $name."___(".date('H-i-s')."_".date('d-m-Y').")__rand".rand(1,11111111).".sql";
                $backup_name = $backup_name ? $backup_name : $name.".txt";
                header('Content-Type: application/octet-stream');   
                header("Content-Transfer-Encoding: UTF-8"); 
                header("Content-disposition: attachment; filename=\"".$backup_name."\"");  
                echo $content; exit;
                break;
        }
        
        }

    }
?>