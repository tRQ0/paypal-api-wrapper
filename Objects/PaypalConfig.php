<?php 

namespace App\Services\PaypalService\Objects;

class PaypalConfig
{
    public function __construct($values = [])
    {
        foreach($values as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }
}