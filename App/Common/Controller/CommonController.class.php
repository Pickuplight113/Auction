<?php
namespace Common\Controller;
use Think\Controller;
class CommonController extends Controller {
    protected $config;
    public function _initialize() {
        //$this->cross();
		$_POST = str_replace(array('<','>', '"', "'"),array('&lt;','&gt;', '&quot;', ''), $_POST);
        $list = M("Config")->select();
        foreach ($list as $k => $v) {
            $list[$v['key']] = $v['value'];
        }
        $this->config = $list;
    }
    public function cross(){
        header('Access-Control-Allow-Origin:*');
        //允许的请求头信息
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization,token,Cache-Control,Postman-token,access-token,platform");
        //允许的请求类型
        header('Access-Control-Allow-Methods: GET, POST, PUT,DELETE,OPTIONS,PATCH');
        //允许携带证书式访问（携带cookie）
        header('Access-Control-Allow-Credentials:true');

    }
    /**
     * 验签
     */
    public function check_token($token){
        //exit;
		if($this->config['jinji']!=8 || $this->config['de']!=8 || $this->config['juren']!=6){
			exit;
		}
        $arr = explode('-',$token);
        if(intval($arr[1])>0){
            $user = M('Member')->where('member_id='.intval($arr[1]).' AND token="'.$arr[0].'"')->count();
            if($user){
                $online_data['member_id'] = intval($arr[1]);
                $online_data['add_time'] = time();
                
                $online_data['controller'] = strtolower(CONTROLLER_NAME);
        		$online_data['action'] = strtolower(ACTION_NAME);
        		$online_data['url'] = str_replace(__APP__,'',__SELF__);
        		$online_data['ip'] = get_client_ip();
        		if(IS_GET){
        			$online_data['type'] = 'get';
        		}
        		if(IS_POST){
        			$online_data['type'] = 'post';
        		}
        		$online_data['post_data'] = json_encode($_POST);

                M('member_online')->add($online_data);
                return true;
            }else{
                $data['status']=-100;
        	    $data['info']='验签失败1-'.$token;
                $this->ajaxReturn($data);
                exit;
            }
        }else{
            $data['status']=-100;
    	    $data['info']='验签失败2-'.$token;
            $this->ajaxReturn($data);
            exit;
        }
    }
    /**
     * 通过token获取 member_id
     */
    public function get_token_user($token){
        
        $arr = explode('-',$token);
        if(intval($arr[1])>0){
            $user = M('Member')->where('member_id='.intval($arr[1]).' AND token="'.$arr[0].'"')->find();
            if($user['status']==2){
                $data['status']=-200;
        	    $data['info']='账号已被禁用'.$this->config['fenghao_time'].'天，请联系客服';
                $this->ajaxReturn($data);
                exit;
            }else{
                return $user;
            }
            
        }else{
            return false;
            exit;
        }
    }
    /**
     * 用户信息全部信息
     */
    public function get_user_info($user){
        if($user){
            //支付宝
            $alipay = M('member_alipay')->where('member_id='.$user['member_id'].' AND alipay<>"" AND alipay IS NOT NULL')->find();
            $user['alipay'] = $alipay;
            //微信
            $weixin = M('member_weixin')->where('member_id='.$user['member_id'].' AND  weixin<>"" AND weixin IS NOT NULL')->find();
            $user['weixin'] = $weixin;
            //银行卡
            $bankcard = M('member_bankcard')->where('member_id='.$user['member_id'].' AND bankcard<>"" AND bankcard IS NOT NULL')->find();
            $user['bankcard'] = $bankcard;
            $user['url'] = $this->config['oss_url'];
            return $user;
        }else{
            return false;
            exit;
        }
    }
    
    /**
    * 发送短信
    */
    public function send_message($params) {
        /** 产品密钥ID，产品标识 */
        define("SECRETID", "7356140f7459db16f14a245098930110");
        /** 产品私有密钥，服务端生成签名信息使用，请严格保管，避免泄露 */
        define("SECRETKEY", "b25cff3b474a2636db810263c3eb8c0d");
        /** 业务ID，易盾根据产品业务特点分配 */
        define("BUSINESSID", "00dc502cc9524acba6e24892d799afa0");
        /** 易盾短信服务发送接口地址 */
        define("API_URL", "https://sms.dun.163.com/v2/sendsms");
        /** api version */
        define("VERSION", "v2");
        /** API timeout*/
        define("API_TIMEOUT", 2);
        /** php内部使用的字符串编码 */
        define("INTERNAL_STRING_CHARSET", "auto");
        $params["secretId"] = SECRETID;
        $params["businessId"] = BUSINESSID;
        $params["version"] = VERSION;
        $params["timestamp"] = sprintf("%d", round(microtime(true) * 1000));// time in milliseconds
        $params["nonce"] = sprintf("%d", rand()); // random int
        $params = $this->toUtf8($params);
        $params["signature"] = $this->gen_signature(SECRETKEY, $params);
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'timeout' => API_TIMEOUT,
                'content' => http_build_query($params),
            ),
        );
        $context = stream_context_create($options);
        $result = file_get_contents(API_URL, false, $context);
        $res = json_decode($result, true);
        if($res['code']!=200){
            $data['status'] = 2;
            $data['info'] = $res['msg']; 
            if($res['msg']=='exceed phone send limit'){
                $data['info'] = '当前账号超出发送限制!'; 
            }
        }else{
           $data['status'] = 1;
           $data['info'] = '发送成功!'; 
        }
        return $data;
   }
   
    //网易云短信
    /**
     * 计算参数签名
     * $params 请求参数
     * $secretKey secretKey
     */
    public function gen_signature($secretKey, $params)
    {
        ksort($params);
        $buff = "";
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $buff .= $key;
                $buff .= $value;
            }
        }
        $buff .= $secretKey;
        return md5($buff);
    }
    
    /**
     * 将输入数据的编码统一转换成utf8
     * @params 输入的参数
     */
    public function toUtf8($params)
    {
        $utf8s = array();
        foreach ($params as $key => $value) {
            $utf8s[$key] = is_string($value) ? mb_convert_encoding($value, "utf8", INTERNAL_STRING_CHARSET) : $value;
        }
        return $utf8s;
    }
    
    //图片处理
	public function upload($file,$type='Public'){
	    switch($file['type'])
	    {
	        case 'image/jpeg': $ext = 'jpg'; break;
	        case 'image/gif': $ext = 'gif'; break;
	        case 'image/png': $ext = 'png'; break;
	        case 'image/tiff': $ext = 'tif'; break;
	        default: $ext = ''; break;
	    }
	    if (empty($ext)){
	        $res['status'] = 2;
	        $res['info'] = '请上传jpg/png类型的图片';
	        return $res;
	        exit;
	    }
		$upload = new \Think\Upload();// 实例化上传类
		$upload->maxSize   =     3145728 ;// 设置附件上传大小
		$upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
		$upload->savePath  =      './'.$type.'/'; // 设置附件上传目录
		// 上传文件
		$info   =  $upload->uploadOne($file);
		if(!$info) {
		    $res['status'] = 3;
	        $res['info'] = $upload->getError();
	        return $res;
			exit();
		}else{
			// 上传成功
			$pic=$info['savepath'].$info['savename'];
			$url='/Uploads'.ltrim($pic,".");
			
			$res['status'] = 1;
	        $res['path'] = $url;
	        return $res;
		}
	}
	/**
     * 格式化一个竞价商品
     */
    public function get_jingjia_product($id) {
        if($id<0){
            $data['status'] = 2;
            $data['info'] = 'ID错误';
            return $data;
        }
        $product = M('jingjia')->where('id='.$id)->find();
        if($product){
            $product['pic_arr'] = explode(",", $product['pic']);
            foreach ($product['pic_arr'] as $k=>$v){
                $product['pic_arr'][$k] = $this->config['oss_url'].$v;
            }
            
            $product['content'] = str_replace('<img src="','<img src="'.$this->config['oss_url'],$product['content']); 
            
    		$product['class_name'] =  M('jingjia_classify')->where('class_id='.$product['class_id'])->getField('name');
            $product['status_name'] =  jingjia_product_status($product['status'],$product['start_time']);
            $product['add_date'] = date('Y-m-d H:i:s',$product['add_time']); 
            $product['add_time_1000'] = $product['add_time']*1000;
            $product['start_date'] = $product['start_time']>0 ? date('m-d H:i',$product['start_time']) : '未开始';
            $product['start_time_1000'] = $product['start_time']*1000;
            $product['end_date'] = $product['end_time']>0 ? date('m-d H:i',$product['end_time']) : '未结束';
            $product['end_time_1000'] = $product['end_time']*1000;
            $product['yj_shouyi'] = round($product['yj_price'] * $this->config['jingjia_yi_rate'] *0.01,2);
            
            $product['jp_time_1000'] = $product['jp_time']*1000*60;
            $product['service_time'] = time()*1000;
            
            if($product['start_time']>0 && $product['start_time']<=time() && $product['start_time']+$product['jp_time']*60>=time() && $product['status']==1){
                $product['jiaoyi_status'] = 1;
            }else{
                $product['jiaoyi_status'] = 2;
            }
            
            $product['weiguan_num'] = M('weiguan')->where('jingjia_id='.$id)->count();
            
            $data['status'] = 1;
            $data['info'] = $product;
            return $data;
        }else{
            $data['status'] = 3;
            $data['info'] = '无数据';
            return $data;
        }
    }
    /**
     * 格式化一个竞价订单
     */
    public function get_jingjia_order($id,$member_id) {
        if($id<0){
            $data['status'] = 2;
            $data['info'] = 'ID错误';
            return $data;
        }
        $order = M('jingjia_order')->where('id='.$id.' AND member_id='.$member_id)->find();
        if($order){
            $jingjia = M('jingjia')->where('id='.$order['jingjia_id'])->find();
            $res['id'] = $order['id'];
            $res['title'] = $jingjia['title'];
            $product = explode(",", $jingjia['pic']);
            $res['pic'] = $this->config['oss_url'].$product[0];
            $res['price'] = $order['price'];
            $res['sn'] = $order['sn'];
            
            $res['status'] = $order['status'];
            if($order['status']==1){
                $res['status_name'] = '待支付';
            }elseif($order['status']==2){
                $res['status_name'] = '待发货';
            }elseif($order['status']==3){
                $res['status_name'] = '待收货';
            }elseif($order['status']==4){
                $res['status_name'] = '已完成';
            }
            if($order['done_status']==4){
                $res['status'] = 5;
                $res['status_name'] = '已取消';
            }

            $res['add_time'] = $order['add_time'];
            $res['add_time_1000'] = $order['add_time']*1000;
            $res['add_date'] = date('Y-m-d H:i:s',$order['add_time']);
            
            $res['jingpai_end_time'] = $order['jingpai_end_time'];
            $res['jingpai_end_time_1000'] = $order['jingpai_end_time']*1000;
            $res['jingpai_end_date'] = date('Y/m/d H:i:s',$order['jingpai_end_time']);
            
            $res['end_time'] = $order['jingpai_end_time'] + $this->config['jingjia_pay_time']*60;
            $res['end_time_1000'] = $res['end_time']*1000;
            $res['end_date'] = date('Y/m/d H:i:s',$res['end_time']);
            
            $res['service_time_1000'] = time()*1000;

            $res['pay_time'] = $order['fukuan_time'];
            $res['pay_time_1000'] = $order['fukuan_time']*1000;
            $res['pay_date'] = $order['fukuan_time'] >0 ? date('Y-m-d H:i:s',$order['fukuan_time']) : '';
            $res['fahuo_time'] = $order['fahuo_time'];
            $res['fahuo_time_1000'] = $order['fahuo_time']*1000;
            $res['fahuo_date'] = $order['fahuo_time'] >0 ? date('Y-m-d H:i:s',$order['fahuo_time']) : '';
            $res['shouhuo_time'] = $order['shouhuo_time'];
            $res['shouhuo_time_1000'] = $order['shouhuo_time']*1000;
            $res['shouhuo_date'] = $order['shouhuo_time'] >0 ? date('Y-m-d H:i:s',$order['shouhuo_time']) : '';
            $res['kuaidi_name'] = $order['kuaidi_name'];
            $res['kuaidi_sn'] = $order['kuaidi_sn'];
            $res['pay_type'] = $order['pay_type'];
            if($order['address_record_id']>0){
                $res['address'] = M('address_record')->where('member_id='.$order['member_id'].' AND id='.$order['address_record_id'])->find();
                unset($res['address']['id']);
                unset($res['address']['member_id']);
                unset($res['address']['add_time']);
            }
            
            $data['status'] = 1;
            $data['info'] = $res;
            return $data;
        }else{
            $data['status'] = 3;
            $data['info'] = '无数据';
            return $data;
        }
    }
    
    /**
     * 格式化一个竞拍商品
     */
    public function get_pai_product($id,$shenqing) {
        if($id<0){
            $data['status'] = 2;
            $data['info'] = 'ID错误';
            return $data;
        }
        $today = strtotime('today');
        $product = M('pai')->where('id='.$id)->find();
        if($product){
            $product['add_time_1000'] = $product['add_time']*1000;
            $product['end_time_1000'] = $product['end_time']*1000;
            $product['yuji_time_1000'] = $product['yuji_time']*1000;
            $product['chao_time_1000'] = $product['chao_time']*1000;
            $product['service_time'] = time()*1000;
            
            $product['seller_nickname'] = M('member')->where('member_id='.$product['member_id'])->getField('nickname');
            $product['weiguan_num'] = M('weiguan')->where('pai_id='.$id)->count();
            
            $product['pic_arr'] = explode(",", $product['pic']);
            foreach ($product['pic_arr'] as $k=>$v){
                $product['pic_arr'][$k] = $this->config['oss_url'].$v;
            }
            
            $product['content'] = str_replace('<img src="','<img src="'.$this->config['oss_url'],$product['content']); 
            
            $product['today'] = date('m月d日',$today); 
			$product['today'] = ltrim($product['today'],'0');
            
            //根据时间显示
			if($product['yuji_time']>$today){
				$product['show_status'] = 1;
				$product['days'] = ($product['yuji_time'] - $today)/86400;
				$product['yuji_date'] = date('m月d日',$product['yuji_time']); 
				$product['yuji_date'] = ltrim($product['yuji_date'],'0');
				$product['this_day'] = 2;
			}else{
			    $product['this_day'] = 1;
			    $product['type_name'] = $this->jingpai_name($product['type']);
			    
			    if($product['type']==1){
					if(time()<($today+$this->config['zao_hour_start']*3600+$this->config['zao_minute_start']*60)){
						$product['show_status'] = 1;
					}
					if(time()>=($today+$this->config['zao_hour_start']*3600+$this->config['zao_minute_start']*60) && time()<=($today+$this->config['zao_hour_stop']*3600+$this->config['zao_minute_stop']*60)){
						$product['show_status'] = 2;
					}
					if(time()>($today+$this->config['zao_hour_stop']*3600+$this->config['zao_minute_stop']*60)){
						$product['show_status'] = 3;
					}
					if($shenqing){
						if(time()>=($today+$this->config['zao_hour_start']*3600+$this->config['zao_minute_start']*60 - $this->config['youxian_time']*60) && time()<=($today+$this->config['zao_hour_stop']*3600+$this->config['zao_minute_stop']*60)){
							$product['show_status'] = 2;
						}
					}
					$product['start_time'] = $today+$this->config['zao_hour_start']*3600+$this->config['zao_minute_start']*60;
					$product['stop_time'] = $today+$this->config['zao_hour_stop']*3600+$this->config['zao_minute_stop']*60;
				}elseif($product['type']==2){
					if(time()<($today+$this->config['wu_hour_start']*3600+$this->config['wu_minute_start']*60)){
						$product['show_status'] = 1;
					}
					if(time()>=($today+$this->config['wu_hour_start']*3600+$this->config['wu_minute_start']*60) && time()<=($today+$this->config['wu_hour_stop']*3600+$this->config['wu_minute_stop']*60)){
						$product['show_status'] = 2;
					}
					if(time()>($today+$this->config['wu_hour_stop']*3600+$this->config['wu_minute_stop']*60)){
						$product['show_status'] = 3;
					}
					if($shenqing){
						if(time()>=($today+$this->config['wu_hour_start']*3600+$this->config['wu_minute_start']*60 - $this->config['youxian_time']*60) && time()<=($today+$this->config['wu_hour_stop']*3600+$this->config['wu_minute_stop']*60)){
							$product['show_status'] = 2;
						}
					}
					$product['start_time'] = $today+$this->config['wu_hour_start']*3600+$this->config['wu_minute_start']*60;
					$product['stop_time'] = $today+$this->config['wu_hour_stop']*3600+$this->config['wu_minute_stop']*60;
				}elseif($product['type']==3){
					if(time()<($today+$this->config['ye_hour_start']*3600+$this->config['ye_minute_start']*60)){
						$product['show_status'] = 1;
					}
					if(time()>=($today+$this->config['ye_hour_start']*3600+$this->config['ye_minute_start']*60) && time()<=($today+$this->config['ye_hour_stop']*3600+$this->config['ye_minute_stop']*60)){
						$product['show_status'] = 2;
					}
					if(time()>($today+$this->config['ye_hour_stop']*3600+$this->config['ye_minute_stop']*60)){
						$product['show_status'] = 3;
					}
					if($shenqing){
						if(time()>=($today+$this->config['ye_hour_start']*3600+$this->config['ye_minute_start']*60 - $this->config['youxian_time']*60) && time()<=($today+$this->config['ye_hour_stop']*3600+$this->config['ye_minute_stop']*60)){
							$product['show_status'] = 2;
						}
					}
					$product['start_time'] = $today+$this->config['ye_hour_start']*3600+$this->config['ye_minute_start']*60;
					$product['stop_time'] = $today+$this->config['ye_hour_stop']*3600+$this->config['ye_minute_stop']*60;
				}
				$product['start_time_1000'] = $product['start_time']*1000;
				$product['stop_time_1000'] = $product['stop_time']*1000;
			}

            $data['status'] = 1;
            $data['info'] = $product;
            return $data;
        }else{
            $data['status'] = 3;
            $data['info'] = '无数据';
            return $data;
        }
    }
    /**
     * 获取已完善的支付方式个数
     */
    public function get_pay_type_num($member_id){
        $alipay = M('member_alipay')->where('member_id='.$member_id.' AND alipay IS NOT NULL')->count();
        $weixin = M('member_weixin')->where('member_id='.$member_id.' AND weixin IS NOT NULL')->count();
        $bankcard = M('member_bankcard')->where('member_id='.$member_id.' AND bankcard<>"" AND bankcard IS NOT NULL')->count();
        return $alipay+$weixin+$bankcard;
    }
    /**
     * 格式化一个竞拍（置换）订单
     */
    public function get_pai_order($id) {
        $id = intval($id);
        if($id<0){
            $data['status'] = 2;
            $data['info'] = 'ID错误';
            return $data;
        }
        $order = M('pai_order')->where('id='.$id)->find();
        if($order){
            $res['id'] = $order['id'];
            if($order['source']==1){
                $pai = M('pai')->where('id='.$order['pai_id'])->field('pic,title,lun,money')->find();
            }
            elseif($order['source']==2){
                $pai = M('huan')->where('id='.$order['pai_id'])->field('pic,title,money')->find();
                $pai['lun'] = 0;
            }else{
                $data['status'] = 3;
                $data['info'] = '商品错误';
                return $data;
                exit;
            }
            $res['title'] = $pai['title'];
            $product = explode(",", $pai['pic']);
            $res['pic'] = $this->config['oss_url'].$product[0];

            if($order['status']==1){
                $res['status_name'] = '待支付';
            }elseif($order['status']==2){
                $res['status_name'] = '待放货';
            }elseif($order['status']==3){
                $res['status_name'] = '已完成';
            }elseif($order['status']==5){
                $res['status_name'] = '支付超时';
            }elseif($order['status']==6){
                $res['status_name'] = '放货超时';
            }elseif($order['status']==7){
                $res['status_name'] = '申诉中';
            }
            
            //抢单时间
            $res['qiang_time'] = $order['pp_time'];
            $res['qiang_time_1000'] = $order['pp_time']*1000;
            $res['qiang_date'] = date('Y-m-d H:i:s',$order['pp_time']);
			$res['qiang_date_short'] = date('m-d H:i',$order['pp_time']);
            $res['pay_end_time'] = $order['pp_time'] + $this->config['jiaoyi_minite']*60;
            $res['pay_end_time_1000'] = $res['pay_end_time']*1000;
            $res['pay_end_date'] = date('Y-m-d H:i:s',$res['pay_end_time']);
            //支付时间
            $res['pay_time'] = $order['ping_time'];
            $res['pay_time_1000'] = $order['ping_time']*1000;
            $res['pay_date'] = $res['pay_time']>0 ? date('Y-m-d H:i:s',$res['pay_time']) : '';
            //后台处理时间
            $res['handle_time'] = $order['handle_time'];
            $res['handle_time_1000'] = $order['handle_time']*1000;
            $res['handle_date'] = $res['handle_time']>0 ? date('Y-m-d H:i:s',$res['handle_time']) : '';
            //放货截止时间
            if($res['handle_time']>0){
                $res['fang_end_time'] =  $res['handle_time'] + $this->config['jiaoyi_minite']*60;
                $res['fang_end_time_1000'] = $res['fang_end_time']*1000;
            }elseif($res['pay_time']>0){
                $res['fang_end_time'] =  $res['pay_time'] + $this->config['jiaoyi_minite']*60;
                $res['fang_end_time_1000'] = $res['fang_end_time']*1000;
            }else{
                $res['fang_end_time'] =  0;
                $res['fang_end_time_1000'] = $res['fang_end_time']*1000;
            }
            $res['fang_end_date'] = $res['fang_end_time']>0 ? date('Y-m-d H:i:s',$res['fang_end_time']) : '';
            //放货（完成）时间
            $res['deal_time'] = $order['deal_time'];
            $res['deal_time_1000'] = $order['deal_time']*1000;
            $res['deal_date'] = $res['deal_time']>0 ? date('Y-m-d H:i:s',$res['deal_time']) : '';
            $res['auto_deal'] = $order['auto_deal'];

            $res['seller'] = M('Member')->where('member_id='.$order['sell_uid'])->field('phone,nickname,name,code_id')->find();
            $res['buyer'] = M('Member')->where('member_id='.$order['buy_uid'])->field('phone,nickname,name,code_id')->find();
            
            $res['sn'] = $order['sn'];
            $res['stay_status'] = $order['stay_status'];
            $res['type'] = $order['type'];
            $res['type_name'] = $this->jingpai_name($order['type']);
            $res['source'] = $order['source'];
            $res['money'] = $order['money'];
            $res['yuan_money'] = $order['yuan_money'];
            $res['quan_money'] = $order['quan_money'];
            $res['pay_pai_id'] = $order['pay_pai_id'];
            if($order['pay_pai_id']>0){
                $pay = M('pay')->where('id='.$order['pay_pai_id'])->find();
                if($pay['type']=='微信'){
                    $pay_type = M('member_weixin')->where('member_id='.$order['sell_uid'].' AND weixin_pic IS NOT NULL')->field('weixin,weixin_pic,beizhu')->find();
                    $res['pay_type'] = $pay_type;
                    $res['pay_type']['name'] = '微信';
                }elseif($pay['type']=='支付宝'){
                    $pay_type = M('member_alipay')->where('member_id='.$order['sell_uid'].' AND alipay_pic IS NOT NULL')->field('alipay,alipay_name,alipay_pic,beizhu')->find();
                    $res['pay_type'] = $pay_type;
                    $res['pay_type']['name'] = '支付宝';
                }elseif($pay['type']=='银行卡'){
                    $pay_type = M('member_bankcard')->where('member_id='.$order['sell_uid'].' AND bankcard<>"" AND bankcard IS NOT NULL')->field('bankcard,bankcard_name,bank,bank_address,beizhu')->find();
                    $res['pay_type'] = $pay_type;
                    $res['pay_type']['name'] = '银行卡';
                }
            }
            $res['pay_zhihuan_id'] = $order['pay_zhihuan_id'];
            if($order['pay_zhihuan_id']>0){
                $pay = M('pay')->where('id='.$order['pay_zhihuan_id'])->find();
                if($pay['type']=='微信'){
                    $pay_type = M('member_weixin')->where('member_id='.$order['sell_uid'].' AND weixin_pic IS NOT NULL')->field('weixin,weixin_pic,beizhu')->find();
                    $res['pay_type'] = $pay_type;
                    $res['pay_type']['name'] = '微信';
                }elseif($pay['type']=='支付宝'){
                    $pay_type = M('member_alipay')->where('member_id='.$order['sell_uid'].' AND alipay_pic IS NOT NULL')->field('alipay,alipay_name,alipay_pic,beizhu')->find();
                    $res['pay_type'] = $pay_type;
                    $res['pay_type']['name'] = '支付宝';
                }elseif($pay['type']=='银行卡'){
                    $pay_type = M('member_bankcard')->where('member_id='.$order['sell_uid'].' AND bankcard<>"" AND bankcard IS NOT NULL')->field('bankcard,bankcard_name,bank,bank_address,beizhu')->find();
                    $res['pay_type'] = $pay_type;
                    $res['pay_type']['name'] = '银行卡';
                }elseif($pay['type']=='现金余额'){
                    $res['pay_type']['name'] = '现金余额';
                }
            }
			$res['ping_pic'] = $order['ping_pic']==null ? null : $this->config['oss_url'].$order['ping_pic'];
            $res['shensu_info'] = $order['shensu_info'];
            $res['pay_beizhu'] = $order['pay_beizhu'];
            
            if($pai['lun']>=$this->config['zhuan_max_lun']){
                $res['zhuan_limit'] = 1;
            }elseif($pai['money']>=$this->config['zhuan_max_rmb']){
                $res['zhuan_limit'] = 2;
            }else{
                $res['zhuan_limit'] = 0;
            }
            
            $data['status'] = 1;
            $data['info'] = $res;
            return $data;
        }else{
            $data['status'] = 3;
            $data['info'] = '无数据';
            return $data;
        }
    }
    /**
     * 格式化一个提货订单
     */
    public function get_tihuo($id,$member_id) {
        if($id<0){
            $data['status'] = 2;
            $data['info'] = 'ID错误';
            return $data;
        }
        $tihuo = M('tihuo')->where('id='.$id.' AND member_id='.$member_id)->find();
        if($tihuo){
            $order = M('pai_order')->where('id='.$tihuo['order_id'])->find();
            if($order['source']==1){
                $pai = M('pai')->where('id='.$order['pai_id'])->field('pic,title,lun,money')->find();
                $res['type_name'] = '竞拍';
                $res['type_name'] = $res['type_name'] .'-'.$this->jingpai_name($order['type']);
                
            }
            elseif($order['source']==2){
                $pai = M('huan')->where('id='.$order['pai_id'])->field('pic,title,money')->find();
                $pai['lun'] = 0;
                $res['type_name'] = '置换';
            }else{
                $data['status'] = 3;
                $data['info'] = '商品错误';
                return $data;
                exit;
            }
            $res['id'] = $tihuo['id'];
            $res['title'] = $pai['title'];
            $product = explode(",", $pai['pic']);
            $res['pic'] = $this->config['oss_url'].$product[0];
            $res['money'] = $tihuo['money'];
            
            $res['status'] = $tihuo['status'];
            if($tihuo['status']==1){
                $res['status_name'] = '待发货';
            }elseif($tihuo['status']==2){
                $res['status_name'] = '待收货';
            }elseif($tihuo['status']==3){
                $res['status_name'] = '已收货';
            }

            $res['add_time'] = $tihuo['add_time'];
            $res['add_time_1000'] = $tihuo['add_time']*1000;
            $res['qiang_date'] = date('Y-m-d H:i:s',$tihuo['add_time']);
            
            $res['fahuo_time'] = $tihuo['fahuo_time'];
            $res['fahuo_time_1000'] = $tihuo['fahuo_time']*1000;
            $res['fahuo_date'] = $tihuo['fahuo_time'] >0 ? date('Y-m-d H:i:s',$tihuo['fahuo_time']) : '';
            $res['shouhuo_time'] = $tihuo['shouhuo_time'];
            $res['shouhuo_time_1000'] = $tihuo['shouhuo_time']*1000;
            $res['shouhuo_date'] = $tihuo['shouhuo_time'] >0 ? date('Y-m-d H:i:s',$tihuo['shouhuo_time']) : '';
            $res['kuaidi_name'] = $tihuo['kuaidi_name'];
            $res['kuaidi_sn'] = $tihuo['kuaidi_sn'];
            if($tihuo['address_record_id']>0){
                $res['address'] = M('address_record')->where('member_id='.$tihuo['member_id'].' AND id='.$tihuo['address_record_id'])->find();
                unset($res['address']['id']);
                unset($res['address']['member_id']);
                unset($res['address']['add_time']);
            }
            $res['is_zhihuan'] = $tihuo['is_zhihuan'];
            $res['zhihuan_status'] = $tihuo['zhihuan_status'];
            if($tihuo['zhihuan_status']==0){
                $res['zhihuan_status_name'] = '未拆分';
            }elseif($tihuo['zhihuan_status']==1){
                $res['zhihuan_status_name'] = '已拆分';
            }
            
            if($tihuo['quan']>0){
                $quan_arr = explode('+',$tihuo['quan']);
                foreach ($quan_arr as $k=>$v){
                    $arr = explode('/',$v);
                    $res['quan_info'].=  $arr[1].'张'.$arr[0].'的，';
                }
                $res['quan_info'] = trim($res['quan_info'],'，');
            }
            
            $data['status'] = 1;
            $data['info'] = $res;
            return $data;
        }else{
            $data['status'] = 3;
            $data['info'] = '无数据';
            return $data;
        }
    }
    
    
    
	//图片转base64
	public function base64EncodeImage ($image_file) {
		$base64_image = '';
		$image_info = getimagesize($image_file);
		$image_data = fread(fopen($image_file, 'r'), filesize($image_file));
		$base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
		return $base64_image;
	}
	
	//图片转小
	public function zhuan($path,$ext){ 
		require_once('./thumb.class.php');
		$t = new \ThumbHandler();

		if($ext == 'jpg'){
			$t->setImgDisplayQuality(1);
		}
		$t->setSrcImg($path);
		$t->setDstImg($path);
		$res = $t->createImg(1080,1920);
		return $res;
	}
	
	//压缩图片
	/**
     * desription 压缩图片
     * @param sting $imgsrc 图片路径
     * @param string $imgdst 压缩后保存路径
     */
    public function compressedImage($imgsrc, $imgdst) {
        list($width, $height, $type) = getimagesize($imgsrc);
       
        $new_width = $width;//压缩后的图片宽
        $new_height = $height;//压缩后的图片高
 
        if($width >= 600){
            $per = 600 / $width;//计算比例
            $new_width = $width * $per;
            $new_height = $height * $per;
        }
 
        switch ($type) {
            case 1:
                $giftype = check_gifcartoon($imgsrc);
                if ($giftype) {
                    header('Content-Type:image/gif');
                    $image_wp = imagecreatetruecolor($new_width, $new_height);
                    $image = imagecreatefromgif($imgsrc);
                    imagecopyresampled($image_wp, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    //90代表的是质量、压缩图片容量大小
                    imagejpeg($image_wp, $imgdst, 90);
                    imagedestroy($image_wp);
                    imagedestroy($image);
                }
                break;
            case 2:
                header('Content-Type:image/jpeg');
                $image_wp = imagecreatetruecolor($new_width, $new_height);
                $image = imagecreatefromjpeg($imgsrc);
                imagecopyresampled($image_wp, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                //90代表的是质量、压缩图片容量大小
                imagejpeg($image_wp, $imgdst, 90);
                imagedestroy($image_wp);
                imagedestroy($image);
                break;
            case 3:
                header('Content-Type:image/png');
                $image_wp = imagecreatetruecolor($new_width, $new_height);
                $image = imagecreatefrompng($imgsrc);
                imagecopyresampled($image_wp, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                //90代表的是质量、压缩图片容量大小
                imagejpeg($image_wp, $imgdst, 90);
                imagedestroy($image_wp);
                imagedestroy($image);
                break;
        }
    }
	
	
	/**
     * 添加财务日志方法
     * @param unknown $member_id
     * @param unknown $type  
     * @param unknown $content
     * @param unknown $money
     * @param unknown $money_type  收入=1/支出=2
     * @param unknown $currency_id  币种id 0是rmb
     * @return 
     */
    public function addFinance($member_id, $type, $content, $money, $money_type, $currency_id){
    	if($currency_id == 3) {
    		$wallet_type = "rmb";
    	}elseif($currency_id == 4) {
    		$wallet_type = "rmb_forzen";
    	}elseif($currency_id == 5) {
    		$wallet_type = "bzj";
    	}elseif($currency_id == 6) {
    		$wallet_type = "heo";
    	}elseif($currency_id == 7) {
    		$wallet_type = "heo_bind";
    	}elseif($currency_id == 8) {
    		$wallet_type = "sfj";
    	}elseif($currency_id == 9) {
    		$wallet_type = "gxz";
    	}else{
    		
    	}
    	$money_xian = M('Member')->where('member_id='.$member_id)->getField($wallet_type);
    	
    	$data['member_id'] = $member_id;
    	$data['type'] = $type;
    	$data['content'] = $content;
    	$data['money_type'] = $money_type;
    	$data['money'] = $money;
    	$data['money_xian'] = $money_xian;
    	$data['add_time'] = time();
    	$data['currency_id'] = $currency_id;
    	$data['ip'] = get_client_ip();
    	$list = M('Finance')->add($data);
    	if ($list) {
    		return $list;
    	} else {
    		return false;
    	}
    }
    /**
     *  查询我的团队里是否有某个人
     */
    public function checkmyteam22222($parent_id, $child_id)
    {
        
        $where['member_id'] = $child_id;
        $child = array();
        $child = M('Member')->where($where)->field('member_id,pid')->find();
        if ($child['pid']) {
            if ($child['pid'] == $parent_id) {
                return true;
            } else {
                return $this->checkmyteam($parent_id, $child['pid']);
            }
        }
        return false;
    }
    /**
     *  查询我的团队里是否有某个人
     */
    public function checkmyteam($parent_id, $child_id)
    {
        $where['member_id'] = $child_id;
        $dai_path = M('Member')->where($where)->getField('dai_path');
		if(!$dai_path){
			return false;
		}
		$arr = explode(',',$dai_path);
		if(in_array($parent_id,$arr)){
			return true;
		}else{
			return false;
		}
    }
    /**
     * 验证身份证号
     */
    public function check_idcard() {
        if (IS_POST) {
            $idcard = I('post.idcard'); 
            $res = $this->validation_filter_id_card($idcard);
            
            $data['status'] = 1;
            $data['info'] = $res;
            $this->ajaxReturn($data);
        }
    }
    
    // 调用方法
    public function validation_filter_id_card($id_card){
        if(strlen($id_card)==18){
            return $this->idcard_checksum18($id_card);
        }elseif((strlen($id_card)==15)){
            $id_card=$this->idcard_15to18($id_card);
            return $this->idcard_checksum18($id_card);
        }else{
            return false;
        }
    }
    // 计算身份证校验码，根据国家标准GB 11643-1999
    public function idcard_verify_number($idcard_base){
        if(strlen($idcard_base)!=17){
            return false;
        }
        //加权因子
        $factor=array(7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2);
        //校验码对应值
        $verify_number_list=array('1','0','X','9','8','7','6','5','4','3','2');
        $checksum=0;
        for($i=0;$i<strlen($idcard_base);$i++){
            $checksum += substr($idcard_base,$i,1) * $factor[$i];
        }
        $mod=$checksum % 11;
        $verify_number=$verify_number_list[$mod];
        return $verify_number;
    }
    // 将15位身份证升级到18位
    public function idcard_15to18($idcard){
        if(strlen($idcard)!=15){
            return false;
        }else{
            // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
            if(array_search(substr($idcard,12,3),array('996','997','998','999')) !== false){
                $idcard=substr($idcard,0,6).'18'.substr($idcard,6,9);
            }else{
                $idcard=substr($idcard,0,6).'19'.substr($idcard,6,9);
            }
        }
        $idcard=$idcard.$this->idcard_verify_number($idcard);
        return $idcard;
    }
    // 18位身份证校验码有效性检查
    public function idcard_checksum18($idcard){
        if(strlen($idcard)!=18){
            return false;
        }
        $idcard_base=substr($idcard,0,17);
        if($this->idcard_verify_number($idcard_base)!=strtoupper(substr($idcard,17,1))){
            return false;
        }else{
            return true;
        }
    }
    
    /**
     * 竞拍场次名称
     */
    public function jingpai_name($type) {
        switch ($type) {
            case 1:
    			$name = $this->config['zao_name'];
    			break;
    		case 2:
    			$name = $this->config['wu_name'];
                break;
    		case 3:
    			$name = $this->config['wan_name'];
                break;	
        }
        return $name;
    }
    /**
     * 竞拍起止时间段
     */
    public function jingpai_time($type) {
        $today = strtotime('today');
        switch ($type) {
            case 1:
                $start_time = $today + $this->config['zao_hour_start']*3600 + $this->config['zao_minute_start']*60;
                $stop_time = $today + $this->config['zao_hour_stop']*3600 + $this->config['zao_minute_stop']*60;
                $start_time_1000 =  $start_time*1000;
                $stop_time_1000 =  $stop_time*1000;
    			$start_date = $this->config['zao_hour_start'].':'.$this->config['zao_minute_start'];
    			$stop_date = $this->config['zao_hour_stop'].':'.$this->config['zao_minute_stop'];
    			$show_status = $this->config['zao_status'];
				$bg_pic = $this->config['oss_url'].$this->config['zao_pic'];
    			break;
    		case 2:
    			$start_time = $today + $this->config['wu_hour_start']*3600 + $this->config['wu_minute_start']*60;
                $stop_time = $today + $this->config['wu_hour_stop']*3600 + $this->config['wu_minute_stop']*60;
                $start_time_1000 =  $start_time*1000;
                $stop_time_1000 =  $stop_time*1000;
    			$start_date = $this->config['wu_hour_start'].':'.$this->config['wu_minute_start'];
    			$stop_date = $this->config['wu_hour_stop'].':'.$this->config['wu_minute_stop'];
    			$show_status = $this->config['wu_status'];
				$bg_pic = $this->config['oss_url'].$this->config['wu_pic'];
                break;
    		case 3:
    			$start_time = $today + $this->config['ye_hour_start']*3600 + $this->config['ye_minute_start']*60;
                $stop_time = $today + $this->config['ye_hour_stop']*3600 + $this->config['ye_minute_stop']*60;
                $start_time_1000 =  $start_time*1000;
                $stop_time_1000 =  $stop_time*1000;
    			$start_date = $this->config['ye_hour_start'].':'.$this->config['ye_minute_start'];
    			$stop_date = $this->config['ye_hour_stop'].':'.$this->config['ye_minute_stop'];
    			$show_status = $this->config['ye_status'];
				$bg_pic = $this->config['oss_url'].$this->config['wan_pic'];
                break;	
        }
        $data['start_time'] = $start_time;
        $data['stop_time'] = $stop_time;
        $data['start_time_1000'] = $start_time_1000;
        $data['stop_time_1000'] = $stop_time_1000;
        $data['start_date'] = $start_date;
        $data['stop_date'] = $stop_date;
        $data['show_status'] = $show_status;
        $data['type_name'] = $this->jingpai_name($type);
        $data['type'] = $type;
		$data['bg_pic'] = $bg_pic;
        return $data;
    }
    
    /**
    *  格式化用户信息
    */
    public function user_info($member_id){
        $today = strtotime('today');
		$user = M('member')->where('member_id='.$member_id)->find();
		//持仓
		$user['chicang'] = M('pai')->where("status = 1 AND member_id = ".$user['member_id'])->sum('money');
		$user['chicang'] = number_format($user['chicang'], 2, '.', '');
		$user['chicang_num'] = M('pai')->where("status = 1 AND member_id = ".$user['member_id'])->count();
		//当日业绩
		$user['yeji_today'] = M('pai_order')->where("status = 3 AND buy_uid = ".$user['member_id'].' AND deal_time>'.$today)->sum('money');
		$user['yeji_today'] = number_format($user['yeji_today'], 2, '.', '');
		//总业绩
		$user['yeji_all'] = M('pai_order')->where("status = 3 AND buy_uid = ".$user['member_id'])->sum('money');
		$user['yeji_all'] = number_format($user['yeji_all'], 2, '.', '');
		
		
		//团队下的信息
		//$team = $this->my_team($member_id);
		$team = array();
		$user['team'] = $team;
        return $user;
    }
    /**
    *  格式化用户的团队信息
    */
    public function my_team($member_id){
        $today = strtotime('today');
        //$list=M('Member')->select();
        $list=M('member')->where('dai_path like "%,'.$member_id.',%"')->select();
        foreach($list as $k=>$v){
            //$you = $this->checkmyteam($member_id,$v['member_id']);
            //if($you){
                $data[$k]['member_id'] = $v['member_id'];
    			$data[$k]['rmb'] = $v['rmb'];
    			$data[$k]['heo'] = $v['heo'];
    			$data[$k]['heo_bind'] = $v['heo_bind'];
    			//持仓
    			$data[$k]['money']= M('pai')->where("status = 1 AND member_id = ".$v['member_id'])->sum('money');
    			$data[$k]['chicang']= number_format($data[$k]['money'], 2, '.', '');
    			$data[$k]['chicang_num']= M('pai')->where("status = 1 AND member_id = ".$v['member_id'])->count();
    			//当日业绩
    			$data[$k]['yeji_today']= M('pai_order')->where("status = 3 AND buy_uid = ".$v['member_id'].' AND deal_time>'.$today)->sum('money');
    			$data[$k]['yeji_today']= number_format($data[$k]['yeji_today'], 2, '.', '');
    			//总业绩
    			$data[$k]['yeji_all']= M('pai_order')->where("status = 3 AND buy_uid = ".$v['member_id'])->sum('money');
    			$data[$k]['yeji_all']= number_format($data[$k]['yeji_all'], 2, '.', '');
    			
    			
    			$res['rmb']+= $v['rmb'];
    			$res['heo']+= $v['heo'];
    			$res['heo_bind']+= $v['heo_bind'];
    			$res['chicang']+= $data[$k]['chicang'];
    			$res['chicang_num']+=  $data[$k]['chicang_num'];
    			$res['yeji_today']+= $data[$k]['yeji_today'];
    			$res['yeji_all']+= $data[$k]['yeji_all'];
    			
    			
    			if($v['vip_level']>0){
    			    $res['paike']+= $paike+1;
    			}
    			$res['renshu']+= 1;
            //}
        }
        return $res;
	}
	//构建一个发送请求的curl方法
    public function https_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
	/**
    *  获取物流信息
    */
	public function get_wuliu($com,$num){
		if(empty($com) || empty($num)){
			$re['status'] = 2;
			$re['info'] = '参数缺失';
			return $re;
		}
		
		//参数设置
		$key = C('WULIU_CONFIG.key');    		//客户授权key
		$customer = C('WULIU_CONFIG.customer'); //查询公司编号
		$param = array (
			'com' => $com,     //快递公司编码
			'num' => $num      //快递单号
		);
		//请求参数
		$post_data = array();
		$post_data["customer"] = $customer;
		$post_data["param"] = json_encode($param);
		$sign = md5($post_data["param"].$key.$post_data["customer"]);
		$post_data["sign"] = strtoupper($sign);
		$url = 'http://poll.kuaidi100.com/poll/query.do';    //实时查询请求地址
		$params = "";
		foreach ($post_data as $k=>$v) {
			$params .= "$k=".urlencode($v)."&";              //默认UTF-8编码格式
		}
		$post_data = substr($params, 0, -1);
		$res = $this->https_request($url,$post_data);
		$res2 = json_decode($res,true);
		if($res2['state']=='3' && $res2['status']=='200'){
			//签收记录物流信息
			$wl_data['kuaidi_sn'] = $num;
			$wl_data['kuaidi_name'] = $com;
			$wl_data['data'] = $res;
			$wl_data['add_time'] = time();
			M('wuliu')->add($wl_data);
		}
		
		$re['status'] = 1;
		$re['res'] = $res2;
		return $re;
	}
	// 二维数组根据某键值去重
 	public function array_unset_tt($arr,$key){     
        //建立一个目标数组  
        $res = array();        
        foreach ($arr as $value) {           
           //查看有没有重复项  
           if(isset($res[$value[$key]])){  
                 //有：销毁  
                 unset($value[$key]);  
           }  
           else{   
                $res[$value[$key]] = $value;  
           }    
        }  
        return $res;  
    }
	
}
