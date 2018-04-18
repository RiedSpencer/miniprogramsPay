<?php
/**
 * Created by PhpStorm.
 * User: spencerRao
 * Date: 2018/4/15
 * Time: 下午2:35
 * function: quick wxpay interface about wx mini programs
 * step: 1。创建订单；2。调用支付接口进行支付；3。在成功返回的函数中修改原始订单状态
 */


namespace wxPay;

class wxPay{

    private $pdo;
    private $tablename;
    private $payparam;
    /**
     * WxPay constructor.
     *
     *
     */
    public function __construct()
    {

    }

    public function test()
    {
        echo "测试成功";
    }

    /**
     * @param $host
     * @param $dbname
     * @param $username
     * @param $pass
     * @return \PDO
     * @funct connect the db
     */
    public function conndb($host,$dbname,$username,$pass)
    {
        $dsn = "mysql:host=$host;dbname=$dbname";
        $pdo = new \PDO($dsn,$username,$pass);
        $this->pdo = $pdo;
    }

    /**
     * @param $tablename
     * @param $param
     * @return mixed
     * @funct create a order
     */
    public function createOrder($tablename,$param)
    {
        $p = array_flip($param);
        $pdo = $this->pdo;
        $condition = '';
        if(count($p)){
            $condition = implode(' = ? , ',$p);
        }
        $sql = 'INSERT INTO '.$tablename.' SET '.$condition;
        $sth = $pdo->prepare($sql);
        $i = 1;
        foreach ($param as $k => $v)
        {
            $sth->bindValue($i,$v);
            ++$i;
        }
        $sth->execute();
        $oid = $pdo->lastInsertId();
        return $oid;
    }

    /**
     * @param $payparam
     * @param $param
     * @param string $tablename
     * @return array|bool
     * @throws \Exception
     * @funct 调用微信支付接口进行支付
     */
    public function wxpay($payparam,$param,$tablename)
    {

        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $payparam['orderSn'] = $this->creatSn();

        $this->payparam = $payparam;

        $xml = $this->makeOrderXml($payparam);
        $response = $this->postXml($url, $xml);
        $re_arr = $this->fromXml($response);

        if($tablename)
        {
            $this->tablename = $tablename;
            $param['orderSn'] = $payparam['orderSn'];
            $oid = $this->createOrder($tablename,$param);
        }

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


    /**
     * @funct pay notify
     */
    public function notify()
    {

        $xml = file_get_contents('php://input');
        $arr = $this->fromXml($xml);
        $response = [];
        try {
            if ($arr['return_code'] != 'SUCCESS') {
                throw new \Exception('通信失败');
            }
            $sign = $this->MakeSign($arr,$this->payparam['key']);
            if ($sign != $arr['sign']) {
                throw new \Exception('签名验证失败');
            }
            $transaction_id = $arr['transaction_id'];
            $prepay_no = $arr['out_trade_no'];
            if (!$transaction_id || !$prepay_no) {
                throw new \Exception('参数缺失');
            }
            $this->checkOrder($prepay_no,$transaction_id);

        } catch (\Exception $e) {
            $response['return_code'] = 'FAIL';
            $response['return_msg'] = $e->getMessage();
            $response_xml = $this->ToXml($response);
            echo $response_xml;
            exit;
        }
    }

    /**
     * @param $prepay_no
     * @param $transaction_id
     * @throws \Exception
     * @funct update the order state
     */
    public function checkOrder($prepay_no,$transaction_id)
    {
        $pdo = $this->pdo;

        if($this->tablename)
        {
            //根据唯一订单号寻找订单，更新订单信息和数据
            $sql = 'SELECT * FROM '.$this->tablename.' WHERE orderSn = ? ';
            $sth = $pdo->prepare($sql);
            $sth->bindValue(1,$prepay_no);
            $sth->execute();
            $res = $sth->fetch(\PDO::FETCH_ASSOC);
            if(isset($res['oid'])){
                //update the order state
                $paytime = date('Y-m-d H:i:s',time());
                $sql = "UPDATE wxorder SET state = 2 and transaction_id = ? and paytime = '$paytime'";
                $sth = $pdo->prepare($sql);
                $sth->bindValue(1,$transaction_id);
                $sth->execute();
            }else{
                throw new \Exception('订单不存在');
            }
        }

    }


    /**
     * @param $payparam
     * @return string
     * @throws \Exception
     */
    public function makeOrderXml($payparam)
    {
        $arr = [
            'appid'            => $payparam['appid'],
            'mch_id'           => $payparam['mch_id'],
            'nonce_str'        => md5(time()),
            'notify_url'       => '',
            'body'             => (isset($payparam['body']))?$payparam['body']:'about pay body',
            'detail'           => (isset($payparam['detail']))?$payparam['detail']:'about pay detail',
            'out_trade_no'     => $payparam['orderSn'],
            'total_fee'        => (isset($payparam['total_fee']))?$payparam['total_fee']:1,
            'spbill_create_ip' => '127.0.0.1',
            'trade_type'       => 'JSAPI',
            'openid'           => $payparam['openid']
        ];
        $sign = $this->MakeSign($arr,$payparam['key']);
        $arr['sign'] = $sign;
        $xml = $this->ToXml($arr);
        return $xml;
    }

    /**
     * 生成签名
     * @return $result 签名
     */
    public function MakeSign($arr,$key)
    {
        //签名步骤一：按字典序排序参数
        ksort($arr);
        $string = $this->ToUrlParams($arr);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $key;

        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }


    /**
     * @return string
     * $funct create a unique  ordersn
     */
    public function creatSn()
    {
        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $orderSn = $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) ;
        return $orderSn;
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

}