<?php

namespace App\Services\PaypalService;

use Illuminate\Http\Request;
use App\Services\PaypalService\Factories\DefaultFactory;
use Exception;
use PDO;

class PaypalPaymentService
{
    private $config;
    protected $factory, $accessToken;

    public function __construct()
    {
        $this->factory = new DefaultFactory();
        $this->config = $this->getPaypalConfig([
            'sandbox_mode' => false,
            'url' => 'https://www.sandbox.paypal.com',
            'region' => 'GB',
            'email' => 'sb-eissv27527408@business.example.com',
            'password' => '4V+QzhTj',
        ]);
    }

    private function getPaypalConfig($configValues)
    {
        // $requiredConfig = [
        //     'sandbox_mode' => '',
        //     'url' => '',
        //     'region' => '',
        //     'email' => '',
        //     'password' => '',
        //     'bearer' => '',
        // ];

        $paypalConfig = $this->factory->objectFactory('PaypalConfig', $configValues);

        return $paypalConfig;
    }

    public function setAccessToken()
    {
        $url = $this->config->url . '/v1/oauth2/token';

        $clientId = config('services.paypal.client_id'); // Replace with your actual client ID
        $clientSecret = config('services.paypal.secret'); // Replace with your actual client secret
    
        $auth = $clientId . ':' . $clientSecret;
            
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_USERPWD => $auth,
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            CURLOPT_HTTPHEADER => array(
                "Accept: application/json",
                "Accept-Language: en_US",
            ),
        ]
        );

        $result= curl_exec($curl);
        if (curl_errno($curl)) {
            throw new Exception(curl_error($curl));
        }

        $array = json_decode($result, true); 

        if(! isset($array['access_token'])) {
            throw new Exception($array['error_description']);
        }

        $token = $array['access_token'];
        
        $this->accessToken = $token;
    }

    public function getPaymentLink(Request $request)
    {
        if(! isset($this->accessToken)) {
            $this->setAccessToken();
        }

        $url = $this->config->url . '/v2/checkout/orders';

        $productId = $request->product_id;
        $productName = $request->product_name;
        $productCurr = @$request->product_curr ?? 'gbp';
        $productPerUnitPrice = $request->product_price_per_unit_amount;
        $orderReqAmt = $request->product_requested_quantity;
        $orderTotal = $orderReqAmt * $productPerUnitPrice;

        $postBody = json_encode([
            "intent" => "CAPTURE",
            "purchase_units" => [
                [
                    // Accepts and array of items
                    "items" => [
                        [
                            "name" => $productName,
                            "sku" => $productId,
                            "quantity" => $orderReqAmt,
                            "unit_amount" => [
                                "currency_code"=> $productCurr,
                                "value"=> $productPerUnitPrice,
                            ],
                        ],
                    ],
                    "amount"=> [
                        "currency_code"=> $productCurr,
                        "value"=> $orderTotal, // Order total includes shipping and discounts
                        "breakdown" => [
                            "item_total" => [
                                "currency_code"=> $productCurr,
                                "value"=> $orderTotal, //item total contains only the product amount total * total quantity
                            ]
                        ],
                    ],
                ],
            ],
            "payment_source" => [
                "paypal" => [
                    "experience_context" => [
                        "payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED",
                        "brand_name" => "Raptorsupplies",
                        "locale" => "en-US",
                        "shipping_preference" => "NO_SHIPPING",
                        "user_action" => "PAY_NOW",
                        "return_url" => route('api.payment.paypal.payment_success'),
                        "cancel_url" => route('api.payment.paypal.payment_fail'),
                    ],
                ],
            ],
        ]);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer $this->accessToken",
            ],
            CURLOPT_POSTFIELDS => $postBody,
        ]);
            
        $result= curl_exec($curl);
        if (curl_errno($curl)) {
            throw new Exception(curl_error($curl));
        }

        $array=json_decode($result, true); 

        if(! $array) {
            throw new Exception('Empty response');
        } else if (isset($array['message'])) {
            throw new Exception($array['message']);
        }
        
        foreach($array['links'] as $value) {
            if($value['rel'] == 'payer-action') {
                return $value['href'];
            }
        }
    }

}