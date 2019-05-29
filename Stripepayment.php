<?php
defined('BASEPATH') || exit('No direct script access allowed');
Class Stripepayment
{

    protected $CI;
    public $secret_key = '';
    public function __construct()
    {
        $this->CI = & get_instance();
        
        if($this->CI->config->item('PAYMENT_MODE') == 'live'){
            $this->secret_key  = $this->CI->config->item('STRIPE_SECRET_KEY_LIVE');
        }else{
            $this->secret_key  = $this->CI->config->item('STRIPE_SECRET_KEY_TEST');
        }
       
        //require_once $this->CI->config->item('third_party') . "Stripe" . DS . "init.php";
        require_once $this->CI->config->item('third_party') . "Stripe" . DS . "init.php";
    }
    public function create_customer($email_id){
        
        \Stripe\Stripe::setApiKey($this->secret_key);
        $customer = \Stripe\Customer::create(array(
                                "description" => "Customer for $email_id on " . date('Y-m-d H:i:s'),
                                "email" => $email_id ,
                            ));
       
        if(!empty($customer)){
            return $customer->id;            
        }else{
            return '';
        }
    }
    public function get_stripe_token_details($stripe_token_id)
    {
        \Stripe\Stripe::setApiKey($this->secret_key);

        $response_array['status'] = 'error';
        $response_array['message'] = 'Something went wrong';

        try
        {
            $stripe_token = \Stripe\Token::retrieve($stripe_token_id);

            if(!empty($stripe_token))
            {
                $response_array['status']               = 'success';
                $response_array['message']              = 'success';
                $response_array['card_fingerprint']     = $stripe_token->card->fingerprint;
            }

        }catch(Exception $e)
        {
            $response_array['status'] = 'error';
            $response_array['message'] = $e->getMessage();
        }        
            
        return $response_array;    
    }
    //express account function*/
    public function createUserDashboardLink($account_id,$secret_key)
    {
        \Stripe\Stripe::setApiKey($secret_key);
        try
        {
            $account = \Stripe\Account::retrieve($account_id);
            $account = $account->login_links->create();
            $url = $account->url;
            $reutn_arr=array();
            $return_arr['success']=1;
            $return_arr['url']=$url;
            return $return_arr;
        }catch(Exception $e)
        {
            
            $return_arr['success']=0;
            $return_arr['url']="";
            return $return_arr;
        }
    }
    //express account function*/
    public function check_customer_card_exists($stripe_customer_id, $card_fingerprint)
    {

        \Stripe\Stripe::setApiKey($this->secret_key);

        $response_array['status']   = 'error';
        $response_array['message']  = 'Something went wrong';
        $customer_cards = $this->get_customer_all_cards($stripe_customer_id);
        if($customer_cards['status'] == 'success')
        {
            $card_exists = FALSE;

            $stripe_card_id = NULL;

            if(!empty($customer_cards['cards_data']) && count($customer_cards['cards_data']))
            {
                foreach ($customer_cards['cards_data'] as $card_info) 
                {

                    if(trim($card_info->fingerprint) == trim($card_fingerprint))
                    {
                        $card_exists = TRUE;
                        $stripe_card_id = $card_info->id;
                        break;
                    }
                }
            }

            //////

            $response_array['status']           = 'success';
            $response_array['card_exists']      = $card_exists;
            $response_array['stripe_card_id']   = $stripe_card_id;

        }
        else
        {
            $response_array['status']   = 'error';
            $response_array['message']  = $customer_cards['message'];
        }
        

        return $response_array;
    }
    public function get_customer_all_cards($stripe_customer_id)
    {
        \Stripe\Stripe::setApiKey($this->secret_key);
        try
        {
            $cards_details =  \Stripe\Customer::retrieve($stripe_customer_id)->sources->all(
                                array(
                                        'limit'     => 100, 
                                        'object'    => 'card'
                                    )
                            );
            $cards_array = $cards_details->data;
            $response_array['status']       = 'success';
            $response_array['cards_data']   = $cards_array;

        }catch(Exception $e)
        {
            $response_array['status'] = 'error';
            $response_array['message'] = $e->getMessage();
        }
        
        return $response_array;
    }
    public function save_card_in_stripe($stripe_customer_id, $stripe_token_id)
    {
        \Stripe\Stripe::setApiKey($this->secret_key);

        $response_array['status']   = 'error';
        $response_array['message']  = 'Something went wrong';

        try
        {
            $customer = \Stripe\Customer::retrieve($stripe_customer_id);

            $response = $customer->sources->create(array("source" => $stripe_token_id));

            if(!empty($response))
            {
                $response_array['status']           = 'success';
                $response_array['stripe_card_id']   = $response->id;            
            }
        
        }catch(Exception $e)
        {
            $response_array['status']   = 'error';
            $response_array['message']  = $customer_cards['message'];
        }    

        return $response_array;
    }
     public function remove_card_from_stripe($stripe_customer_id, $stripe_card_id)
    {
        \Stripe\Stripe::setApiKey($this->secret_key);
        $response_array['status']   = 'error';
        $response_array['message']  = 'Something went wrong';

        try
        {
            $customer = \Stripe\Customer::retrieve($stripe_customer_id);

            $response = $customer->sources->retrieve($stripe_card_id)->delete();

            if(!empty($response))
            {
                $response_array['status']           = 'success';
            }
        
        }catch(Exception $e)
        {
            $response_array['status']   = 'error';
            $response_array['message']  = $customer_cards['message'];
        }    

        return $response_array;
    }

    public function get_stripe_token($card){

        \Stripe\Stripe::setApiKey($this->secret_key);

        $paymentInputArr = array(
                        "card" => $card
                    );
        try
        {
            $payment = \Stripe\Token::create($paymentInputArr);    
        }
        catch(Exception $e)
        {
             $response_array['status'] = 'error';
             $response_array['message'] = $e->getMessage();
        }
        if(!empty($payment['id'])){
            return $payment['id'];
        }else{
            return $response_array;
        }
    }

    public function create_subscription($card_token,$strip_customerid,$stripe_plan_id,$credit_card_name,$secret_key,$is_autorecurring= true,$expired_date_package = ''){
            \Stripe\Stripe::setApiKey($this->secret_key);

            $subscription_array = array(
                                    "source" => $card_token, // obtained from Stripe.js
                                    "customer" => $strip_customerid,
                                    "plan" => $stripe_plan_id,
                                    "metadata" => array(
                                        "name" => $credit_card_name
                                    )
                                );
                    
            /*if($is_autorecurring == false){
                $subscription_array['billing'] = 'send_invoice';
                $subscription_array['days_until_due'] = '1';

            }*/
            $subscription = \Stripe\Subscription::create($subscription_array);
            
            if(!empty($subscription)){
                return $subscription;
            }else{
                return array();
            }
    }


    public function create_plan($plan_details= array(),$secret_key){          
        \Stripe\Stripe::setApiKey($this->secret_key);
        $plan_stripe_reponse = \Stripe\Plan::create($plan_details);        
        return $plan_stripe_reponse;
    }

    public function create_recipient($recipient_name,$stripe_token,$recipient_email){
        \Stripe\Stripe::setApiKey($this->secret_key);
        $recipient = \Stripe\Recipient::create(array(
                                  "name" => $recipient_name,
                                  "type" => "individual",
                                  "bank_account" => $stripe_token,
                                  "email" => $recipient_email
                                  )
                                );        
            if(!empty($recipient)){
                return $recipient;    
            }else{
                return array();
            }
            
    }

    public function create_charge($amount,$stripe_token,$stripe_account_id){        

        \Stripe\Stripe::setApiKey($this->secret_key);
        $curreny_type = $this->CI->config->item('CURRENCY_CODE');
        $charge_response = \Stripe\Charge::create(array(
                                              'amount' => $amount,
                                              'currency' => $curreny_type,
                                              'source' => $stripe_token,
                                              'destination' => $stripe_account_id
                                            ));
        $charge_response = json_decode($charge_response,true);
        if(!empty($charge_response)){
            return $charge_response;
        }else{
            return array();
        }
    }

    public function cancel_subscription($subscription_trascrition_id){
        try{
            \Stripe\Stripe::setApiKey($this->secret_key);
            $subscription = \Stripe\Subscription::retrieve($subscription_trascrition_id);
            $subscription_cancel = $subscription->cancel(['at_period_end' => true]);
            //$subscription_cancel = json_decode($subscription_cancel,true);                        
            if(!empty($subscription_cancel)){            
                return $subscription_cancel;
            }else{
                return array();
            }            
         }catch(Exception $e)
        {
            $return['success'] = 0;
            $return['message'] = $e->getMessage();
            return $return;
        }
    }


    public function cancel_subscription_direct($subscription_trascrition_id){
        try{
            \Stripe\Stripe::setApiKey($this->secret_key);
            $subscription = \Stripe\Subscription::retrieve($subscription_trascrition_id);
            $subscription_cancel = $subscription->cancel();
            if(!empty($subscription_cancel)){            
                return $subscription_cancel;
            }else{
                return array();
            }            
         }catch(Exception $e)
        {
            $return['success'] = 0;
            $return['message'] = $e->getMessage();
            return $return;
        }
    }


  public function create_charge_with_card_id($stripe_data){
       
       /*
        $stripe_data['transaction_id']       =   "";
        $stripe_data['amount']              =   "";
        $stripe_data['stripe_card_id']      =   "';
        $stripe_data['stripe_customer_id']  =   "";
        $stripe_data['stripe_description']  =   "";
       */


        try{

                
                // \Stripe\Stripe::setApiKey($this->secret_key);
                // $curreny_type = $this->CI->config->item('CURRENCY_CODE');
                // $charge_response = \Stripe\Charge::create(array(
                //                                       'amount'          => $amount * 100,
                //                                       'currency'        => $curreny_type,
                //                                       "source"          => $stripe_card_id,
                //                                       "description"     => 
                //                                       $stripe_description,
                //                                       "customer"        => $stripe_customer_id,
                //                                       'capture' => false
                //                                     ));



                 \Stripe\Stripe::setApiKey($this->secret_key);
                $curreny_type = $this->CI->config->item('CURRENCY_CODE');

                $charge_response = \Stripe\Charge::create(array(
                                                      'amount'          => $stripe_data['amount'] * 100,
                                                      'currency'        => $curreny_type,
                                                      "source"          => $stripe_data['stripe_card_id'],
                                                      "description"     =>  $stripe_data['stripe_description'],
                                                      "customer"        => $stripe_data['stripe_customer_id'],
                                                      'capture' => false
                                                    ));

                if(!empty($charge_response)){
                    $return['success'] = 1;
                    $return['message'] = "Payment successfully.";
                    $return['data'] = $charge_response;
                }else{
                    $return['success'] = 0;
                    $return['message'] = "No response from stripe";
                }
        }catch(Exception $e){
             $e->getMessage();             
            $return['success'] = 0;
            $return['message'] = $e->getMessage();
        }

        return $return;
        
    }


    public function capture_charge_with_charge_id($stripe_charge_id,$secret_key){
        try{
                
                \Stripe\Stripe::setApiKey($this->secret_key);
                $curreny_type = $this->CI->config->item('CURRENCY_CODE');
                $charge = \Stripe\Charge::retrieve($stripe_charge_id);
                $charge_response = $charge->capture();
                if($charge_response['paid'] == '1'){
                    $return['success'] = 1;
                    $return['message'] = "Payment successfully.";
                }else{
                    $return['success'] = 0;
                    $return['message'] = "No response from stripe";
                }
        }catch(Exception $e){
             $e->getMessage();
            $return['success'] = 0;
            $return['message'] = $e->getMessage();
        }

        return $return;
        
    }
    public function create_refund_charge($charge_id,$secret_key)
    {
        \Stripe\Stripe::setApiKey($this->secret_key);
        try
        {
            $re = \Stripe\Refund::create(array(
            "charge" => $charge_id,
            "reason" => "requested_by_customer",
            // "refund_application_fee"=>true,
            // "reverse_transfer"=>true
            )); 
            if(!empty($re))
            {
                $return['success']  =   1;
                $return['message']  =   $this->CI->lang->line('ORDER_CANCEL_SUCCESS');
                $return['id']       =   $re->id;
            }   
            else
            {
                $return['success']  =   0;
                $return['message']  =   $this->CI->lang->line('ORDER_CANCEL_FAILURE');
            }
        }
        catch(Exception $e)
        {
            $e->getMessage();
            $return['success'] = 0;
            $return['message'] = $e->getMessage();
        }
        return $return;
    }
    public function payout_to_connect_account($CONNECTED_STRIPE_ACCOUNT_ID,$amount,$description)
    {
        // echo $secret_key;
        // echo "<br>";
        // echo $CONNECTED_STRIPE_ACCOUNT_ID;
        // echo "<br>";
        // echo $amount;
        // echo "<br>";
        // echo $description;
        // echo "<br>";
        // die;

        \Stripe\Stripe::setApiKey($this->secret_key);
        try
        {
            $transfer = \Stripe\Transfer::create([
              "amount" => $amount * 100,
              "currency" => "usd",
              "destination" => $CONNECTED_STRIPE_ACCOUNT_ID,
              "description" => $description
            ]);
            
            if(!empty($transfer))
            {
                $return['success']  =   1;
                $return['message']  =   $this->CI->lang->line('Payout successfully.');
                $return['id']       =   $transfer->id;
            }   
            else
            {
                $return['success']  =   0;
                $return['message']  =   $this->CI->lang->line('Problem while payout');
            }
        }
        catch(Exception $e)
        {
          
            $e->getMessage();
            $return['success'] = 0;
            $return['message'] = $e->getMessage();
        }
        return $return;
    }
    public function verify_oauth_token($code){
        
        $url = 'https://connect.stripe.com/oauth/token';
        $fields = array(
            'client_secret' => urlencode($this->secret_key),
            'code' => $code,
            'grant_type' => 'authorization_code'
        );

        //url-ify the data for the POST
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');        
        $ch = curl_init();
        
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, count($fields));
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        $result = curl_exec($ch);        
        $customer_response = json_decode($result,true);
        
        curl_close($ch);
        if(!empty($customer_response)){
            return $customer_response;
        }else{
            return array();
        }
    }

}

/* End of file General.php */
/* Location: ./application/libraries/General.php */
