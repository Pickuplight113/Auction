<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use Think\Page;
class ZhihuanController extends CommonController {
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
    }
	/**
     * 获取我的置换券列表
     */
    public function quan_list(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;

            $member_id = $this->user['member_id'];
            $where = 'member_id='.$member_id;
            
            $status = intval(I('status'));
            if($status>0){
                $where.= 'status='.$status;
            }
            
            $count      =  M('quan')->where($where)->count();
            $list = M('quan')->where($where)->limit($num)->page($page)->order('status,id desc')->select();
            if($list){
                foreach ($list as $k=>$v){
                    $res[$k]['id'] = $v['id'];
                    $res[$k]['money'] = $v['money'];
                    $res[$k]['status'] = $v['status'];
                    
                    if($v['status']==1){
                        $res[$k]['status_name'] = '未使用'; 
                    }elseif($v['status']==2){
                        $res[$k]['status_name'] = '已使用'; 
                    }elseif($v['status']==3){
                        $res[$k]['status_name'] = '已过期'; 
                    }
                    
                    $res[$k]['add_time'] = $v['add_time'];
                    $res[$k]['add_time_1000'] = $v['add_time']*1000;
                    $res[$k]['add_date'] = date('Y.m.d',$v['add_time']);
                    $res[$k]['stop_time'] = $v['stop_time'];
                    $res[$k]['stop_time_1000'] = $v['stop_time']*1000;
                    $res[$k]['stop_date'] = date('Y.m.d',$v['stop_time']);
                    $res[$k]['end_time'] = $v['end_time'];
                    $res[$k]['end_time_1000'] = $v['end_time']*1000;
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
     * 获取置换说明
     */
    public function get_zhihuan_tip(){
        $data['status'] = 1;
        $data['info'] = $this->config['zhihuan_tip'];
        $this->ajaxReturn($data);
    }
    
    /**
     * 置换区商品
     */
    public function listing(){
        $member_id = $this->user['member_id'];
        
        $today = strtotime('today');
    
        
        $page = intval(I('page'));
        $page = $page == 0 ? 1 : $page;
        $num = intval(I('num'));
        $num = $num == 0 ? 10 : $num;
        $quan_id = intval(I('quan_id'));
        if($quan_id<=0){
		    $data['status'] = 2;
            $data['info'] = '参数错误';
            $this->ajaxReturn($data);
		}
		$quan = M('quan')->where('member_id='.$member_id.' AND id='.$quan_id)->find();
		if(!$quan){
		    $data['status'] = 3;
            $data['info'] = '参数错误';
            $this->ajaxReturn($data);
		}
        
		$status = intval(I('status'));
		$where='id>0 AND status=1 OR (status = 2 AND end_time>'.$today.')';
		if($status>0){
		    $where.= ' AND status='.$status;
		}
        $count      =  M('huan')->where($where)->count();
        $list =  M('huan')
            ->where($where)
            ->order("status")
            ->limit($num)->page($page)
            ->select();
        if($list){
            foreach($list as $k=>$v){
    		    $res[$k]['id'] = $v['id'];
    		    $res[$k]['title'] = $v['title'];
    		    $res[$k]['money'] = $v['money'];
    		    $res[$k]['status'] = $v['status'];
    		    $product['pic_arr'] = explode(",", $v['pic']);
    	        $res[$k]['pic'] = $this->config['oss_url'].$product['pic_arr'][0];
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
		
		$today = strtotime('today');
		$data['start_time'] = $today+$this->config['zhihuan_hour_start']*3600+$this->config['zhihuan_minute_start']*60;
		$data['start_time_1000'] = $data['start_time']*1000;
		$data['start_date'] = $this->config['zhihuan_hour_start'].':'.$this->config['zhihuan_minute_start'];
		$data['stop_time'] = $today+$this->config['zhihuan_hour_stop']*3600+$this->config['zhihuan_minute_stop']*60;
		$data['stop_time_1000'] = $data['stop_time']*1000;
		$data['stop_date'] = $this->config['zhihuan_hour_stop'].':'.$this->config['zhihuan_minute_stop'];
        
        $data['quan_id'] = $quan_id;
        $data['data'] = $res;
        $this->ajaxReturn($data);
		
    }
    /**
     * 获取单个置换区商品
     */
    public function get_zhihuan_info(){
        if(IS_POST){
            $member_id = $this->user['member_id'];
            $user = $this->user;
            $id = intval(I('id'));
            $quan_id = intval(I('quan_id'));
            if($quan_id<=0 || $id<=0){
    		    $data['status'] = 2;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
    		}
    		$quan = M('quan')->where('status = 1 AND member_id='.$member_id.' AND id='.$quan_id)->field('id,money,add_time,stop_time')->find();
    		if(!$quan){
    		    $data['status'] = 3;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
    		}
    		
    		$quan['add_time_1000'] = $quan['add_time']*1000;
    		$quan['add_date'] = date('Y.m.d',$quan['add_time']);
    		$quan['stop_time_1000'] = $quan['stop_time']*1000;
            $quan['stop_date'] = date('Y.m.d',$quan['stop_time']);
 
            $where = 'status IN (1,2) AND id='.$id;
            $huan =  M('huan')->where($where)->field('id,member_id,title,money,status,pic,content')->find();
            if(!$huan){
                $data['status'] = 4;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
            }
            $huan['pic_arr'] = explode(",", $huan['pic']);
            foreach ($huan['pic_arr'] as $k=>$v){
                $huan['pic_arr'][$k] = $this->config['oss_url'].$v;
            }
            $huan['content'] = str_replace('<img src="','<img src="'.$this->config['oss_url'],$huan['content']);
            $huan['seller'] = M('member')->where('member_id='.$huan['member_id'])->getField('nickname');
		    //是否为自己的商品
		    if($member_id == $huan['member_id']){
			    $huan['my_product'] = 1;
			}else{
			    $huan['my_product'] = 2;
			}
            
            $data['status'] = 1;
            $data['info'] = '获取成功';

			$today = strtotime('today');
    		$data['start_time'] = $today+$this->config['zhihuan_hour_start']*3600+$this->config['zhihuan_minute_start']*60;
    		$data['start_time_1000'] = $data['start_time']*1000;
    		$data['start_date'] = $this->config['zhihuan_hour_start'].':'.$this->config['zhihuan_minute_start'];
    		$data['stop_time'] = $today+$this->config['zhihuan_hour_stop']*3600+$this->config['zhihuan_minute_stop']*60;
    		$data['stop_time_1000'] = $data['stop_time']*1000;
    		$data['stop_date'] = $this->config['zhihuan_hour_stop'].':'.$this->config['zhihuan_minute_stop'];
    		if(time()<$data['start_time']){
    		    $data['huan_status'] = 2;
    		    $data['huan_statusname'] = '未开始';
    		}elseif(time()>$data['stop_time']){
    		    $data['huan_status'] = 3;
    		    $data['huan_statusname'] = '已结束';
    		}else{
    		    $data['huan_status'] = 1;
    		    $data['huan_statusname'] = '可置换';
    		}

            $data['product'] = $huan;
            $data['quan'] = $quan;
            $this->ajaxReturn($data);
            
        }
    }
    /*
     * 开始置换
     */
    public function submit_huan(){
        if(IS_POST){
    		$member_id = $this->user['member_id'];
            $user = $this->user;
            $today = strtotime('today');
            $id = intval(I('id'));
    		$quan_id = intval(I('quan_id'));
            if($quan_id<=0 || $id<=0){
    		    $data['status'] = 2;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
    		}
    		//置换券
    		$quan = M('quan')->where('status = 1 AND member_id='.$member_id.' AND id='.$quan_id)->find();
    		if(!$quan){
    		    $data['status'] = 3;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
    		}
    		//置换商品
            $where = 'id='.$id;
            $product = M('huan')->where($where)->find();
			if(!$product){
				$data['status'] = 3;
                $data['info'] = '无商品信息';
                $this->ajaxReturn($data);
			}
			//检测是否已被置换
			if($product['status']!=1){
				$data['status'] = -1;
				$data['info'] = '商品不可置换';
				$this->ajaxReturn($data);
			}
			//锁定竞品
			M('huan')->where($where)->setField('status',5);
			//检测是否在交易时间内
			$time = time();
			$start = $today+$this->config['zhihuan_hour_start']*3600+$this->config['zhihuan_minute_start']*60;
			$end = $today + $this->config['zhihuan_hour_stop']*3600+$this->config['zhihuan_minute_stop']*60;
			if($start>$time || $end<$time){
				$data['status'] = 2;
				$data['info'] = '不在置换时间内';
				M('huan')->where($where)->setField('status',$product['status']);
				$this->ajaxReturn($data);
			}
			
			//今日置换数量是否已达到限制
			$have_trade = M('pai_order')->where('buy_uid='.$member_id.' AND source=2 AND pp_time>'.$today)->count();
			if($have_trade>=$this->config['zhihuan_limit_num']){
				$data['status'] = 5;
				$data['info'] = '今日置换单数已达到限制，不能再次置换';
				M('huan')->where($where)->setField('status',$product['status']);
				$this->ajaxReturn($data);
			}
			//今日置换金额是否已达到限制
			$have_money = M('pai_order')->where('buy_uid='.$member_id.' AND source=2 AND pp_time>'.$today)->sum('yuan_money');
			$have_money = $have_money + $product['money'];
			if($have_money > $this->config['zhihuan_limit_money']){
				$data['status'] = 5;
				$data['info'] = '今日置换金额已达到限制，不能再次置换';
				M('huan')->where($where)->setField('status',$product['status']);
				$this->ajaxReturn($data);
			}
			
		    //只使用置换券
		    if($quan['money']>=$product['money']){
				$order_data['money'] = 0;
				$order_data['status'] = 3;
				$order_data['deal_time'] = $time;
		    }else{
		    //置换券 + C2C
		        $order_data['status'] = 1;
				$order_data['money'] = $product['money'] - $quan['money'];
		    }
		    $order_data['buy_uid'] = $member_id;
			$order_data['sell_uid'] = $product['member_id'];
			$order_data['type'] = $product['type'];
			$order_data['pai_id'] = $product['id'];
		    $order_data['yuan_money'] = $product['money'];
			$order_data['quan_money'] = $quan['money'];
			$order_data['quan_id'] = $quan_id;
			$order_data['source'] = 2;
			$order_data['pp_time'] = $time;
			$order_data['sn'] = $this->get_sn();
		    $r = M('pai_order')->add($order_data);
		    if($r){
				//置换品表改变状态
				$pro_data['id'] = $id;
				$pro_data['status'] = 2;
				$pro_data['end_time'] = $time;
				$pro_data['order_id'] = $r;
				M('huan')->save($pro_data);
				//置换券表改变状态
				$quan_data['id'] = $quan_id;
				$quan_data['status'] = 2;
				$quan_data['end_time'] = $time;
				$quan_data['huan_order_id'] = $r;
				M('quan')->save($quan_data);
				
				$data['status'] = 1;
				$data['info'] = '恭喜您，置换成功！';
				$data['order_id'] = $r;
				$this->ajaxReturn($data);
			}else{
				M('huan')->where($where)->setField('status',$product['status']);
				$data['status'] = 4;
				$data['info'] = '操作失败，请重试';
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