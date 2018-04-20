# miniprogramsPay

description:quick create a wxpay interface.


needs: php >= 7.0 ,db:mysql

how to use:

require 'vendor/autoload.php';


use wxPay\wxPay; 


$wxPay = new wxPay();

//about payinfo


$payparam = [
    'appid'=>'',//小程序的appid


    'mchid'=>'',//小程序的商户号


    'openid'=>'',//支付用户的openid


    'key'=>'',//商户平台的key


   /* 'body'=>'',//支付信息


    'notify'=>''//需要将notify 回调地址加在微信支付的配置里面，不然无法进行回调*/


];

//if you want to record the payinfo


$table = '';//表名称 



$param = [


    'create_time'=>date('Y-m-d H:i:s',time())//表插入的参数


];



//需要在订单表格里面加上关键信息字段如下：


//oid为表单主键，state为支付状态(默认为1 未支付)，orderSn为商家自生成唯一订单号，trade_sn为预支付订单号，paytime为支付时间  


//其余字段  自定义即可



//数据库连接信息


$conn = [


    'host'=>'',


    'dbname'=>'',


    'username'=>'',


    'pass'=>''


];



$wxpay = new wxPay();


$wxpay->wxpay($payparam,$table,$param,$conn);


//返回json信息


{"payinfo":{"appId":"","nonceStr":"","package":"","signType":"","timeStamp":,"paySign":""}}


//说明统一下单成功

