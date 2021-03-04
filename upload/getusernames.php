<?php 
    define("IN_MYBB", 1);
    require("global.php");
 //$db->query Function von mybb, fuehrt ein SQL Query in der Datenbank aus
 //evt mit where usergruppen/user ausschließen wenn sie nicht vorgeschlagen werden sollen! (gastaccount... Teamaccount) z.b.  WHERE uid != 5 AND uid != 3 AND uid !=48 
 $get_users = $db->query("SELECT username From mybb_users  WHERE uid != 5 AND uid != 3 AND uid !=48  ORDER by username");
    $user= array();
	while ($row = mysqli_fetch_assoc($get_users))
		{
            $user[] = $row;
        } 
        echo json_encode($user,JSON_UNESCAPED_UNICODE);
