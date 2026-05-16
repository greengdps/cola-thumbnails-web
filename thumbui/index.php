<?php
require __DIR__ . "/config.php";
error_reporting(0);
ini_set('display_errors', 0);
session_start();
function validateCaptcha() {
  	require __DIR__ . "/config.php";
  	$key = $_POST["cf-turnstile-response"];
  	if(empty($key)) return false;
    $post = [
      'secret' => $secretKey,
      'response' => $key,
      'remoteip' => $_SERVER["HTTP_CF_CONNECTING_IP"]
    ];
    
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

    // execute!
    $json = json_decode(curl_exec($ch), true);

    // close the connection, release resources used
    curl_close($ch);
  
  	return $json["success"];
}
if($_SERVER["REQUEST_URI"] == "/thumbnails.json") {
    header('Content-Type: application/json');

    $array = ["level_ids" => []]; // Initialize array with "level_ids" key
    $namefill = scandir("thumbs"); // Scan the "thumbs" directory

    foreach ($namefill as $file_name) {
        // Check if the file has a ".png" extension
        if (pathinfo($file_name, PATHINFO_EXTENSION) === 'png') {
            $array["level_ids"][] = pathinfo($file_name, PATHINFO_FILENAME); // Get filename without extension
        }
    }

    echo json_encode($array, JSON_PRETTY_PRINT); // Output JSON with formatting
    exit;
}
switch($_POST["action"]) {
  case "logout":
    $_SESSION["username"] = "";
    $_SESSION["id"] = "";
    break;
  case "login":   
    if(empty($_POST["username"]) || empty($_POST["password"])) {
    	echo "<center><h1>Credentials needed.</h1></center>";
      	break;
    }
    if(!validateCaptcha()) {
      	echo "<center><h1>Failed Captcha</h1></center>";
    	break;
    }
    $post = [
      'userName' => $_POST["username"],
      'password' => $_POST["password"]
    ];
    
    $ch = curl_init("$gdps/accounts/loginGJAccount.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

    // execute!
    $response = curl_exec($ch);

    // close the connection, release resources used
    curl_close($ch);

    // do anything you want with your response
    $code = intval(explode(",", $response)[0]);
    if($code > 0) {
    	$_SESSION["username"] = htmlspecialchars($_POST["username"]);
      	$_SESSION["id"] = $code;
    } else {
      	http_response_code(403);
    	echo("<center><h1>Error $code</h1></center>");
    }
    break;
  case "post":
    if(empty($_SESSION["id"])) exit("Please log in");
    if(!isset($_FILES["thumbnail"]) || $_FILES["thumbnail"]["error"] != UPLOAD_ERR_OK || empty($_POST["levelid"])) {
    	echo "<center><h1>Level ID or Thumbnail not specified</h1></center>";
      	break;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $filename = $_FILES["thumbnail"]["tmp_name"];
    
    
    if (!is_uploaded_file($filename)) {
    	die("Invalid upload");
	}
    
    $mime  = finfo_file($finfo, $filename);
    finfo_close($finfo);
	
    if(!$img || $mime !== 'image/png') {
    	echo "<center><h1>Only PNGs are supported.</h1></center>";
      	break;
    }
    if(file_exists(__DIR__ . "/../thumbs/" . intval($_POST["levelid"]) . ".png") || file_exists(__DIR__ . "/../pending/" . intval($_POST["levelid"]) . ".png")) {
		echo "<center><h1>Either the thumbnail has been posted or is pending.</h1></center>";
      	break;
    }
    if(!validateCaptcha()) {
      	echo "<center><h1>Failed Captcha</h1></center>";
    	break;
    }
    require __DIR__ . "/../incl/lib/connection.php";
    $query = $db->prepare("SELECT reason FROM bans WHERE person=:id AND banType = 4 AND personType = 0 AND isActive = 1");
    $query->execute([':id' => $_SESSION["id"]]);
    if($query->rowCount() != 0) {
      	$reason = htmlspecialchars(base64_decode($query->fetchColumn()));
      	echo "<center><h1>You have been banned!</h1><h4>$reason</h4></center>";
    	break;
    }
    move_uploaded_file($_FILES["thumbnail"]["tmp_name"], "../pending/" . intval($_POST["levelid"]) . ".png");
    $query = $db->prepare("INSERT INTO posts (author, authorid, levelid) VALUES (:a, :b, :c)");
    $query->execute([':a' => $_SESSION["username"], ':b' => $_SESSION["id"], ':c' => intval($_POST["levelid"])]);
    echo "<center><h1>Uploaded successfully! Awaiting approval</h1></center>";
    break;
  default:
    break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DindeGDPS Thumbnails</title>
    <style>
        html, body {
            background-color: darkslategray;
            color: white;
            font-family: Arial, Helvetica, sans-serif;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        .content {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
			flex-direction: column;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        input {
            margin-bottom: 3px;
            height: 30px;
        }
    </style>
  	<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>
</head>
<body>
    <div class="content">
      	<h1>DindeGDPS Thumbnails</h1>
        <?php
      		if(empty($_SESSION["id"])) {
            	echo <<<EOF
                    <form method="POST">
                        <h3>Login using your DindeGDPS Account</h3>
                        <input type="hidden" name="action" value="login">
                        <input type="text" name="username" placeholder="Username">
                        <input type="password" name="password" placeholder="Password">
                        <div class="cf-turnstile" data-sitekey="$siteKey"></div>
                        <input type="submit">
                    </form>
                EOF;
            } else {
              	$username = $_SESSION["username"];
                echo <<<EOF
                      <h3>Hi $username!</h3>
                      <form method="POST" enctype='multipart/form-data'>
                        <input type="hidden" name="action" value="post">
                        <input type="file" name="thumbnail">
                        <input type="number" name="levelid" placeholder="Level ID">
                        <div class="cf-turnstile" data-sitekey="$siteKey"></div>
                        <input type="submit">
                      </form>
                      <form method="POST">
                          <input type="hidden" name="action" value="logout">
                          <input type="submit" value="Log Out">
                      </form>
                EOF;
            }
      	?>
    </div>
</body>
</html>
