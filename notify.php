<?php
define('WP_USE_THEMES', false);
$root_path =rtrim($_SERVER['DOCUMENT_ROOT'],'/');
if(file_exists($root_path.'/wp-load.php')){
    require_once $root_path.'/wp-load.php';
}else if(file_exists('../../../wp-load.php')){
    require_once     '../../../wp-load.php';
}else{
    echo '无法加载wp-load.php';
    exit;
}
ob_clean();
global $XH_Alipay_Payment_WC_Payment_Gateway;
$data = $_POST;

if(!isset($data['hash'])
    ||!isset($data['trade_order_id'])){
    echo 'invalid sign';
    exit;
}
$appkey =$XH_Alipay_Payment_WC_Payment_Gateway->get_option('appsecret');
$hash =$XH_Alipay_Payment_WC_Payment_Gateway->generate_xh_hash($data,$appkey);
if($data['hash']!=$hash){
    echo 'invalid sign';
    exit;
}

$order = new WC_Order($data['trade_order_id']);
try{
    if(!$order){
        throw new Exception('Unknow Order (id:'.$data['trade_order_id'].')');
    }
    
    if($order->needs_payment()&&$data['status']=='OD'){
        $order->payment_complete(isset($data['transacton_id'])?$data['transacton_id']:'');
    }
}catch(Exception $e){
    //looger
    $logger = new WC_Logger();
	$logger->add( 'xh_wedchat_payment', $e->getMessage() );
	echo "errcode:{$e->getCode()},errmsg:{$e->getMessage()}";
    exit;
}

$params = array(
        'action'=>'success',
        'appid'=>$XH_Alipay_Payment_WC_Payment_Gateway->get_option('appid')
);
$params['hash']=$XH_Alipay_Payment_WC_Payment_Gateway->generate_xh_hash($params, $appkey);
print json_encode($params);
exit;


