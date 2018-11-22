<?php

namespace App\Http\Controllers;

use App\Ldaplibs\Delivery\CSVDelivery;
use App\Ldaplibs\Import\DeliveryQueueManager;
use App\Ldaplibs\SettingsManager;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function delivery(){
        $settings = new SettingsManager();
        $file_list = $settings->get_list_of_data_extract();
        $queue = new DeliveryQueueManager();
        foreach ($file_list as $data_structure){
//           data_structure: data extracted + type of Delivery.
            $delivery = DeliveryFactory::get_data_delivery_from_structure($data_structure);
            $queue->push($delivery);
        }
        $queue->process();
    }
}

class DeliveryFactory{
    public static function get_data_delivery_from_structure($data_structure){
//        Check type here!
        return new CSVDelivery();
    }
}