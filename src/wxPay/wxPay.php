<?php
/**
 * Created by PhpStorm.
 * User: spencerRao
 * Date: 2018/4/15
 * Time: 下午2:35
 */

namespace wxPay;

use wxPay\orderPay;

class wxPay
{

    //支付参数
    private  $payparam;
    private  $order;
    private  $table;
    public function __construct()
    {

    }

    /**
     * @return string
     * 生成唯一订单号
     */
    private function guid() {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime()*10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);
            $uuid   = substr($charid, 0, 8).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);
            return $uuid;
        }
    }

    /**
     * @return string
     * $funct create a unique  ordersn
     */
    public function creatSn($order)
    {
        $orderSn = $this->guid() ;
        //去订单号查找是否有相同的订单号
        $while = $order->checkOrder($order,$orderSn);
        if($while)
            $this->creatSn($order);

        return $orderSn;
    }

    //进行微信支付接口
    /**
     * @param $payparam 支付参数
     * @param string $table 表名称
     * @param array $param 表格插入参数
     * @param array $conn 数据库连接参数
     * @return string
     * @throws \Exception
     */
    public function wxpay($payparam,$table='',$param = [],$conn = [])
    {
        $GLOBALS['payparam'] = $payparam;
        $tableparam['table'] = $table;
        $GLOBALS['table'] = $table;
        $tableparam['condition'] = $param;
        $tableparam['conn'] = $conn;
        $order = new orderPay($tableparam);
        $GLOBALS['order'] = $order;
        if(!count($payparam))
        {
            return 'please ready your payparam.';
        }
        //生成order_sn
        $order_sn = $this->creatSn($order);
        $GLOBALS['payparam']['order_sn'] = $order_sn;
        //验证为空或者参数不存在
        if(!isset($payparam['appid']) || !isset($payparam['mchid']) || !isset($payparam['openid']) || !isset($payparam['key']))
        {
            return 'lost some params';
        }

        $fee = $payparam['total_fee']??1;
        $body = $payparam['body']??'this is wxpay';
        $detail = $payparam['detail']??'';
        $notify = $payparam['notify']??'this is notify_url';

        $prearr = $this->unifiedOrder($payparam['appid'],$payparam['mchid'],$payparam['openid'],$order_sn,$fee,$body,$detail,$notify);

        $payinfo = $this->xcxSign($prearr['prepay_id']);

        //创建订单
        if($order){
            $create = $order->order($order_sn,$prearr['prepay_id']);//创建
            if(count($create)>1)
                $oid = $create['oid'];
            else
                echo $create;
        }
        echo json_encode(['prepay'=>$prearr,'payinfo'=>$payinfo]);
    }


    public static function unifiedOrder($app_id,$mch_id,$openid, $order_no, $last_price, $body='',$detail='', $notify='')
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $wx_order = new static();
        $xml = $wx_order->makeOrderXml($app_id,$mch_id,$openid, $order_no, $last_price, $body,$detail, $notify);
        $response = $wx_order->postXml($url, $xml);
        $re_arr = $wx_order->fromXml($response);
        if ($re_arr['return_code'] == 'FAIL') {
            throw new \Exception($re_arr['return_msg']);
        }
        if (isset($re_arr['err_code'])) {
            throw new \Exception($re_arr['err_code_des']);
        }
        if ($re_arr['return_code'] != 'SUCCESS' || $re_arr['result_code'] != 'SUCCESS') {
            throw new \Exception('微信统一下单失败');
        }
        return $re_arr;
    }

    public static function xcxSign($prepay_id)
    {
        $xcx_order = new static();
        $arr = [
            'appId'     => $GLOBALS['payparam']['appid'],
            'nonceStr'  => md5(time()),
            'package'   => 'prepay_id=' . $prepay_id,
            'signType'  => 'MD5',
            'timeStamp' => time(),
        ];
        $arr['paySign'] = $xcx_order->MakeSign($arr);
        return $arr;
    }

    public static function closeOrder($order_no)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/closeorder';
        $wx_order = new static();
        $arr = [
            'appid'        => $GLOBALS['payparam']['appid'],
            'mch_id'       => $GLOBALS['payparam']['mch_id'],
            'out_trade_no' => $order_no,
            'nonce_str'    => md5(time()),
        ];
        $arr['sign'] = $wx_order->MakeSign($arr);
        $xml = $wx_order->ToXml($arr);
        $response = $wx_order->postXml($url, $xml);
        $re_arr = $wx_order->fromXml($response);
        if ($re_arr['return_code'] == 'FAIL') {
            throw new \Exception($re_arr['return_msg']);
        }
        if (isset($re_arr['err_code'])) {
            throw new \Exception($re_arr['err_code_des']);
        }
        if ($re_arr['return_code'] != 'SUCCESS' || $re_arr['result_code'] != 'SUCCESS') {
            throw new \Exception('微信统一关单失败');
        }
        return true;
    }


    public function makeOrderXml($app_id,$mch_id,$openid, $order_no, $last_price, $body,$detail, $notify)
    {
        $arr = [
            'appid'            => $app_id,
            'mch_id'           => $mch_id,
            'nonce_str'        => md5(time()),
            'notify_url'       => ($notify)??'',
            'body'             => ($body)??"  ",
            'detail'           => ($detail)??'',
            'out_trade_no'     => $order_no,
            'total_fee'        => $last_price,
            'spbill_create_ip' => '127.0.0.1',
            'trade_type'       => 'JSAPI',
            'openid'           => $openid
        ];
        $sign = $this->MakeSign($arr);
        $arr['sign'] = $sign;
        $xml = $this->ToXml($arr);
        return $xml;
    }


    public function postXml($url, $xml)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, false);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw  new \Exception('curl出错');
        }
    }

    /**
     * 生成签名
     * @return $result 签名
     */
    public function MakeSign($arr)
    {
        $wx_order = new static();
        
        //签名步骤一：按字典序排序参数
        ksort($arr);
        $string = $wx_order->ToUrlParams($arr);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $GLOBALS['payparam']['key'];
    
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        
        return $result;
    }

    /**
     * 格式化参数格式化成url参数
     */
    public function ToUrlParams($arr)
    {
        $buff = "";
        foreach ($arr as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 输出xml字符
     **/
    public function ToXml($arr)
    {
        if (!is_array($arr) || count($arr) <= 0) {
            throw new \Exception("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @return array|bool
     */
    public function fromXml($xml)
    {
        if (!$xml) {
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    /**
     * 查询订单
     * @param $transaction_id
     * @return array|bool
     * @author Lejianwen
     */
    public function queryOrder($transaction_id, $trade_sn = '')
    {
        $arr = [
            'appid'          => $GLOBALS['payparam']['appid'],
            'mch_id'         => $GLOBALS['payparam']['mchid'],
            'nonce_str'      => md5(time()),
            'sign_type'      => 'MD5'
        ];
        if ($trade_sn !== '') {
            $arr = array_merge($arr, ['out_trade_no' => $trade_sn]);
        } else {
            $arr = array_merge($arr, ['transaction_id' => $transaction_id]);
        }
        $arr['sign'] = $this->MakeSign($arr);
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        $xml = $this->ToXml($arr);
        $response = $this->postXml($url, $xml);
        $re_arr = $this->fromXml($response);
        if ($re_arr['return_code'] == 'SUCCESS' && $re_arr['result_code'] == 'SUCCESS') {
            return $re_arr;
        }
        return false;
    }


    /**
     * 支付之后回调函数
     */
    public function notify(){
        $xml = file_get_contents('php://input');
        $order = new static();
        $arr = $order->fromXml($xml);
        $response = [];
        $errmsg = '支付成功';
        try {
            if ($arr['return_code'] != 'SUCCESS') {
                $errmsg = '通信失败';
            }
            $sign = $order->MakeSign($arr);
            if ($sign != $arr['sign']) {
                $errmsg = '签名验证失败';
            }
            $transaction_id = $arr['transaction_id'];
            $prepay_no = $arr['out_trade_no'];
            if (!$transaction_id || !$prepay_no) {
                $errmsg = '参数缺失';
            }

            echo json_encode(['trade_sn'=>$prepay_no,'msg'=>$errmsg]);

        } catch (\Exception $e) {
            $response['return_code'] = 'FAIL';
            $response['return_msg'] = $e->getMessage();
            $response_xml = $order->ToXml($response);
            echo $response_xml;
            exit;
        }
    }


}