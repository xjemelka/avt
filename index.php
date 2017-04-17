<?php 
    //ENTER THE RELEVANT INFO BELOW
    $mysqlUserName      = "root";
    $mysqlPassword      = "";
    $mysqlHostName      = "localhost";
    $dbName             = "test";
    $fileName           = "file";
    $tables             = "export2";
    $format             = "txt";

   //or add 5th parameter(array) of specific tables:    array("mytable1","mytable2","mytable3") for multiple tables

    Export_Database($mysqlHostName, $mysqlUserName, $mysqlPassword, $dbName, $tables, $fileName, $format);

    function Export_Database($host,$user,$pass,$name,  $tables, $fileName=false, $format)
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
                    {   //first row
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
                        //every row
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
                    while($row = $result->fetch_row())  
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
                        $content .= PHP_EOL;
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
                    $columns = $mysqli->query('SHOW COLUMNS FROM '.$table);
                    $column_amount  =   $mysqli->affected_rows;
                    $column_names = array();
                    for ($j=0; $j<$column_amount; $j++){
                        $column = $columns->fetch_row();
                        array_push($column_names, $column[0]);
                    }
                    while($row = $result->fetch_row())  
                    {   //first row
                        if ($st_counter == 0 )  
                        {
                            $content .= "{".PHP_EOL;
                        }
                        //every row
                        $content .= '  "'.$table.'":{'.PHP_EOL;
                        for($j=0; $j<$fields_amount; $j++)  
                        { 
                            $row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
                            if (isset($row[$j]))
                            {
                                if ($row[$j]!=""){
                                    $content .= '    "'.$column_names[$j].'": '.$row[$j]; 
                                }   
                                elseif ($j==$fields_amount-1){
                                    $content = substr($content, 0, -3);
                                }
                            }
                            if ($j<($fields_amount-1))
                            {
                                $content.= ','.PHP_EOL;
                            }      
                            else {
                                $content.= PHP_EOL;
                            }
                        }
                        $content .="  }".PHP_EOL;
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
        }
        
        }

    }
?>