<?php

namespace App\Services\PaypalService\Factories;

use App\Services\PaypalService\Objects\PaypalConfig;

interface AbstractPaypalFactory 
{
    public function objectFactory($objectName, $values);
}

class DefaultFactory implements AbstractPaypalFactory
{    
    public function objectFactory($objectName, $values) {
        return new PaypalConfig($values);
    }

}
