<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "translator_aksara_jawa";
$conn = mysqli_connect($servername, $username, $password, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
};
if (session_status() == PHP_SESSION_NONE) {
    session_start();
};
if (isset($_POST['report'])) {
    $sql2 = "INSERT INTO `reports` (`Name`, `Report`) VALUES ('$_SESSION[Name]', '$_POST[translate]')";
    $result2 = $conn->query($sql2);
    if($result2){
        header("Location: ../?menu=translate");
    }
    else{
        echo '<script>alert("Request failed to send, please try again");</script>';
    }
} else {
    require __DIR__ . '/vendor/autoload.php';
    $target_dir = "uploads/";
    $uploadOk = 1;
    $FileType = strtolower(pathinfo($_FILES["image"]["name"],PATHINFO_EXTENSION));
    $target_file = $target_dir . generateRandomString() .'.'.$FileType;
    // Check file size
    if ($_FILES["image"]["size"] > 5000000) {
        header('HTTP/1.0 403 Forbidden');
        echo "Sorry, your file is too large.";
        $uploadOk = 0;
    }
    if($FileType != "jpg" && $FileType != "png" && $FileType != "jpeg") {
        header('HTTP/1.0 403 Forbidden');
        echo "Sorry, please upload a png file";
        $uploadOk = 0;
    }
    if ($uploadOk == 1) {

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            uploadToApi($target_file);
        } else {
            header('HTTP/1.0 403 Forbidden');
            echo "Sorry, there was an error uploading your file.";
        }
    }

    function uploadToApi($target_file){
        require __DIR__ . '/vendor/autoload.php';
        $fileData = fopen($target_file, 'r');
        $client = new \GuzzleHttp\Client();
        try {
        $r = $client->request('POST', 'https://api.ocr.space/parse/image',[
            'headers' => ['apiKey' => 'K86583684888957'],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $fileData
                ]
            ]
        ], ['file' => $fileData]);
        $response =  json_decode($r->getBody(),true);
        $str = str_replace(PHP_EOL, ' ', $response['ParsedResults'][0]['ParsedText']);
        // print_r($response['ParsedResults'][0]['ParsedText']);
        header("Location: ../?menu=translate&text=".$str);
        } catch(Exception $err) {
            header('HTTP/1.0 403 Forbidden');
            echo $err->getMessage();
        }
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
?>