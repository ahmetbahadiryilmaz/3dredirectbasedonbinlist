<?php

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;

class GarantiPOS{
    public  $baseUrl="";
    public  $ip ;
    public  $session ;
    public  $request ;
    public  $installment ;
    public  $orderno ;
    public  $successUrl;
    public  $amount=1;
    public  $failUrl;
    private $currency="TRY";
    private $pos=null;
    private $lang =  AbstractGateway::LANG_TR;

    public $userid;
    public $usermail;    
    public $userfullname;



    public function __construct( ) {
        //account bilgileri kendi account bilgilerinizle degistiriniz
     
        $account = AccountFactory::createGarantiPosAccount(
            'garanti',
            '$merchantid',
            'PROVAUT',
            '$password*',
            '$terminalid',
            \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_PAY,
            '$storekey'
        );
        
        $this->pos = $this->getGateway($account);
        
    }
    public function getPos()
    {
        return $this->pos;
    }

    function getFormData( ){
   
        $transaction = $this->request->get('tx', AbstractGateway::TX_PAY);
        $order = $this->getNewOrder(
            $this->baseUrl,$this->ip,$this->request->get('currency', 'TRY'),$this->session,$this->request->get('installment'),
            $this->request->get('is_recurring', 0) == 1,
            $this->request->get('lang', AbstractGateway::LANG_TR)
        );
        $this->session->set('order', $order);

        $cardinfo=$this->request->request->all();
         
        $card = $this->createCard($this->pos,  $cardinfo);

        /**
        * Vakifbank'ta provizyonu (odemeyi) tamamlamak icin tekrar kredi kart bilgileri isteniyor, bu yuzden kart bilgileri kaydediyoruz
        */
        $this->session->set('card', $this->request->request->all());

        $this->pos->prepare($order, $transaction, $card);

        try {
        $formData = $this->pos->get3DFormData();
        //dd($formData);
        } catch (\Throwable $e) {
        var_dump($e);
        exit();
        }
    
        return ($formData);   
    }

    function getNewOrder(): array {
        return $this->createNewPaymentOrderCommon( );
    }
    
    
    function doPayment(\Mews\Pos\PosInterface $pos, string $transaction, ?\Mews\Pos\Entity\Card\AbstractCreditCard $card)
    {
        if ($pos->getAccount()->getModel() === \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE
            && \Mews\Pos\Gateways\AbstractGateway::TX_POST_PAY !== $transaction
        ) {
            //bu asamada $card regular/non secure odemede lazim.
            $pos->payment($card);
        } else {
            $pos->payment();
        }
    }
    
    
    
    function getGateway(\Mews\Pos\Entity\Account\AbstractPosAccount $account): ?\Mews\Pos\PosInterface
    {   
        try {
            $handler = new \Monolog\Handler\StreamHandler(__DIR__.'/../var/log/pos.log', \Psr\Log\LogLevel::DEBUG);
            $logger = new \Monolog\Logger('pos', [$handler]);
    
            /*        $client = new HttpClient(
                new \Http\Client\Curl\Client(),
                new \Slim\Psr7\Factory\RequestFactory(),
                new \Slim\Psr7\Factory\StreamFactory()
            );*/
    
            $pos = \Mews\Pos\Factory\PosFactory::createPosGateway($account, null, null, $logger);
            $pos->setTestMode(false);
    
            return $pos;
        } catch (Exception $e) {
            var_dump($e);
            exit();
        }
    }
    
    function createCard(\Mews\Pos\PosInterface $pos, array $card): \Mews\Pos\Entity\Card\AbstractCreditCard
    {
        try {
            return \Mews\Pos\Factory\CreditCardFactory::create(
                $pos,
                $card['number'],
                $card['year'],
                $card['month'],
                $card['cvv'],
                $card['name'],
                $card['type'] ?? null
            );
        } catch (Exception $e) {
            var_dump($e);
            exit();
        }
    }
    
    function createNewPaymentOrderCommon(): array {
    
     
        
  
        $order = [
            'id'          => $this->orderno,
            'amount'      => $this->amount,
            'currency'    =>$this->currency,
            'installment' => $this->installment,
    
            //3d, 3d_pay, 3d_host odemeler icin zorunlu
            'success_url' => $this->successUrl,
            'fail_url'    => $this->failUrl,
    
            //gateway'e gore zorunlu olan degerler
            'ip'          => $this->ip, //EstPos, Garanti, KuveytPos, VakifBank
            'email'       => $this->usermail, // EstPos, Garanti, KuveytPos, VakifBank
            'name'        => $this->userfullname, // EstPos, Garanti
            'user_id'     => $this->userid, // EstPos
            'rand'        => md5(uniqid(time())), //EstPos, Garanti, PayFor, InterPos, VakifBank
        ];
    
        if ($this->lang) {
            //lang degeri verilmezse account (EstPosAccount) dili kullanilacak
            $order['lang'] = $this->lang;
        }
    
        return $order;
    }
    

    


}
?>