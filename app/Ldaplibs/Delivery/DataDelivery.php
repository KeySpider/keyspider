<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:01 AM
 */

namespace App\Ldaplibs\Delivery;


interface DataDelivery
{
    public function format();
    public function delivery();
}