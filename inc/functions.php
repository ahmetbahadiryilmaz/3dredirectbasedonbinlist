<?php

function kartTipi($number){
    $number=str_replace(" ", "", $number);

    $cardType = array(
        "visa"       => "/^4[0-9]{12}(?:[0-9]{3})?$/",
        "mastercard" => "/^5[1-5][0-9]{14}$/",
        "amex"       => "/^3[47][0-9]{13}$/",
        "discover"   => "/^6(?:011|5[0-9]{2})[0-9]{12}$/",
        "dinners"    => "/^[300-305]d{11}$/",
        "dinners"    => "/^3[68]d{12}$/",
        "enroute"    => "/^2(014|149)d{11}$/",
        "jbc"        => "/^3d{15}$/",
        "jbc"        => "/^(2131|1800)d{11}$/",
    );

    foreach ($cardType as $key => $value) {
        if (preg_match($value,$number))
        {
            $type= $key;
            break;
        }else{
            $type= false;
        }
    }
    return $type;
}

function insertlog($orderid,$data,$tip){
    global $dbh; //global pdo conn
    $update = $dbh->prepare("insert into payment_logs (order_id,data,tip )  values(:order_id,:data,:tip) ");
    $update->execute(
        [
        'order_id' => $orderid,
        'data' =>$data,
        'tip' =>$tip
       ]);
}
?>