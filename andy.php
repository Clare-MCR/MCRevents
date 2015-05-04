<?php
include('config.php'); 
$dbh->query("UPDATE access SET e_view=1, e_book=1,e_adm=0, mcr_member=1 WHERE e_view IS NULL");

global $dbh;
$result = $dbh->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    print_r($row);
}
print("<br />");
print("<br />");
$result = $dbh->query("DESCRIBE mcrevents_booking ");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    print_r($row);
}
print("<br />");
print("<br />");
print("\n");
print("\n");
$result = $dbh->query("DESCRIBE access ");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    print_r($row);
}
print("<br />");
print("<br />");

print("\n");

print("\n");
$result = $dbh->query("SELECT * FROM mcrevents_booking WHERE 1 ");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    //print_r($row);
}

print("\n");
print("<br />");
print("<br />");
print("\n");
$result = $dbh->query("SELECT * FROM access WHERE 1 ");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    print_r($row);
}
?>
