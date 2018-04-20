<?php
/**
 * Created by PhpStorm.
 * User: spencerRao
 * Date: 2018/4/20
 * Time: 8:43
 */
namespace wxPay;

class orderPay
{
    private $param;
    private $pdo;
    public function __construct($param)
    {
        $this->param = $param;
        //连接数据库 创建订单模型
        $this->conndb($param['conn']);
    }

    //进行order表的输入
    public function order($ordersn,$trade_sn)
    {
        $param = $this->param['condition'];
        $table = $this->param['table'];
        $param['orderSn'] = $ordersn;
        $param['trade_sn'] = $trade_sn;
        $param['state'] = 1;//1、未支付  2、已支付 3、支付失败
        if(!count($param) || !$table)
        {
            return 'please ready the param';
        }
        $rowcount = 0;
        $pdo = $this->pdo;
        $condition = $this->pack($param);
        //进行insert操作
        $sql = 'INSERT INTO '.$table.' SET '.$condition;
        $sth = $pdo->prepare($sql);
        $sth = $this->bind($sth,$param);
        $sth->execute();
        $oid = $pdo->lastInsertId();
        $rowcount = $sth->rowCount();
        return ['affected'=>$rowcount,'oid'=>$oid];
    }

    /**
     * 连接数据库
     * @param $connparam
     */
    public function conndb($connparam){
        $host = $connparam['host']??0;
        $dbname = $connparam['dbname']??0;
        $username = $connparam['username']??0;
        $pass = $connparam['pass']??0;
        $dsn = "mysql:host=$host;dbname=$dbname";
        try{
            $this->pdo = new \PDO($dsn,$username,$pass);
        }catch(\Exception $e){
            echo "连接数据库失败";exit;
        }

    }

    /**
     * @param $tablename
     * @param $param
     * @return int
     * @funct judge the user exist
     */
    public function judge($tablename,$param)
    {
        //得到数据表字段
        $where = $this->pack($param,'where');
        $pdo = $this->pdo;
        $sth = $pdo->prepare('SELECT oid from '.$tablename.' where '.$where);
        $sth = $this->bind($sth,$param);
        $sth->execute();
        $res = $sth->fetch(\PDO::FETCH_ASSOC);
        return $res['oid'];
    }


    /**
     * @param $param
     * @return string
     * @funct package the param
     */
    public function pack($param,$type='')
    {
        $p = array_flip($param);
        $where = '';

        if(count($p))
        {
            $state = ($type == 'where')?implode(' = ? AND ',$p):implode(' = ? , ',$p);
            $where = $state." = ?";
        }
        return $where;
    }

    /**
     * @param $sth
     * @param $param
     * @return mixed
     * @funct bind the value
     */
    public function bind($sth,$param)
    {
        $i = 1;
        foreach ($param as $k => $v)
        {
            $sth->bindValue($i,$v);
            ++$i;
        }
        return $sth;
    }


    /**
     * @param $order 订单模型
     * @param $orderSn 订单号
     * @return int
     */
    public function checkOrder($order,$orderSn)
    {
        $talename = $order->param['table'];
        $param = ['orderSn'=>$orderSn];
        $oid = $this->judge($talename,$param);
        return $oid;
    }

}