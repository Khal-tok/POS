<?php
$new_password = '123'; 
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
echo "";
echo $hashed_password;
?>