<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('session.cookie_samesite', 'None');
session_set_cookie_params(['samesite' => 'None']);

require_once "vendor/autoload.php";
require_once "inc/defines.php";
require_once "inc/functions.php";
require_once "inc/binler.php";
require_once "Poslar/_EstPOS.php";
require_once "Poslar/GarantiPOS.php";
require_once "Poslar/PosnetPOS.php";
require_once "Poslar/PayforPOS.php";

$sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
    'cookie_samesite' => 'None',
    'cookie_secure' => true,
]);
$session        = new \Symfony\Component\HttpFoundation\Session\Session($sessionHandler);
$session->start();

 

//Ziraat Bankası	4546 7112 3456 7894	12/2026	000	a	Visa	Credit	00 - Başarılı
//Akbank	5571 1355 7113 5575	12/2026	000	a	Mastercard	Credit	00 - Başarılı
//Garanti Bankası	5406 6975 4321 1173	03/2023	465	123456	Mastercard	Credit	00 - Başarılı
//QNB Finansbank	4155 6501 0041 6111	12/2025	000	Ekranda gelen kod	Visa	Credit	00 - Başarılı
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;

$_POST["number"]=str_replace(" ","",$_POST["cardnumber"]);
$yy=explode("/",$_POST["date"]);
$_POST["month"]=$yy[0]; 
$_POST["year"]=$yy[1];
$karttip=kartTipi($_POST["number"]);

if($karttip=="mastercard"){
    $_POST["type"]=AbstractCreditCard::CARD_TYPE_MASTERCARD;
}elseif($karttip=="visa"){
    $_POST["type"]= AbstractCreditCard::CARD_TYPE_VISA;
}; 


$_POST["number"]=str_replace(" ","",$_POST["cardnumber"]);


$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$ip = $request->getClientIp();

$hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')."://$_SERVER[HTTP_HOST]";
$subMenu = [];




$kartno=$_POST["number"];
$year=$_POST["year"];
$month=$_POST["month"];
$cvv=$_POST["cvv"];
$type=$_POST["type"];
$amount=$_POST["amount"];
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/



$installment=1;
/*
$taks     =   $dbh->prepare("SELECT * FROM  installments WHERE id=?");
$taks->execute(array(@$_POST['taksit']));
$taks     =   $taks->fetch(PDO::FETCH_ASSOC);*/




$installment  =isset($_POST['taksit'])?$_POST['taksit']: 0;


$taks     =   $dbh->prepare("SELECT * FROM  installments WHERE id=?");
$taks->execute(array(@$_POST['taksit']));
$taks     =   $taks->fetch(PDO::FETCH_ASSOC);
$amountg = $_POST['amount'];

if($taks){
    $installment=$taks['installment'];
    $amountg = $_POST['amount'];
    $amount = $_POST['amount'] + ($_POST['amount'] * $taks['ratio']) / 100;
}else{
    $taks['id']=0;
    $amount = $_POST['amount'];
    $installment=1;
}
$request->attributes->set('number', $kartno);
$request->attributes->set('year', $year);
$request->attributes->set('month', $month);
$request->attributes->set('cvv', $cvv);
$request->attributes->set('type', $type);


$altikarakter = substr($_POST["number"],0,6);
$sekizkarakter = substr($_POST["number"],0,8);
$baseUrl = $url."/pos/";
$yeniOrderId= date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
$successUrl = $baseUrl."success.php?oid=".$yeniOrderId;
$errorUrl = $baseUrl."error.php?oid=".$yeniOrderId;
$formData=[];
$bank=-1;
//bin listesinden kod çekmece
$bank['bank'] = $binlerbankaisim[$binlerbanka[$altikarakter]];
/*
$bankalar = $dbh->query("SELECT * FROM cart_detail", PDO::FETCH_ASSOC);
foreach ($bankalar as $banka) { 
    $binlerbanka[$banka["cart_no"]]  = $banka["bank"];
}
 
$bank     =   $dbh->prepare("SELECT * FROM cart_detail WHERE cart_no=? or cart_no=?");
$bank->execute(array($altikarakter,$sekizkarakter));
$bank     =   $bank->fetch(PDO::FETCH_ASSOC);
$pos_list     =   $dbh->prepare("SELECT * FROM pos_list WHERE id=?");
$pos_list->execute(array($bank['bank']));
$pos_list     =   $pos_list->fetch(PDO::FETCH_ASSOC);

$sett     =   $dbh->prepare("SELECT * FROM general_settings WHERE id=1");
$sett->execute(array());
$sett     =   $sett->fetch(PDO::FETCH_ASSOC);

 */
//bankasismi// $binlerbankaisim[$binlerbanka[$altikarakter]];
if($bank['bank']==BankaKodlari::$TCZIRAAT   )
{//3
    $bank=3;
    $formData= ziraatyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId);
}
elseif(   $bank['bank']==BankaKodlari::$AKBANK   )
{//6
    $bank=6;
    $formData = akbankyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId);

}
elseif($bank['bank']== BankaKodlari::$FINANS  )
{//7
    $formData =finansyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId);
    $bank=7;
}
elseif($bank['bank']==BankaKodlari::$GARANTI  )
{//1
    $formData = garantiyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId);
    $bank=1;
}
elseif($bank['bank']==BankaKodlari::$YAPIKREDI    )
{//2

    $formData = yapikrediyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId);
    $bank=2;

}
elseif($bank['bank']== BankaKodlari::$ISBANKASI   )
{//5
    $formData = isbankasiyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId);

    $bank=5;
}
elseif($bank['bank']==BankaKodlari::$HALK  )
{//4
    $formData = halkbankyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId);
    $bank=4;
}
else{
    echo $altikarakter." Bin Listesinde Yok<br>";
    echo $binlerbanka[$altikarakter];
    exit();
}


if($formData){

    $ins = $dbh->prepare("INSERT into collection SET user = :user, amount=:amount,amount_dov=:amount_dov, amount_g=:amount_g,order_r=:order_r,order_session=:order_session, bank =:bank , installments= :installments , installment_id=:installment_id,gift_install=:gift_install ,description=:description");
    $ins->execute([
        'user' => $_SESSION['musteri'],
        'amount' => $amount,
        'amount_dov' => "USD: ".number_format(($amount/(str_replace(",",".",$USD))),2,",",".")." / EUR: ".number_format(($amount/(str_replace(",",".",$EUR))),2,",","."),
        'amount_g' => $amountg,
        'order_r' =>$yeniOrderId,
        'order_session' =>json_encode($session->all()),
        'bank' => $bank,
        'installments' => $installment,
        'installment_id' => $taks['id'],
        'gift_install' => $taks['gift'],
        'description' => $_POST['desc'],
    ]);


}

?>
<form method="post" action="<?= $formData['gateway']; ?>"  class="redirect-form" role="form">
    <?php foreach ($formData['inputs'] as $key => $value) : ?>
        <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
    <?php endforeach; ?>
    <div class="text-center">Çekim Ekranına Yönlendiriliyorsunuz...</div>
    <hr>
    <div class="form-group text-center">
        <button type="submit" class="btn btn-lg btn-block btn-success">Lütfen Bekleyin</button>
    </div>
</form>
<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<script> 
    $(function () {
        var redirectForm = $('form.redirect-form')
        if (redirectForm.length) {
            redirectForm.submit()
        }
    })
</script>

<?php



function ziraatyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId)
{

    $EstPOS = new _EstPOS(BankaKodlari::$TCZIRAAT);
    $EstPOS->request=$request;
    $EstPOS->session=$session;
    $EstPOS->ip=$ip;
    $EstPOS->amount=$amount; 
    $EstPOS->installment=$installment; 
    $EstPOS->successUrl=$successUrl."&banka=" . BankaKodlari::$TCZIRAAT;
    $EstPOS->failUrl=$errorUrl."&banka=". BankaKodlari::$TCZIRAAT;
    $EstPOS->orderno=$yeniOrderId;
    $EstPOS->usermail=$_SESSION["musteriadi"]; 
    $EstPOS->userid=$_SESSION["musteri"];
    $EstPOS->userfullname=$_SESSION["musteriname"];
    return $EstPOS->getFormData();
}

function akbankyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId)
{ 


    $EstPOS = new _EstPOS(BankaKodlari::$AKBANK);
    $EstPOS->request=$request;
    $EstPOS->session=$session;
    $EstPOS->ip=$ip;
    $EstPOS->amount=$amount; 
    $EstPOS->installment=$installment; 
    $EstPOS->successUrl=$successUrl."&banka=" . BankaKodlari::$AKBANK;
    $EstPOS->failUrl=$errorUrl."&banka=". BankaKodlari::$AKBANK;
    $EstPOS->orderno=$yeniOrderId;
    $EstPOS->usermail=$_SESSION["musteriadi"]; 
    $EstPOS->userid=$_SESSION["musteri"];
    $EstPOS->userfullname=$_SESSION["musteriname"];
    return $EstPOS->getFormData();
}


function halkbankyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session,$amount,$installment,$yeniOrderId)
{

    $EstPOS = new _EstPOS(BankaKodlari::$HALK);
    $EstPOS->request=$request;
    $EstPOS->session=$session;
    $EstPOS->ip=$ip;
    $EstPOS->amount=$amount; 
    $EstPOS->installment=$installment; 
    $EstPOS->successUrl=$successUrl."&banka=". BankaKodlari::$HALK;
    $EstPOS->failUrl=$errorUrl."&banka=". BankaKodlari::$HALK;
    $EstPOS->orderno=$yeniOrderId;
    $EstPOS->usermail=$_SESSION["musteriadi"];
    $EstPOS->userid=$_SESSION["musteri"];
    $EstPOS->userfullname=$_SESSION["musteriname"];
    return $EstPOS->getFormData();
}
function isbankasiyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session , $amount,$installment,$yeniOrderId){
    $EstPOS = new _EstPOS(BankaKodlari::$ISBANKASI);
    $EstPOS->request=$request;
    $EstPOS->session=$session;
    $EstPOS->ip=$ip;
    $EstPOS->amount=$amount; 
    $EstPOS->installment=$installment; 
    $EstPOS->successUrl=$successUrl."&banka=". BankaKodlari::$ISBANKASI;
    $EstPOS->failUrl=$errorUrl."&banka=". BankaKodlari::$ISBANKASI;
    $EstPOS->orderno=$yeniOrderId;
    $EstPOS->usermail=$_SESSION["musteriadi"];
    $EstPOS->userid=$_SESSION["musteri"];
    $EstPOS->userfullname=$_SESSION["musteriname"];
    return $EstPOS->getFormData();
}

function garantiyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session , $amount,$installment,$orderId){
    $GarantiPOS = new GarantiPOS();
    $GarantiPOS->request=$request;
    $GarantiPOS->session=$session;
    $GarantiPOS->ip=$ip;
    $GarantiPOS->amount=$amount;
    $GarantiPOS->installment=$installment; 
    $GarantiPOS->successUrl=$successUrl."&banka=". BankaKodlari::$GARANTI;
    $GarantiPOS->failUrl=$errorUrl."&banka=". BankaKodlari::$GARANTI;
    $GarantiPOS->orderno=$orderId;
    $GarantiPOS->usermail=$_SESSION["musteriadi"];
    $GarantiPOS->userid=$_SESSION["musteri"];
    $GarantiPOS->userfullname=$_SESSION["musteriname"];
    return $GarantiPOS->getFormData();
}

function yapikrediyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session , $amount,$installment,$orderId){
    $PosnetPOS = new PosnetPOS();
    $PosnetPOS->request=$request;
    $PosnetPOS->session=$session;
    $PosnetPOS->ip=$ip;
    $PosnetPOS->amount=$amount;
    $PosnetPOS->installment=$installment; 
    $PosnetPOS->successUrl=$successUrl."&banka=". BankaKodlari::$YAPIKREDI;
    $PosnetPOS->failUrl=$errorUrl."&banka=". BankaKodlari::$YAPIKREDI;
    $PosnetPOS->orderno=$orderId;
    return $PosnetPOS->getFormData();
}



function finansyonlendir($baseUrl,$successUrl,$errorUrl,$request,$ip,$session , $amount,$installment,$orderId){
    $PayforPOS = new PayforPOS();
    $PayforPOS->request=$request;
    $PayforPOS->session=$session;
    $PayforPOS->ip=$ip;
    $PayforPOS->amount=$amount;
    $PayforPOS->installment=$installment; 
    $PayforPOS->successUrl=$successUrl."&banka=".BankaKodlari::$FINANS;
    $PayforPOS->failUrl=$errorUrl."&banka=".BankaKodlari::$FINANS;
    $PayforPOS->orderno=$orderId;
    $PayforPOS->usermail=$_SESSION["musteriadi"];
    $PayforPOS->userid=$_SESSION["musteri"];
    $PayforPOS->userfullname=$_SESSION["musteriname"];
    return $PayforPOS->getFormData();
}


?> 