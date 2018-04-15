<?php
/**
 * Created by PhpStorm.
 * User: spencer
 * Date: 2018/4/15
 * Time: 下午8:05
 */

include 'WxPay.php';

$wxpay = new \wxpay\WxPay();
$payparam = ['appid'=>'','mch_id'=>'','total_fee'=>1,'openid'=>'','key'=>''];
$param = ['state'=>1];
$wxpay->conndb('127.0.0.1','composer','root','');
$wxpay->wxpay($payparam,$param,'wxorder');
