<?php
require __DIR__ . "/../config.php";
// ini_set("display_errors", 1);
session_start();

function base64_encodesafe($string) {
    $data = base64_encode($string);
    $data = str_replace(array('+','/','='),array('-','_',''),$data);
    return $data;
}

function text2ascii($text) {
  return array_map('ord', str_split($text));
}

function cipher($plaintext, $key) {
  $key = text2ascii($key);
  $plaintext = text2ascii($plaintext);

  $keysize = count($key);
  $input_size = count($plaintext);

  $cipher = "";

  for ($i = 0; $i < $input_size; $i++)
    $cipher .= chr($plaintext[$i] ^ $key[$i % $keysize]);

  return $cipher;
}

if(empty($_SESSION["id"])) exit(header("Location: /"));

require __DIR__ . "/../../incl/lib/connection.php";

$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID=:id");
$query->execute([":id" => intval($_GET["id"])]);
if($query->rowCount() == 0 || $query->fetchColumn() <= 0) exit(header("Location: /"));

if(!empty($_POST["action"])) {
  	$action = strtolower($_POST["action"]);
  
  	if($action != "approve" && empty($_POST["reason"])) exit("Forgot reason :(");
  
  	$query = $db->prepare("SELECT * FROM posts WHERE id=:id");
  	$query->execute([":id" => intval($_POST["id"])]);
  
  	if($query->rowCount() == 0) {
    	exit("Post not found");
    }
  
  	$post = $query->fetch(PDO::FETCH_ASSOC);
  
  	$query = $db->prepare("DELETE FROM posts WHERE id=:id");
  	$query->execute([":id" => intval($_POST["id"])]);
  
  
  	$message = "Your thumbnail has been ";
  	if($action == "approve") {
      	rename(__DIR__ . "/../../pending/" . $post["levelid"] . ".png", __DIR__ . "/../../thumbs/" . $post["levelid"] . ".png");
      	echo "<h1>Approved :D</h1>";
      	$message .= "approved! We thank you for your help!";
    } else {
    	unlink(__DIR__ . "/../../pending/" . $post["levelid"] . ".png");
		$message .= "denied. Reason: " . $_POST["reason"];
      	echo "<h1>Post denied!</h1>";
    }

	$query = $db->prepare("SELECT userName, userID FROM users WHERE extID = :id");
    $query->execute([":id" => $accountID]);
	
	if($query->rowCount() > 0) {
		$res = $query->fetch();
		$subject = base64_encodesafe("Level submission (Level " . $post["levelid"] . ")");
		$body = base64_encodesafe(cipher($message, 14251));
		$query = $db->prepare("INSERT INTO messages (subject, body, accID, userID, userName, toAccountID, secret, timestamp) VALUES (:subject, :body, :accID, :userID, :userName, :toAccountID, :secret, :uploadDate)");
		$query->execute([':subject' => $subject, ':body' => $body, ':accID' => $accountID, ':userID' => $res["userID"], ':userName' => $res["userName"], ':toAccountID' => $post["authorid"], ':secret' => "Wmfd2893gb7", ':uploadDate' => time()]);
	}
}



$query = $db->prepare("SELECT * FROM posts WHERE 1 ORDER BY id DESC");
$query->execute();

if($query->rowCount() == 0) exit("<h1>No requests to check</h1><img src='https://media.tenor.com/g16jQZqbvWoAAAAM/yippee-happy.gif'>");

$reqs = $query->fetchAll();

foreach($reqs as $req) {
  	$reqID = $req["id"];
  	$author = $req["author"];
    $authorID = $req["authorid"];
  	$levelID = $req["levelid"];
  	echo <<<EOF
    	<form method="POST">
        	<a href="/pending/$levelID.png" target="_blank">$levelID sent by $author ($authorID)</a>
            <input type="hidden" name="id" value="$reqID">
            <input type="submit" name="action" value="Approve">
            <input type="submit" name="action" value="Deny">
            <input type="text" name="reason" placeholder="Reason for denying">
        </form>
    EOF;
}