<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use Think\Page;
class JingjiaController extends CommonController {
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
        //竞价商品数量入缓存
        $id = I('id',0,'intval');
        if($id>0){
            if(empty(S('jingjia_num_'.$id))){
                $jingjia = M('jingjia')->where('id='.$id.' AND status=1 ')->find();
                if($jingjia){
                    $jj_yu = ($jingjia['fd_price']-$jingjia['qp_price'])%$jingjia['yj_price'];
                    $jj_int = intval(($jingjia['fd_price']-$jingjia['qp_price'])/$jingjia['yj_price']);
                    $jj_num =  $jj_yu==0 ? $jj_int : ($jj_int+1);
                    S('jingjia_num_'.$id , $jj_num);
                }
            }
        }
    }
    /**
     * 获取单个竞价区商品
     */
    public function get_jingjia_info(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            $res = $this->get_jingjia_product($id);
            if($res['status']==1){
                $res['info']['my_weiguan'] = M('weiguan')->where('jingjia_id='.$id.' AND member_id='.$member_id)->count();
                
                if($user['rmb']>=$res['info']['bzj']){
                    $res['info']['bzj_status'] = 1;
                    $res['info']['bzj_status_name'] = '保证金充足';
                }else{
                    $res['info']['bzj_status'] = 2;
                    $res['info']['bzj_status_name'] = '保证金不足';
                }
                
                $data['status'] = 1;
                $data['info'] = '获取成功';
                //$data['url'] = $this->config['oss_url'];
                $data['data'] = $res['info'];
                $this->ajaxReturn($data);
            }else{
               $this->ajaxReturn($res); 
            }
        }
    }
    /**
     * 竞价商品 出价
     */
    public function buy(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = -1;
                $data['info'] = 'ID参数错误';
                $this->ajaxReturn($data);
            }
			$where = 'id='.$id;
            $jingjia = M('jingjia')->where($where)->find();
            if(!$jingjia){
                $data['status'] = 2;
                $data['info'] = '无可交易的商品';
                $this->ajaxReturn($data);
            }
			//检测是否已锁定
			if($jingjia['status']==5){
				$data['status'] = -5;
				$data['info'] = '服务器繁忙，请稍后再试';
				$this->ajaxReturn($data);
			}
			//锁定竞品
			M('jingjia')->where($where)->setField('status',5);
			//检测是否已竞价结束
			if($jingjia['status']==2){
				$data['status'] = -1;
				$data['info'] = '商品已竞价结束';
				M('jingjia')->where($where)->setField('status',$jingjia['status']);
				$this->ajaxReturn($data);
			}
			
            if($jingjia['start_time']<=0 || $jingjia['start_time']>time() || $jingjia['start_time']+$jingjia['jp_time']*60<time()){
                $data['status'] = 3;
                $data['info'] = '该商品不可交易';
				M('jingjia')->where($where)->setField('status',$jingjia['status']);
                $this->ajaxReturn($data);
            }
            //是否参与过
            /*$jj_order = M('jingjia_order')->where('jingjia_id='.$id.' AND member_id='.$member_id)->count();
            if($jj_order){
                $data['status'] = 4;
                $data['info'] = '本场您已参与过';
                $this->ajaxReturn($data);
            }*/
            //保证金是否充足
            if($user['rmb']<$jingjia['bzj']){
                $data['status'] = 5;
                $data['info'] = '您的保证金不足';
				M('jingjia')->where($where)->setField('status',$jingjia['status']);
                $this->ajaxReturn($data);
            }
            //是否售罄
            //S('jingjia_num_'.$id,2);
            $yuan_num = S('jingjia_num_'.$id);
            
            /*$data['status'] = 4;
            $data['info'] = $yuan_num;
            $this->ajaxReturn($data);*/
            
            if($yuan_num <= 0){
                $data['status'] = 6;
                $data['info'] = '出价失败，商品已售出';
				M('jingjia')->where($where)->setField('status',$jingjia['status']);
                $this->ajaxReturn($data);
            }else{
				//缓存数量减一
                S('jingjia_num_'.$id , $yuan_num-1);
                //上一笔出价
                $last_order = M('jingjia_order')->where('jingjia_id='.$id)->order('id desc')->find();
                if($last_order['price']){
                    $price = $last_order['price'] + $jingjia['yj_price'];
                }else{
                    $price = $jingjia['qp_price'] + $jingjia['yj_price'];
                }
                //此次记录
                $order['member_id'] = $member_id;
                $order['jingjia_id'] = $id;
                $order['price'] = $price;
                $order['add_time'] = time();
                $order['sn'] = $this->get_sn();
                $order['done_status'] = 1;
                $order['shouyi'] = round($jingjia['yj_price']*$this->config['jingjia_yi_rate']*0.01,2);
                $res = M('jingjia_order')->add($order);
                //冻结保证金
                M('member')->where('member_id='.$member_id)->setDec('rmb',$jingjia['bzj']);
                M('member')->where('member_id='.$member_id)->setInc('bzj',$jingjia['bzj']);
                addFinance($member_id, 31, '竞价出价成功，冻结保证金【'.$jingjia['title'].'】', $jingjia['bzj'], 2, 3);
                addFinance($member_id, 31, '竞价出价成功，冻结保证金【'.$jingjia['title'].'】', $jingjia['bzj'], 1, 5);
                //上一笔出局
                if($last_order){
                    M('jingjia_order')->where('id='.$last_order['id'])->setField('done_status',2);
                    //释放上一笔的保证金
                    M('member')->where('member_id='.$last_order['member_id'])->setInc('rmb',$jingjia['bzj']);
                    M('member')->where('member_id='.$last_order['member_id'])->setDec('bzj',$jingjia['bzj']);
                    addFinance($member_id, 32, '竞价出局，释放保证金【'.$jingjia['title'].'】', $jingjia['bzj'], 1, 3);
                    addFinance($member_id, 32, '竞价出局，释放保证金【'.$jingjia['title'].'】', $jingjia['bzj'], 2, 5);
                }
                
                $xian_num = S('jingjia_num_'.$id);
                M('jingjia')->where('id='.$id)->setField('dq_price',$price);
                if($xian_num==0){
                    //最后一人，结束竞价
                    //更新成交表
                    $order_data['id'] = $res;
                    $order_data['price'] = $jingjia['fd_price'];
                    $order_data['status'] = 1;
                    $order_data['done_status'] = 3;
                    //$order_data['shouyi'] = round($jingjia['yj_price']*$this->config['jingjia_yi_rate']*0.01,2);
                    $order_data['zeng'] = $jingjia['zeng'];
                    $order_data['jingpai_end_time'] = time();
                    M('jingjia_order')->save($order_data);
					//更新商品表
					$jj_data['id'] = $id;
					$jj_data['status'] = 2;
					$jj_data['order_id'] = $res;
					$jj_data['end_time'] = time();
					$jj_data['dq_price'] = $jingjia['fd_price'];
					M('jingjia')->save($jj_data);
                    
                    //发送通知短信
                    $params = array(
                        "templateId" => "13604",
                        "mobile" => $user['phone'],
                		"paramType" => "json",
                        "params" => json_encode($json_param),
                    );
            		$this->send_message($params);
                    //下发收益，最后一人获得赠送绑定-HEO，释放保证金；支付成功的时候，这里不写
                }else{
					M('jingjia')->where($where)->setField('status',$jingjia['status']);
				}
                $data['status'] = 1;
                $data['info'] = '出价成功';
                $data['shouyi'] = $order['shouyi'];
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 获取已出价的用户列表
     */
    public function get_user_jingjia(){
        if(IS_POST){
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = 'ID参数错误';
                $this->ajaxReturn($data);
            }
            $order = M('jingjia_order')->where('jingjia_id='.$id)->order('id desc')->select();
            if($order){
                foreach ($order as $k=>$v){
                    $res[$k]['member_id'] = $v['member_id'];
                    $user = M('member')->where('member_id='.$v['member_id'])->field('head,nickname')->find();
                    $res[$k]['nickname'] = substr_cut($user['nickname']);
                    $res[$k]['head'] = $this->config['oss_url'].$user['head'];
                    $res[$k]['price'] = $v['price'];
                    $res[$k]['add_time'] = $v['add_time'];
                    $res[$k]['add_time_1000'] = $v['add_time']*1000;
                    $res[$k]['add_date'] = date('Y-m-d H:i:s',$v['add_time']);
                    $res[$k]['status'] = $v['done_status'];
                    if($v['done_status']==1){
                        $res[$k]['status_name'] = '领先';
                    }elseif($v['done_status']==2){
                        $res[$k]['status_name'] = '出局';
                    }elseif($v['done_status']==3){
                        $res[$k]['status_name'] = '成交';
                    }elseif($v['done_status']==4){
                        $res[$k]['status_name'] = '成交';
                    }
                }
                
            }else{
                $res = array();
            }
            $data['status'] = 1;
            $data['data'] = $res;
            $this->ajaxReturn($data);
        }
    }
    /**
     * 获取我的出价
     */
    public function get_my_jingjia(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;
            
            $member_id = $this->user['member_id'];
            $count      =  M('jingjia_order')->where('member_id='.$member_id)->count();
            $order = M('jingjia_order')->where('member_id='.$member_id)->limit($num)->page($page)->order('id desc')->select();
            if($order){
                foreach ($order as $k=>$v){
                    $res[$k]['id'] = $v['jingjia_id'];
                    $jingjia = M('jingjia')->where('id='.$v['jingjia_id'])->field('pic,title,dq_price')->find();
                    $res[$k]['title'] = $jingjia['title'];
                    $product = explode(",", $jingjia['pic']);
                    $res[$k]['pic'] = $this->config['oss_url'].$product[0];
                    $res[$k]['my_price'] = $v['price'];
                    $res[$k]['dq_price'] = $jingjia['dq_price'];
                    $res[$k]['end_time'] = $v['add_time'];
                    $res[$k]['end_time_1000'] = $v['add_time']*1000;
                    $res[$k]['end_date'] = date('H:i',$v['add_time']);
                }
                
            }else{
                $res = array();
            }
            $data['status'] = 1;
            $data['total_num'] = $count;
    		$data['num'] = $num;
    		$yu = $count % $num;
    		$total_page = intval($count/$num); 
    		$data['total_page'] = $yu==0 ? $total_page : ($total_page+1);
    		$data['page'] = $page;
            $data['data'] = $res;
            $this->ajaxReturn($data);
        }
    }
    /**
     * 获取我的竞价订单列表
     */
    public function get_my_order(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;
            
            $status = intval(I('status'));
            
            $member_id = $this->user['member_id'];
            $where = 'done_status IN (3,4) AND member_id='.$member_id;
            if($status>0){
                if($status==5){
                    $where.=' AND done_status=4';
                }else{
                    $where.=' AND status='.$status;
                }
            }
            $count      =  M('jingjia_order')->where($where)->count();
            $order = M('jingjia_order')->where($where)->limit($num)->page($page)->order('done_status,status')->select();
            if($order){
                foreach ($order as $k=>$v){
                    $res[$k]['id'] = $v['id'];
                    $jingjia = M('jingjia')->where('id='.$v['jingjia_id'])->field('pic,title')->find();
                    $res[$k]['title'] = $jingjia['title'];
                    $product = explode(",", $jingjia['pic']);
                    $res[$k]['pic'] = $this->config['oss_url'].$product[0];
                    $res[$k]['price'] = $v['price'];
                    
                    $res[$k]['status'] = $v['status'];
                    if($v['status']==1){
                        $res[$k]['status_name'] = '待支付';
                    }elseif($v['status']==2){
                        $res[$k]['status_name'] = '待发货';
                    }elseif($v['status']==3){
                        $res[$k]['status_name'] = '待收货';
                    }elseif($v['status']==4){
                        $res[$k]['status_name'] = '已完成';
                    }
                    if($v['done_status']==4){
                        $res[$k]['status'] = 5;
                        $res[$k]['status_name'] = '已取消';
                    }

                    $res[$k]['add_time'] = $v['add_time'];
                    $res[$k]['add_time_1000'] = $v['add_time']*1000;
                    $res[$k]['add_date'] = date('Y-m-d H:i:s',$v['add_time']);
                    
                    $res[$k]['jingpai_end_time'] = $v['jingpai_end_time'];
                    $res[$k]['jingpai_end_time_1000'] = $v['jingpai_end_time']*1000;
                    $res[$k]['jingpai_end_date'] = date('Y-m-d H:i:s',$v['jingpai_end_time']);
                    
                    $res[$k]['end_time'] = $v['jingpai_end_time'] + $this->config['jingjia_pay_time']*60;
                    $res[$k]['end_time_1000'] = $res[$k]['end_time']*1000;
                    $res[$k]['end_date'] = date('Y/m/d H:i:s',$res[$k]['end_time']);
                    
                    $res[$k]['service_time_1000'] = time()*1000;
                }
            }else{
                $res = array();
            }
            $data['status'] = 1;
            $data['total_num'] = $count;
    		$data['num'] = $num;
    		$yu = $count % $num;
    		$total_page = intval($count/$num); 
    		$data['total_page'] = $yu==0 ? $total_page : ($total_page+1);
    		$data['page'] = $page;
            $data['data'] = $res;
            $this->ajaxReturn($data);
        }
    }
    /**
     * 获取我的竞价订单详情
     */
    public function get_my_order_info(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            $res = $this->get_jingjia_order($id,$member_id);
            if($res['status']==1){
                $data['status'] = 1;
                $data['info'] = '获取成功';
                //$data['url'] = $this->config['oss_url'];
                $data['data'] = $res['info'];
                $this->ajaxReturn($data);
            }else{
               $this->ajaxReturn($res); 
            }
        }
    }
    /**
     * 提交订单，支付
     */
    public function submit_order(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            $add_id = intval(I('address_id'));
            $pay_type = intval(I('pay_type'));
            if($id<=0 || $add_id<=0 || $pay_type<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('jingjia_order')->where('id='.$id.' AND member_id='.$member_id)->find();
            if($order){
                if($order['status']!=1){
                    $data['status'] = 7;
                    $data['info'] = '订单已支付，请勿重复提交';
                    $this->ajaxReturn($data);
                }
                $address = M('address')->where('id='.$add_id.' AND member_id='.$member_id)->find();
                if(!$address){
                    $data['status'] = 8;
                    $data['info'] = '收货地址错误';
                    $this->ajaxReturn($data);
                }
                if($pay_type==3){
                    $pwd = trim(I('pwd'));
                    if($user['pwd_trade'] != md5(md5($pwd))){
                        $data['status'] = 6;
                        $data['info'] = '支付密码错误';
                        $this->ajaxReturn($data);
                    }
                    
                    //现金支付
                    if($user['rmb']<$order['price']){
                        $data['status'] = 3;
                        $data['info'] = '现金余额不足';
                        $this->ajaxReturn($data);
                    }
                    $pay_data['type'] = '现金余额';
                    
                    //扣除现金
                    M('member')->where('member_id='.$member_id)->setDec('rmb',$order['price']);
                    addFinance($member_id, 13, '支付竞价商品订单', $order['price'], 2, 3);
                    //下发所有出价者收益
                    $jingjia_order_list = M('jingjia_order')->where('jingjia_id='.$order['jingjia_id'])->select();
                    foreach ($jingjia_order_list as $k=>$v){
                        M('member')->where('member_id='.$v['member_id'])->setInc('rmb',$v['shouyi']);
                        addFinance($v['member_id'], 14, '竞价商品溢价收益', $v['shouyi'], 1, 3);
                    }
                    //获得赠送绑定-HEO
                    M('member')->where('member_id='.$member_id)->setInc('heo_bind',$order['zeng']);
                    addFinance($member_id, 16, '竞价商品成交支付成功获得赠送绑定-HEO', $order['zeng'], 1, 7);
                    //释放保证金
                    $jingjia_bzj = M('jingjia')->where('id='.$order['jingjia_id'])->getField('bzj');
					$jingjia_title = M('jingjia')->where('id='.$order['jingjia_id'])->getField('title');
                    M('member')->where('member_id='.$member_id)->setDec('bzj',$jingjia_bzj);
                    addFinance($member_id, 32, '竞价商品成交支付成功释放保证金【'.$jingjia_title.'】', $jingjia_bzj, 2, 5);
                    
                    $order_data['pay_type'] = '现金余额支付';
                    $order_data['fukuan_time'] = time();
                    $order_data['status'] = 2;
                }
                elseif($pay_type==1){
                    $order_data['pay_type'] = '微信支付';
                    $pay_data['type'] = '微信';
                }
                elseif($pay_type==2){
                    $order_data['pay_type'] = '支付宝支付';
                    $pay_data['type'] = '支付宝';
                }
                $pay_data['scene'] = '竞价支付';
                $pay_data['money'] = $order['price'];
                $pay_data['scene_id'] = $id;
                $pay_data['add_time'] = time();
                $pay_id = M('pay')->add($pay_data);
                
                $order_data['id'] = $id;
                $order_data['add_id'] = $add_id;
                $order_data['pay_id'] = $pay_id;
                unset($address['id']);
                unset($address['is_default']);
                $address_record = M('address_record')->add($address);
                $order_data['address_record_id'] =  $address_record;
                $r = M('jingjia_order')->save($order_data);
                if($r !== false){
                    $data['status'] = 1;
                    $data['info'] = $pay_type==3 ? '支付成功' : '提交成功';
                    $this->ajaxReturn($data);
                }else{
                    $data['status'] = 5;
                    $data['info'] = '无此订单信息';
                    $this->ajaxReturn($data);
                    
                }
            }else{
                $data['status'] = 4;
                $data['info'] = '无此订单信息';
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 确认收货
     */
    public function shouhuo(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('jingjia_order')->where('id='.$id.' AND member_id='.$member_id)->find();
            if($order){
                if($order['status']!=3){
                    $data['status'] = 7;
                    $data['info'] = '此订单无法确认收货';
                    $this->ajaxReturn($data);
                }
                $tihuo_data['id'] = $id;
    			$tihuo_data['status'] = 4;
    			$tihuo_data['shouhuo_time'] = time();
    			$r = M('jingjia_order')->save($tihuo_data);
                if($r !== false){
    				$data['status'] = 1;
    				$data['info'] = "收货成功";
    				$this->ajaxReturn($data);	
    			}else{
    				$data['status'] = 3;
    				$data['info'] = "收货失败";
    				$this->ajaxReturn($data);
    			}
                
            }else{
                $data['status'] = 4;
                $data['info'] = '无此订单信息';
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 查询物流信息
     */
    public function wuliu(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('jingjia_order')->where('id='.$id.' AND member_id='.$member_id)->find();
            if($order){
                if($order['status']!=2 && $order['status']!=3 && $order['status']!=4 ){
                    $data['status'] = 7;
                    $data['info'] = '订单无物流信息';
                    $this->ajaxReturn($data);
                }
				
				$wuliu = M('wuliu')->where('kuaidi_sn="'.$order['kuaidi_sn'].'"')->find();
				if($wuliu){
					$data['status'] = 1;
					$data['data']['message'] = 'ok';
					$data['data']['data'] = json_decode($wuliu['data'],true);
					$data['data']['data']['com_name'] = kuaidi_name($data['data']['data']['com']);
					$this->ajaxReturn($data); 
				}else{
					$kuaidi_name = kuaidi_com($order['kuaidi_name']);
					$res = $this->get_wuliu($kuaidi_name,$order['kuaidi_sn']);
					if($res['res']['com']){
						$res['res']['com_name'] = kuaidi_name($res['res']['com']);
					}
					$data['status'] = 1;
					$data['data'] = $res['res'];
					$this->ajaxReturn($data); 
				}  
				
            }else{
                $data['status'] = 4;
                $data['info'] = '无此订单信息';
                $this->ajaxReturn($data);
            }
        }
    }
	//生成订单号
    public function get_sn() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
		
		$str1 = substr($msectime,0,10);
		$date = date("YmdHis",$str1);
		$str2 = substr($msectime,-3);
		
        return $date.$str2;
    }
    
}
