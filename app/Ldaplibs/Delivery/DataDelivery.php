<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:01 AM
 * All delivery types must implement this Interface
 */

namespace App\Ldaplibs\Delivery;


interface DataDelivery
{
    public function format();
    public function delivery();
    public function buildHistoryData(array $deliveryInformation):array ;
    public function saveToHistory(array $historyData);
}