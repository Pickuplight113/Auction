<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use Think\Page;
class HuanController extends CommonController {
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
    }
    /*
     * 商品转拍页面
     */
    public function zhuanpai()
    {
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('pai_order')->where('id='.$id.' AND buy_uid='.$member_id)->find();
            if(!$order){
                $data['status'] = 3;
                $data['info'] = '无订单信息';
                $this->ajaxReturn($data);
            }
            if($order['status']!=3 || $order['stay_status']!=0){
                $data['status'] = 4;
                $data['info'] = '订单不能转拍';
                $this->ajaxReturn($data);
            }
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
            $res['source'] = $order['source'];
            $res['money'] = $order['yuan_money'];
            
            $res['lun'] = $pai['lun'];
            $res['max_lun'] = $this->config['zhuan_max_lun'];
            $res['max_money'] = $this->config['zhuan_max_rmb'];
            
            if($pai['lun']>=$this->config['zhuan_max_lun']){
                $res['zhuan_limit'] = 1;
            }elseif($pai['money']>=$this->config['zhuan_max_rmb']){
                $res['zhuan_limit'] = 2;
            }else{
                $res['zhuan_limit'] = 0;
            }
            if($res['zhuan_limit']==0){
                $today = strtotime("today");
                $weekarray=array("日","一","二","三","四","五","六");
                for($i=1;$i<=7;$i++){
                    $zhuan_set = M('zhuanpai_set')->where('type='.$order['type'].' AND day = '.$i)->find();
                    //if($zhuan_set['is_show']==1){
                        $zhuan[$i]['is_show'] = $zhuan_set['is_show'];
                        $zhuan[$i]['days'] = $i;
                        $zhuan[$i]['time'] = $today+86400*$i;
                        $zhuan[$i]['time_1000'] = $zhuan[$i]['time']*1000;
                        $zhuan[$i]['date'] = date('Y-m-d',$zhuan[$i]['time']);
                        $zhuan[$i]['shouyi'] = round($res['money']*$zhuan_set['sy_rate']*0.01,2);
                        $zhuan[$i]['fee'] = round($res['money']*$zhuan_set['fw_rate']*0.01,2);
                        $zhuan[$i]['zhuan_money'] = intval($res['money'] + $zhuan[$i]['shouyi'] + $zhuan[$i]['fee']);
                        if(($pai['lun']-1)>$this->config['zhuan_max_lun']){
                            $zhuan[$i]['zhuan_limit'] = 1;
                        }elseif(($zhuan[$i]['zhuan_money']-$zhuan[1]['shouyi'])>$this->config['zhuan_max_rmb']){
                            $zhuan[$i]['zhuan_limit'] = 2;
                        }else{
                            $zhuan[$i]['zhuan_limit'] = 0;
                        }

                        $zhuan[$i]['all_num'] = M('zhuanpai_date')->where('type='.$order['type'].' AND zhuan_time='.$zhuan[$i]['time'])->getField('limit_num');
                        $zhuan[$i]['yi_num'] = M('pai')->where('status = 1 AND type='.$order['type'].' AND sourse = 2 AND yuji_time='.$zhuan[$i]['time'])->count();
                        if(intval($zhuan[$i]['all_num'])==0){
                            $z_data['type'] = $order['type'];
        	                $z_data['limit_num'] = 999;
        	                $z_data['zhuan_time'] = $zhuan[$i]['time'];
        	                $z_data['zhuan_date'] = date("Y-m-d",$zhuan[$i]['time']);
        	                M('zhuanpai_date')->add($z_data);
                            $zhuan[$i]['all_num'] = 999;
                        }
                        $zhuan[$i]['shengyu_num'] = $zhuan[$i]['all_num'] - $zhuan[$i]['yi_num'];
                        $zhuan[$i]['shengyu_num'] = $zhuan[$i]['shengyu_num']<=0 ? 0 : $zhuan[$i]['shengyu_num'];
                        
                        $week = intval(date('w',$zhuan[$i]['time']));
                        $zhuan[$i]['week'] = $weekarray[$week];
						
                        $zhuan[$i]['is_break'] = $week==0 ? $this->config['break_day7'] : $this->config['break_day'.$week];
                        
                    /*}else{
                        $zhuan[$i]['is_show'] = 2;
                        $zhuan[$i]['shouyi'] = round($res['money']*$zhuan_set['yj_rate']*0.01,2);
                    }*/
                }
            }
            foreach ($zhuan as $k=>$v){
                $zhuan_res[] = $v;
            }

            $data['status'] = 1;
            $data['info'] = '获取成功';
            $data['my_heo'] = $user['heo'] + $user['heo_bind'];
            $data['goods'] = $res;
            $data['zp_data'] = $zhuan_res;
            $this->ajaxReturn($data);
        }
    }
    /**
     * 提交转拍
     */
    public function submit_zhuanpai(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('pai_order')->where('id='.$id.' AND buy_uid='.$member_id)->find();
            if(!$order){
                $data['status'] = 3;
                $data['info'] = '无订单信息';
                $this->ajaxReturn($data);
            }
            if($order['status']!=3 || $order['stay_status']!=0){
                $data['status'] = 4;
                $data['info'] = '订单不能转拍';
                $this->ajaxReturn($data);
            }
            $days = intval(I('days'));
            if($days<=0 || $days>7){
                $data['status'] = 5;
                $data['info'] = '请选择正确的转拍日期';
                $this->ajaxReturn($data);
            }
            $zhuan_set = M('zhuanpai_set')->where('type='.$order['type'].' AND day = '.$days)->find();
            $is_show = intval(I('is_show'));
            if($is_show!=1 || $zhuan_set['is_show']!=1){
                $data['status'] = 5;
                $data['info'] = '当日不可转拍';
                $this->ajaxReturn($data);
            }
            $zhuan_limit = intval(I('zhuan_limit'));
            if($zhuan_limit!=0){
                $data['status'] = 5;
                $data['info'] = '商品不可转拍';
                $this->ajaxReturn($data);
            }
            $is_break = intval(I('is_break'));
            if($is_break!=2){
                $data['status'] = 5;
                $data['info'] = '当日为休息日，不可转拍';
                $this->ajaxReturn($data);
            }
            $shengyu_num = intval(I('shengyu_num'));
            if($shengyu_num<=0){
                $data['status'] = 5;
                $data['info'] = '商品不可转拍';
                $this->ajaxReturn($data);
            }
            
            if($order['source']==1){
                $pai = M('pai')->where('id='.$order['pai_id'])->field('pic,title,lun,money,type,content')->find();
            }
            elseif($order['source']==2){
                $pai = M('huan')->where('id='.$order['pai_id'])->field('pic,title,money,type,content')->find();
                $pai['lun'] = 0;
            }else{
                $data['status'] = 3;
                $data['info'] = '商品错误';
                return $data;
                exit;
            }
            
            $today = strtotime('today');
            $pro_data['member_id'] = $member_id;
			$pro_data['title'] = $pai['title'];
			$pro_data['type'] = $pai['type'];
			$pro_data['yuan_money'] = $order['yuan_money'];
			$pro_data['pic'] = $pai['pic'];
			$pro_data['content'] = $pai['content'];
			$pro_data['status'] = 1;
			$pro_data['sourse'] = 2;
			$pro_data['add_time'] = time();
			$pro_data['yijialv'] = $zhuan_set['yj_rate'];
			$pro_data['yongjin'] = $zhuan_set['fw_rate'];
            $pro_data['shouyilv'] = $pro_data['yijialv'] - $pro_data['yongjin'];
            $pro_data['yongjin_kou'] = round($order['yuan_money']*$zhuan_set['fw_rate']*0.01,2);
            if($pro_data['yongjin_kou']>($user['heo']+$user['heo_bind'])){
                $data['status'] = 6;
                $data['info'] = '您的HEO余额不足';
                $this->ajaxReturn($data);
            }
            $pro_data['yuji_time'] = $today + 86400*$days;
			$pro_data['shouyi'] = round($order['yuan_money'] * $pro_data['yijialv'] * 0.01,2);
			$pro_data['money'] = intval($pro_data['shouyi'] + $order['yuan_money']);
			/*
			$zhuan[$i]['shouyi'] = round($res['money']*$zhuan_set['sy_rate']*0.01,2);
			$zhuan[$i]['fee'] = round($res['money']*$zhuan_set['fw_rate']*0.01,2);
			$zhuan[$i]['zhuan_money'] = $res['money'] + $zhuan[$i]['shouyi'] + $zhuan[$i]['fee'];
			*/
			$pro_data['lun'] = $pai['lun'] + $days;
            //var_dump($pro_data);exit;
			$r = M('pai')->add($pro_data);
            if($r){
				M('pai_order')->where('id='.$id)->setField('stay_status',1);
				M('pai_order')->where('id='.$id)->setField('zhuanpai_id',$r);
				
				
				if($user['heo_bind']>0){
				    if($user['heo_bind']>=$pro_data['yongjin_kou']){
					    M('member')->where('member_id='.$member_id)->setDec('heo_bind',$pro_data['yongjin_kou']);
					    addFinance($member_id, 8, '转拍竞品支付服务费', $pro_data['yongjin_kou'], 2, 7);
					}else{
					    $cha = $pro_data['yongjin_kou'] - $user['heo_bind'];
					    M('member')->where('member_id='.$member_id)->setDec('heo_bind',$user['heo_bind']);
					    addFinance($member_id, 8, '转拍竞品支付服务费', $user['heo_bind'], 2, 7);
					    M('member')->where('member_id='.$member_id)->setDec('heo',$cha);
					    addFinance($member_id, 8, '转拍竞品支付服务费', $cha, 2, 6);
					}
				}else{
				    M('member')->where('member_id='.$member_id)->setDec('heo',$pro_data['yongjin_kou']);
				    addFinance($member_id, 8, '转拍竞品支付服务费', $pro_data['yongjin_kou'], 2, 6);
				}
				
				$data['status'] = 1;
				$data['info'] = '转拍成功';
				$this->ajaxReturn($data);
			}else{
				$data['status'] = -1;
				$data['info'] = '转拍失败';
				$this->ajaxReturn($data);
			}
        }
    }
	/*
     * 提货页面数据
     */
    public function submit_tihuo(){
		if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            $address_id = intval(I('address_id'));
            $is_zhihuan = intval(I('is_zhihuan'));
            
            if($id<=0 || $address_id<=0 || $is_zhihuan<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('pai_order')->where('id='.$id.' AND buy_uid='.$member_id)->find();
            if(!$order){
                $data['status'] = 3;
                $data['info'] = '无订单信息';
                $this->ajaxReturn($data);
            }
            if($order['status']!=3 || $order['stay_status']==1 || $order['stay_status']==2){
                $data['status'] = 4;
                $data['info'] = '订单不能提货';
                $this->ajaxReturn($data);
            }
            
            $address = M('address')->where('id='.$address_id.' AND member_id='.$member_id)->find();
            if(!$address){
                $data['status'] = 8;
                $data['info'] = '收货地址错误';
                $this->ajaxReturn($data);
            }
            
            $tihuo_data['member_id'] = $member_id;
			$tihuo_data['pai_id'] = $order['pai_id'];
			$tihuo_data['add_id'] = $address_id;
			$tihuo_data['order_id'] = $order['id'];
			$tihuo_data['money'] = $order['yuan_money'];
			$tihuo_data['add_time'] = time();
			$tihuo_data['status'] = 1;
			
			unset($address['id']);
            unset($address['is_default']);
            $address_record = M('address_record')->add($address);
            $tihuo_data['address_record_id'] =  $address_record;
            
            
            if($order['stay_status']==3){
                $is_zhihuan = 2;
                $tihuo_data['zhihuan_status'] = 1; 
                $tihuo_data['quan_ids'] = $order['quan_ids'];
                $tihuo_data['quan'] = $order['quan'];
            }
            
            $tihuo_data['is_zhihuan'] = $is_zhihuan;
			$r = M('tihuo')->add($tihuo_data);
			if($r){
				M('pai_order')->where('id='.$id)->setField('stay_status',2);
				M('pai_order')->where('id='.$id)->setField('tihuo_id',$r);
				
				$data['status'] = 1;
				$data['info'] = '提货成功';
				$this->ajaxReturn($data);
			}else{
				
				$data['status'] = 4;
				$data['info'] = '操作失败';
				$this->ajaxReturn($data);
			}
		}
	}
	/**
     * 获取我的提货订单列表
     */
    public function tihuo_list(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;

            $member_id = $this->user['member_id'];
            $where = 'member_id='.$member_id;
            
            $count      =  M('tihuo')->where($where)->count();
            $tihuo = M('tihuo')->where($where)->limit($num)->page($page)->order('status,id desc')->select();
            if($tihuo){
                foreach ($tihuo as $k=>$v){
                    $re = $this->get_tihuo($v['id'],$member_id);
                    if($re['status']==1){
                        $res[$k] = $re['info'];
                    }else{
                        $res = array();
                    }
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
            $tihuo = M('tihuo')->where('id='.$id.' AND member_id='.$member_id)->find();
            if($tihuo){
                if($tihuo['status']!=2){
                    $data['status'] = 7;
                    $data['info'] = '此订单无法确认收货';
                    $this->ajaxReturn($data);
                }
                $tihuo_data['id'] = $id;
    			$tihuo_data['status'] = 3;
    			$tihuo_data['shouhuo_time'] = time();
    			$r = M('tihuo')->save($tihuo_data);
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
            $tihuo = M('tihuo')->where('id='.$id.' AND member_id='.$member_id)->find();
            if($tihuo){
                if($tihuo['status']!=2 && $tihuo['status']!=3){
                    $data['status'] = 7;
                    $data['info'] = '订单无物流信息';
                    $this->ajaxReturn($data);
                }
				
				$wuliu = M('wuliu')->where('kuaidi_sn="'.$tihuo['kuaidi_sn'].'"')->find();
				if($wuliu){
					$data['status'] = 1;
					$data['data']['message'] = 'ok';
					$data['data']['data'] = json_decode($wuliu['data'],true);
					$data['data']['data']['com_name'] = kuaidi_name($data['data']['data']['com']);
					$this->ajaxReturn($data); 
				}else{
					$kuaidi_name = kuaidi_com($tihuo['kuaidi_name']);
					$res = $this->get_wuliu($kuaidi_name,$tihuo['kuaidi_sn']);
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
	

}