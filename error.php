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

 
require_once "Poslar/_EstPOS.php";
require_once "Poslar/GarantiPOS.php";
require_once "Poslar/PosnetPOS.php";
require_once "Poslar/PayforPOS.php";
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$goid=$_GET["oid"];
insertlog($goid,$_GET["banka"],0);
insertlog($goid,json_encode($_POST),1);

$orderid=$_GET["oid"];
 
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
    $email=false;
    echo "yapıirekdis";
}
else if($_GET["banka"]==BankaKodlari::$GARANTI || ($_GET["banka"]=="garanti")){
    $GarantiPOS = new GarantiPOS();
    $pos=$GarantiPOS->getPos();
    $email=$_POST["Email"];
    echo "garantis";
}

$order = $session->get('order');
$card=false;
if($order){

    if(isset($order["email"])){
       $email=$order["email"];

    }
     
    echo "\n";
    insertlog($goid,json_encode($order),2);
    $card = $session->get('card');

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
    insertlog($goid,json_encode($session->get('card')),4);

}else{
    $pos->payment();
    file_put_contents("success_log.poslar.txt", "nocard"."\n",FILE_APPEND);
    insertlog($goid,"nocard",5);

}
// Ödeme başarılı mı?
echo "issucess:".json_encode($pos->isSuccess())."\n";
echo "<pre>"; 
// Sonuç çıktısı 
$response =($pos->getResponse());
insertlog($goid,json_encode($response),6);



 
file_put_contents("error_log.poslar.txt", json_encode($_POST)."\n",FILE_APPEND);
if($orderid){ 
        header('Location: '.$url.'/musteri/error.php?banka='.$_GET["banka"]."&durum=hata");
}
 
?>