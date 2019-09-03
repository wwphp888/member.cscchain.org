<?php
set_time_limit(0);
ini_set('max_execution_time', 300);
//echo phpinfo();die();
$myfile = fopen("testfile.txt", "w+");
$i = 1;
while($i<40){
    $a =date("Y-m-d H:i:s").",";
    fwrite($myfile, $a);
    sleep(5);
}
fclose($myfile);die();
?>