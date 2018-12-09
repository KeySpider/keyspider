<?php

namespace App\Http\Controllers;

use App\Ldaplibs\Delivery\CSVDelivery;
use App\Ldaplibs\Delivery\DBExtractor;
use App\Ldaplibs\Import\DeliveryQueueManager;
use App\Ldaplibs\SettingsManager;

class DeliveryController extends Controller
{
    /**
     * delivery
     */
    public function delivery()
    {
        $settings = new SettingsManager();
        $file_list = $settings->get_list_of_data_extract();
        $queue = new DeliveryQueueManager();
        foreach ($file_list as $data_structure){
            $extracted_data = new DBExtractor($data_structure);
            $delivery = DeliveryFactory::get_data_delivery_from_structure($extracted_data);
            $queue->push($delivery);
        }
        $queue->process();
    }
}

class DeliveryFactory {
    public static function get_data_delivery_from_structure($extracted_data){
//        Check type here!
        return new CSVDelivery();
    }
}
