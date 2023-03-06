<?php
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_set_cookie_params(['samesite' => 'None']);
require_once "vendor/autoload.php";
require_once "inc/defines.php";
require_once "inc/functions.php";
require_once "inc/binler.php";

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

 
$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None',
    'cookie_secure' => true,
]);
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
$session->start();


require_once("../../xpanel/includes/vt.php");
require_once "Poslar/_EstPOS.php";
require_once "Poslar/GarantiPOS.php";
require_once "Poslar/PosnetPOS.php";
require_once "Poslar/PayforPOS.php";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$ix=0;
$goid=$_GET["oid"];
insertlog($goid,$_GET["banka"],0);
insertlog($goid,json_encode($_POST),1);

file_put_contents("success_log.poslar.txt", $_GET["banka"]."|".$goid."\n",FILE_APPEND);
file_put_contents("success_log.poslar.txt", json_encode($_POST)."\n",FILE_APPEND);

$email=false;
if($_GET["banka"]==BankaKodlari::$HALK){
    $EstPOS = new _EstPOS(BankaKodlari::$HALK);
    $pos=$EstPOS->getPos();
    echo "halk";
}

else if($_GET["banka"]==BankaKodlari::$ISBANKASI || ($_GET["banka"]=="isbank")){
    $EstPOS = new _EstPOS(BankaKodlari::$ISBANKASI);
    $pos=$EstPOS->getPos();
}
else if($_GET["banka"]==BankaKodlari::$TCZIRAAT){
    $EstPOS = new _EstPOS(BankaKodlari::$TCZIRAAT);
    $pos=$EstPOS->getPos();
}
else if($_GET["banka"]==BankaKodlari::$AKBANK){
    $EstPOS = new _EstPOS(BankaKodlari::$AKBANK);
    $pos=$EstPOS->getPos();
}
else if($_GET["banka"]==BankaKodlari::$FINANS || ($_GET["banka"]=="finans")){
    $payforpos = new PayforPOS();
    $pos=$payforpos->getPos();
    echo "finans";
}
else if($_GET["banka"]==BankaKodlari::$YAPIKREDI || ($_GET["banka"]=="yapikredi")){
    $PosnetPOS = new PosnetPOS();
    $pos=$PosnetPOS->getPos();
 
    echo "yapıirekdis";
}
else if($_GET["banka"]==BankaKodlari::$GARANTI || ($_GET["banka"]=="garanti")){
    $GarantiPOS = new GarantiPOS();
    $pos=$GarantiPOS->getPos();
     
    echo "garantis";
}
try  { 
    $order = $session->get('order');
    $card=false;
    if($order){

        if(isset($order["email"])){
           $email=$order["email"];
 
        }
         
        echo "\n";
        file_put_contents("success_log.poslar.txt", json_encode($order)."\n",FILE_APPEND);
        insertlog($goid,json_encode($order),2);
        $card = $session->get('card');
 
        file_put_contents("success_log.poslar.txt", json_encode($session->all())."\n",FILE_APPEND);
        insertlog($goid,json_encode($session->all()),3);
        echo "\n";
        $pos->prepare($order, AbstractGateway::TX_PAY);
    }
    
    if($card){
        $card= \Mews\Pos\Factory\CreditCardFactory::create(
            $pos,
            $card['number'],
            $card['year'],
            $card['month'],
            $card['cvv'],
            $card['name'],
            $card['type'] ?? null
        );


        $pos->payment($card);
        file_put_contents("success_log.poslar.txt", "viacard"."\n",FILE_APPEND);
        file_put_contents("success_log.poslar.txt", json_encode($session->get('card'))."\n",FILE_APPEND);
        insertlog($goid,json_encode($session->get('card')),4);

    }else{
        $pos->payment();
        file_put_contents("success_log.poslar.txt", "nocard"."\n",FILE_APPEND);
        insertlog($goid,"nocard",5);

    }
    // Ödeme başarılı mı?
    $pos->isSuccess();
    echo "<pre>"; 
    // Sonuç çıktısı 
    $response =($pos->getResponse());
    insertlog($goid,json_encode($response),6);
    if(isset($response["3d_all"]["email"])){
        $email= $response["3d_all"]["email"];
    }
    
    if(isset($response["3d_all"]["Email"])){
        $email= $response["3d_all"]["email"];
    }
    if($response["status"]=="approved"){
      $kont     =   $dbh->prepare("SELECT * FROM collection WHERE order_r=? ");
      $kont->execute(array($goid));
      $kont     =   $kont->fetch(PDO::FETCH_ASSOC);
      if($kont){
          $update = $dbh->prepare("UPDATE collection SET status = :status WHERE order_r = :order_r");
          $update->execute(['status' => 1, 'order_r' => $goid]);

          if(($email)){


            $ksorgu     =   $dbh->prepare("SELECT * FROM customer WHERE email=? ");
            $ksorgu->execute(array($email));
            $kbilgi     =   $ksorgu->fetch(PDO::FETCH_ASSOC);
            if (!$ksorgu->rowCount()) {
                $hata = "Giriş Yapılamadı";
            } else {
                $_SESSION['deneme'] = "asdasd";
            //$_SESSION['yonetici'] = $kbilgi;
                $_SESSION['musteri'] = $kbilgi['id'];
                $_SESSION['musteriadi'] = $kbilgi['email'];
                $_SESSION['musteriname'] = $kbilgi['name_surname'];
                $yonetici['isim'] = $kbilgi['email'];
                $_SESSION["b"] = array(
                    "email" => $kbilgi["email"]
                );
            }
        }
        if($kont){
           $kbb     =   $dbh->prepare("SELECT * FROM customer WHERE id=? ");
           $kbb->execute(array($kont['user']));
           $kbb     =   $kbb->fetch(PDO::FETCH_ASSOC);
 
       }

   }
   $redirectek="";
   if($_GET["banka"]==BankaKodlari::$GARANTI || ($_GET["banka"]=="garanti")){
    $redirectek="&campaign=".base64_encode($response["3d_all"]["campaignchooselink"]);
   }

 header('Location: '.$url.'/success.php?banka='.$_GET["banka"]."&durum=basari&oid=".$response["order_id"].$redirectek);
}else{
 
    echo "HATA!!".$response["status"];
    insertlog($goid, json_encode([
        "status"=> $response["status"],
        "md_status"=> $response["md_status"],
        "md_error_message"=> $response["md_error_message"],
        "error_code"=> $response["error_code"],
        "error_message"=> $response["error_message"],
    ]),6);

    
    $update = $dbh->prepare("UPDATE collection SET 
    status = :status ,  
    error_description = :error_description
    WHERE order_r = :order_r ");
    $error_description=$response["status_detail"].",".$response["error_message"];
    $update->execute(['error_description'=>$error_description,'status' => 2, 'order_r' => $goid]);

    

    header('Location: '.$url.'/error.php?banka='.$_GET["banka"]."&durum=hata&oid=".$goid);

 
}
file_put_contents("success_log.poslar.txt", json_encode($response)."\n",FILE_APPEND);




    // response içeriği için /examples/template/_payment_response.php dosyaya bakınız.
} catch (Mews\Pos\Exceptions\HashMismatchException $e) {
 insertlog($goid, json_encode($e),8);
 file_put_contents("success_log.poslar.txt", json_encode($e)."\n",FILE_APPEND);
 header('Location: '.$url.'/error.php?banka='.$_GET["banka"]."&durum=hata&oid=".$goid);

}

?>