<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly
class XH_Alipay_Payment_WC_Payment_Gateway extends WC_Payment_Gateway {
    private $instructions;
	public function __construct() {
		$this->id                 = XH_Alipay_Payment_ID;
		$this->icon               = XH_Alipay_Payment_URL . '/images/logo/alipay.png';
		$this->has_fields         = false;
		
		$this->method_title       = __('Alipay Payment',XH_Alipay_Payment);
		$this->method_description = __('Helps to add Alipay payment gateway that supports the features including QR code payment.',XH_Alipay_Payment);
		
		$this->title              = $this->get_option ( 'title' );
		$this->description        = $this->get_option ( 'description' );
		$this->instructions       = $this->get_option('instructions');
		
		$this->init_form_fields ();
		$this->init_settings ();
		
		$this->enabled            = $this->get_option ( 'enabled' );
		if($this->enabled=='yes'){
		    if($this->is_wechat_app()&&$this->get_option('disabled_in_wechat')=='yes'){
		          $this->enabled='no';
		    }
		}
		
		add_filter ( 'woocommerce_payment_gateways', array($this,'woocommerce_add_gateway') );
		add_action ( 'woocommerce_update_options_payment_gateways_' .$this->id, array ($this,'process_admin_options') );
		add_action ( 'woocommerce_update_options_payment_gateways', array ($this,'process_admin_options') );
		add_action ( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action ( 'woocommerce_thankyou_'.$this->id, array( $this, 'thankyou_page' ) );
	}
	
	public function woocommerce_add_gateway($methods) {
	    $methods [] = $this;
	    return $methods;
	}
	
	public function process_payment($order_id) {
		$order            = new WC_Order ( $order_id );
		if(!$order||!$order->needs_payment()){
		    return array (
		        'result' => 'success',
		        'redirect' => $this->get_return_url($order)
		    );
		}
		
		$expire_rate      = floatval($this->get_option('exchange_rate',1));
		if($expire_rate<=0){
		    $expire_rate=1;
		}
		
		$total_amount     = round($order->get_total()*$expire_rate,2);		
		$data=array(
		      'version'   => '1.0',//api version
		      'lang'       => get_option('WPLANG','zh-cn'),
		      'is_app'    => $this->isWebApp()?'Y':'N',
		      'plugins'   => $this->id,
		      'appid'     => $this->get_option('appid'),
		      'trade_order_id'=> $order_id,
		      'payment'   => 'alipay',
		      'total_fee' => $total_amount,
		      'title'     => $this->get_order_title($order),
		      'description'=> $this->get_order_desc($order),
		      'time'      => time(),
		      'notify_url'=> get_option('siteurl'),
		      'return_url'=> $this->get_return_url($order),
		      'callback_url'=>wc_get_checkout_url(),
		      'nonce_str' => str_shuffle(time())
		);
		
		$hashkey          = $this->get_option('appsecret');
		$data['hash']     = $this->generate_xh_hash($data,$hashkey);
		$url              = $this->get_option('transaction_url').'/payment/do.html';
		
		try {
		    $response     = $this->http_post($url, json_encode($data));
		    $result       = $response?json_decode($response,true):null;
		    if(!$result){
		        throw new Exception('Internal server error',500);
		    }
		     
		    $hash         = $this->generate_xh_hash($result,$hashkey);
		    if(!isset( $result['hash'])|| $hash!=$result['hash']){
		        throw new Exception(__('Invalid sign!',XH_Alipay_Payment),40029);
		    }
		    
		    if($result['errcode']!=0){
		        throw new Exception($result['errmsg'],$result['errcode']);
		    }
		    
		    return array(
		        'result'  => 'success',
		        'redirect'=> $result['url']
		    );
		} catch (Exception $e) {
		    wc_add_notice("errcode:{$e->getCode()},errmsg:{$e->getMessage()}",'error');
		    return array(
		        'result' => 'fail',
		        'redirect' => $this->get_return_url($order)
		    );
		}
	}
	public  function isWebApp(){
	    if(!isset($_SERVER['HTTP_USER_AGENT'])){
	        return false;
	    }
	
	    $u=strtolower($_SERVER['HTTP_USER_AGENT']);
	    if($u==null||strlen($u)==0){
	        return false;
	    }
	
	    preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/',$u,$res);
	
	    if($res&&count($res)>0){
	        return true;
	    }
	
	    if(strlen($u)<4){
	        return false;
	    }
	
	    preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/',substr($u,0,4),$res);
	    if($res&&count($res)>0){
	        return true;
	    }
	
	    $ipadchar = "/(ipad|ipad2)/i";
	    preg_match($ipadchar,$u,$res);
	    return $res&&count($res)>0;
	}
	private function http_post($url,$data){
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	    curl_setopt($ch,CURLOPT_URL, $url);
	    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
	    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
	    curl_setopt($ch,CURLOPT_REFERER,get_option('siteurl'));
	    curl_setopt($ch, CURLOPT_HEADER, FALSE);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	    curl_setopt($ch, CURLOPT_POST, TRUE);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	    $response = curl_exec($ch);
	    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    curl_close($ch);
	    if($httpStatusCode!=200){
	        throw new Exception($response,$httpStatusCode);
	    }
	    
	    return $response;
	}
	
	public function generate_xh_hash(array $datas,$hashkey){
	    ksort($datas);
	    reset($datas);
	    
	    $pre =array();
	    foreach ($datas as $key => $data){
	        if($key=='hash'){
	            continue;
	        }
	        $pre[$key]=$data;
	    }
	    
	    $arg  = '';
	    $qty = count($pre);
	    $index=0;
	    
	    foreach ($pre as $key=>$val){
	        $arg.="$key=$val";
	        if($index++<($qty-1)){
	            $arg.="&";
	        }
	    }
	    
	    if(get_magic_quotes_gpc()){
	        $arg = stripslashes($arg);
	    }
	     
	    return md5($arg.$hashkey);
	}
	
	private function is_alipay_app(){
	    return strripos($_SERVER['HTTP_USER_AGENT'],'micromessenger');
	}
	
	public function thankyou_page() {
	    if ( $this->instructions ) {
	        echo wpautop( wptexturize( $this->instructions ) );
	    }
	}
	
	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
	    if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method ) {
	        echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
	    }
	}
	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	function init_form_fields() {
		$this->form_fields = array (
				'enabled' => array (
						'title'       => __('Enable/Disable',XH_Alipay_Payment),
						'type'        => 'checkbox',
						'label'       => __('Enable/Disable the alipay payment',XH_Alipay_Payment),
						'default'     => 'no',
						'section'     => 'default'
				),
				'title' => array (
						'title'       => __('Payment gateway title',XH_Alipay_Payment),
						'type'        => 'text',
						'default'     =>  __('Alipay Payment',XH_Alipay_Payment),
						'desc_tip'    => true,
						'css'         => 'width:400px',
						'section'     => 'default'
				),
				'description' => array (
						'title'       => __('Payment gateway description',XH_Alipay_Payment),
						'type'        => 'textarea',
						'default'     => __('QR code payment or OA native payment, credit card',XH_Alipay_Payment),
						'desc_tip'    => true,
						'css'         => 'width:400px',
						'section'     => 'default'
				),
				'instructions' => array(
    					'title'       => __( 'Instructions', XH_Alipay_Payment ),
    					'type'        => 'textarea',
    					'css'         => 'width:400px',
    					'description' => __( 'Instructions that will be added to the thank you page.', XH_Alipay_Payment ),
    					'default'     => '',
    					'section'     => 'default'
				),
				'appid' => array(
    					'title'       => __( 'APP ID', XH_Alipay_Payment ),
    					'type'        => 'text',
    					'css'         => 'width:400px',
    					'default'     => '',
    					'section'     => 'default',
                        'description' =>__('<a target="_blank" href="http://mp.wordpressopen.com">register and get app id</a>.', XH_Alipay_Payment )
				),
				'appsecret' => array(
    					'title'       => __( 'APP Secret', XH_Alipay_Payment ),
    					'type'        => 'text',
    					'css'         => 'width:400px',
    					'default'     => '',
    					'section'     => 'default',
                        'description' =>__('<a target="_blank" href="http://mp.wordpressopen.com">register and get app secret</a>.', XH_Alipay_Payment )
				),
				'transaction_url' => array(
    					'title'       => __( 'Transaction Url', XH_Alipay_Payment ),
    					'type'        => 'text',
    					'css'         => 'width:400px',
    					'default'     => 'https://pay.wordpressopen.com',
    					'section'     => 'default'
				),
				'exchange_rate' => array (
    					'title'       => __( 'Exchange Rate',XH_Alipay_Payment),
    					'type'        => 'text',
    					'default'     => '1',
    					'description' => __(  'Set the exchange rate to RMB. When it is RMB, the default is 1',XH_Alipay_Payment),
    					'css'         => 'width:400px;',
    					'section'     => 'default'
				),
    		    'disabled_in_wechat' => array (
    		        'title'       => __('Disabled In Wechat',XH_Alipay_Payment),
    		        'type'        => 'checkbox',
    		        'default'     => 'no',
    		        'section'     => 'default'
    		    )
		);
	}
	
	private function is_wechat_app(){
	    return strripos($_SERVER['HTTP_USER_AGENT'],'micromessenger');
	} 
	
	public function get_order_title($order, $limit = 98) {
		$title ="#{$order->id}";
		
		$order_items = $order->get_items();
		if($order_items){
		    $qty = count($order_items);
		    foreach ($order_items as $item_id =>$item){
		        $title.="|{$item['name']}";
		        break;
		    }
		    if($qty>1){
		        $title.='...';
		    }
		}
		
		$title = mb_strimwidth($title, 0, $limit);
		return apply_filters('xh-payment-get-order-title', $title,$order);
	}
	
	/**
	 * 
	 * @param WC_Order $order
	 * @param number $limit
	 * @param string $trimmarker
	 */
	public function get_order_desc($order) {
	    $descs=array();
	    
	    $order_items = $order->get_items();
	    if($order_items){
	        $qty = count($order_items);
	        $index=0;
    	    foreach ($order_items as $item_id =>$item){
    	       $result =array(
    	              'order_item_id'=>$item_id
    	       );
    	        
    	        if($item['item_meta_array']){
    	           foreach ($item['item_meta_array'] as $key_id=>$meta){
    	               if($meta->key=='_qty'){
    	                   $result['qty']=$meta->value;
    	                   continue;
    	               }
    	               
    	               if($meta->key=='_product_id'){
    	                   $result['product_id']=$meta->value;
    	                   continue;
    	               }
    	           } 
    	        }
    	   
    	        if(isset( $result['product_id'])){
    	            $product = new WC_Product($result['product_id']);
    	            if($product){
    	                //获取图片
    	                $imgsrc ='';
    	                $img_id = $product->get_image_id();
    	                if($img_id>0){
    	                    $img = get_post($img_id);
    	                    if($img){
    	                        $imgsrc=$img->guid;
    	                    }
    	                }
    	                
    	                $desc=array(
    	                       'id'=>$result['product_id'],
    	                       'order_qty'=>$result['qty'],
    	                       'order_item_id'=>$result['order_item_id'],
    	                       'url'=>$product->get_permalink(),
    	                       'sale_price'=>$product->get_sale_price(),
    	                       'image'=>$imgsrc,
    	                       'title'=>$product->get_title(),
    	                       'sku'=>$product->get_sku(),
    	                       'summary'=>$product->post->post_excerpt,
    	                       'content'=>$product->post->post_content
    	                );
    	            }
    	        }
    	        
    	       $descs[]=$desc;
            }
	    }
	   
	    return apply_filters('xh-payment-get-order-desc', json_encode($descs),$order);
	}
}

?>
