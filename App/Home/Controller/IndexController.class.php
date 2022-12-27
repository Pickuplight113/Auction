<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class IndexController extends CommonController {
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
    }
    /**
     * 测试
     */
    public function test(){

        var_dump('OK2');exit;
        
    }
    
    /**
     * 我的页面数据
     */
    public function user(){
		$info = $this->user;
		$member_id = $this->user['member_id'];
		
		$user['member_id'] = $info['member_id'];
		$user['code_id'] = $info['code_id'];
        $user['nickname'] = $info['nickname'];
		$user['vip_level'] = $info['vip_level'];
        $user['vip_name'] = level_name($info['vip_level']);
        $user['head'] = $this->config['oss_url'].$info['head'];
		
		if(strpos($info['head'],'http') !== false){ 
			$user['head'] =  $info['head'];
		}else{
			$user['head'] =  $this->config['oss_url'].$info['head'];
		}

        //竞拍收益 竞拍转拍差价扣除服务费
        /*
        $user_shouyi = M('pai')->where('shouyi>0 AND sourse=2 AND member_id='.$member_id)->field('shouyi,yongjin_kou')->select();
        foreach ($user_shouyi as $k=>$v){
            $user['shouyi']+= $v['shouyi'] - $v['yongjin_kou'];
        }
        $user['shouyi'] = $user['shouyi']=='' ? 0 : $user['shouyi'];
        */
        $user['shouyi'] = $info['pai_shouyi']<='0' ? 0 : $info['pai_shouyi'];
        
        //我的奖金 动态收益
        $user['jiangjin'] = M('dong_record')->where('member_id='.$member_id)->sum('yongjin');
        $user['jiangjin'] = $user['jiangjin']=='' ? 0 : $user['jiangjin'];
        //签到 数据
        $today = strtotime('today');
        $yesterday = $today-86400;
        $qd = M('qiandao')->where('member_id=' . $member_id.' AND add_time>'.$today)->find();
        if($qd){
            $user['is_sign'] = 1;
            $user['sign_thisday_jiangli'] = $qd['jiangli'];
            $user['sign_days'] = $qd['days'];
        }else{
            $user['is_sign'] = 0;
            $qd_yes = M('qiandao')->where('member_id=' . $member_id.' AND add_time>'.$yesterday)->find();
            if($qd_yes){
                $user['sign_thisday_jiangli'] = 0;
                $user['sign_days'] = $qd_yes['days'];
            }else{
                $user['sign_thisday_jiangli'] = 0;
                $user['sign_days'] = 0;
            }
        }
        $qd_leiji = M('qiandao')->where('member_id=' . $member_id)->sum('jiangli');
        $user['sign_leiji'] = floatval($qd_leiji);
		//绿色通道图标
		$today = strtotime('today');
		$days = $user['youxian_gays'];
		if($user['vip_level']>1){
		    $daili = 1;
		}else{
		    $daili = 0;
		}
		if($days>0 || $daili>0){
		    $user['tubiao_show'] = 1;
		}else{
		    $user['tubiao_show'] = 0;
		}
		
		$data['status']=1;
        $data['user']=$user;
        $this->ajaxReturn($data);
    }
    /**
     * 待操作事件提示
     */
    public function tips(){
        $today = strtotime('today');
        $time = time();
        $wu_start = $today + 15*3600 + 26*60 + 30; 
        $wu_stop = $today + 15*3600 + 27*60 + 30; 
        $wan_start = $today + 19*3600 + 26*60 + 30; 
        $wan_stop = $today + 19*3600 + 27*60 + 30; 
        if( ($time>=$wu_start && $time<=$wu_stop) || ($time>=$wan_start && $time<=$wan_stop) ){
            $data['status']=1;
            $data['buy_dzf'] = $data['sell_dfh'] = $data['shop_dzf'] = $data['shop_dqr'] = $data['buy_wei'] = $data['buy_dfh'] = 0;
            $this->ajaxReturn($data);
        }
        
        
        
		$member_id = $this->user['member_id'];
		
		$data['status']=1;
		//我的买单，待支付
		$data['buy_dzf'] = M('pai_order')->where('status=1 AND buy_uid='.$member_id)->count();
		//我的卖单，待放货
		$data['sell_dfh'] = M('pai_order')->where('status=2 AND sell_uid='.$member_id)->count();
		//商城订单，待支付
		$data['shop_dzf'] = M('jingjia_order')->where('status=1 AND member_id='.$member_id)->count();
		//商城订单，待确认
		$data['shop_dqr'] = M('jingjia_order')->where('status=3 AND member_id='.$member_id)->count();
		
		//我的买单，未转拍也未提货
		$data['buy_wei'] = M('pai_order')->where('status=3 AND stay_status=0 AND buy_uid='.$member_id)->count();
		//我的买单，待放货的
		$data['buy_dfh'] = M('pai_order')->where('status=2 AND buy_uid='.$member_id)->count();

        $this->ajaxReturn($data);
    }
    /**
     * 任务页面数据
     */
    public function renwu(){
		$info = $this->user;
		$member_id = $this->user['member_id'];
        $data['status']=1;
        $today = strtotime('today');
        $yesterday = $today-86400;
        $data['num'] = 3;
        //签到
        $qd = M('qiandao')->where('member_id=' . $member_id.' AND add_time>'.$today)->count();
        if($qd>0){
            $data['is_sign'] = 1;
            $data['num']--;
        }else{
            $data['is_sign'] = 2;
        }
        //邀请好友
        $data['is_yao'] = 2;
        //竞价商城下单
        $data['is_shop'] = 2;

        $this->ajaxReturn($data);
    }

    /**
     * 提交签到
     */
    public function submit_sign(){
		if(IS_POST){
            $member_id = $this->user['member_id'];
            $today = strtotime('today');
            $yesterday = $today-86400;
            $qd = M('qiandao')->where('member_id=' . $member_id.' AND add_time>'.$today)->find();
            if($qd){
                $data['status'] = 2;
                $data['info'] = '今天已签到，获得'.$qd['jiangli'].'绑定-HEO，已连续签到'.$qd['days'].'天。';
                $this->ajaxReturn($data);
            }else{
                $qdjl_1 = $this->config['qdjl_1']*10;
                $qdjl_2 = $this->config['qdjl_2']*10;
                $qdjl_3 = $this->config['qdjl_3']*10;
                $qdjl_4 = $this->config['qdjl_4']*10;
                //昨日是否签到
                $qd_yes = M('qiandao')->where('member_id=' . $member_id.' AND add_time>'.$yesterday)->find();
                if($qd_yes){
                    //昨日是否已签到6天
                    if($qd_yes['days']>=6){
                        $qd_data['jiangli'] = rand($qdjl_3,$qdjl_4)*0.1;
                    }else{
                        $qd_data['jiangli'] = rand($qdjl_1,$qdjl_2)*0.1;
                    }
                    $qd_data['days'] = $qd_yes['days']+1;
                }else{
                    $qd_data['jiangli'] = rand($qdjl_1,$qdjl_2)*0.1;
                    $qd_data['days'] = 1;
                }
                $qd_data['member_id'] = $member_id;
                $qd_data['add_time'] = time();
                $qd_data['add_date'] = date('Y-m-d',time());
                M('qiandao')->add($qd_data);
                
                M('Member')->where("member_id=" . $member_id)->setInc('heo', $qd_data['jiangli']);
				addFinance($member_id, 28, '签到奖励，连续第'.$qd_data['days'].'天', $qd_data['jiangli'], 1, 6);
                
                $qd_leiji = M('qiandao')->where('member_id=' . $member_id)->sum('jiangli');
                
                $data['status'] = 1;
                $data['jiangli'] = $qd_data['jiangli'];
                $data['days'] = $qd_data['days'];
                $data['sign_leiji'] = floatval($qd_leiji);
                
                $this->ajaxReturn($data);
            }
        }

		$data['status']=1;
		$data['info']='未确定';
		
        $this->ajaxReturn($data);
    }
    /**
     * 绿色通道页面数据
     */
    public function channel(){
		$member_id = $this->user['member_id'];
		$user = $this->user;
		$today = strtotime('today');
		$days = 0;
		$youxian_end_time = 0;
		$daili = 0;
		$shenqing_time = 0;
		
		$zige = 1; //1可申请  2不可申请
		$zige_type = 0;//不可申请原因  0无 1在绿色通道有效期内  2在绿色通道间隔期内 3已是代理
		
		if($user['vip_level']>1){
		    $daili = 1;
		    $zige = 2;
		    $zige_type = 3;
		}elseif($user['youxian_days']>0){
		    $days = $user['youxian_days'];
		    $zige = 2;
		    $zige_type = 1;
		    $youxian_end_time = date('Y-m-d H:i', ($today + $user['youxian_days']*86400));
		}else{
		    if($user['youxian_days']>0){
		        $shenqing_time = $today + ($user['youxian_days']+$this->config['youxian_days'])*86400;
		        if($shenqing_time>time()){
		            $zige = 2;
        		    $zige_type = 2;
		        }
		    }
		}
		
		$data['status']=1;

		$data['days'] = $days;
	    $data['daili'] = $daili;
	    $data['zige'] = $zige;
	    $data['zige_type'] = $zige_type;
	    $data['youxian_end_time'] = $youxian_end_time;
	    $data['shenqing_time'] = $shenqing_time;
	    
	    $data['heo']=$user['heo'];
	    $data['shenqing_heo']=$this->config['youxian_heo'];
	    $data['wenxin']=$this->config['youxian_wenxin'];
	    $data['tiaojian']=$this->config['youxian_tiaojian_tip'];
		$data['shuoming']=$this->config['youxian_tip'];
		$this->ajaxReturn($data);
		
		
		$trade_count = $is_new = 0;
		//资格检测 1有     2没有    3已申请   $zige
		//资格类型 1新用户(2次) 2无订单  3VIP      $type
		/*$zige = 2;
		$time = time()-24*3600;
		$shenqing = M('shenqing')->where('member_id='.$member_id.' AND youxiao=1 AND trade_id=0 AND ((status IN (2,3) AND handle_time>'.$time.') OR status=1)')->order('id desc')->find();
		$trade = M('pai_order')->where('buy_uid='.$member_id)->order('id desc')->find();
		$product = M('pai')->where('member_id='.$member_id.' AND status = 1')->count();
		if($trade){
		    $trade_count = M('pai_order')->where('buy_uid='.$member_id)->count();
			$cha = time()-$trade['pp_time'];
		}
		if($shenqing){
			$zige = 3;
		}else{
		    if($trade_count<$this->config['youxian_new_count']){
		        $is_new = 1;
		    }
			if($user['vip_level']>=2){
				$zige = 1;
				$type = 3;
			}elseif(!$trade || $is_new==1){
				$zige = 1;
				$type = 1;	
			}elseif($cha>=($this->config['yxpd']*3600)){
		        if($this->config['is_yadan']==1 && $product==0){
			        $zige = 1;
				    $type = 2;
			    }
			    if($this->config['is_yadan']==2){
			        $zige = 1;
				    $type = 2;
			    }
			}else{
		        $zige = 2;
			    $type = 0;	
			}	
		}

		$data['status']=1;
		
		$data['zige'] = $zige;
	    $data['type'] = $type;
		$today = strtotime('today');
		$shenqing_list = M('shenqing')->where('add_time>'.$today.' OR (add_time<'.$today.' AND (status=1 OR (status=2 AND trade_id=0)))')->order('id')->select();
		$data['all_people'] = count($shenqing_list); 
		if($shenqing){
		    $data['shenqing_status'] = $shenqing['status'];
    		$data['my_sort'] = array_search($member_id,array_column($shenqing_list, 'member_id'))+1;
    		$data['reason'] = $shenqing['reason'];
		}
		
		$data['baozhengjin']=$this->config['dongjie_yx'];
		$data['user_heo'] = $user['heo'];
		$data['tiaojian']=$this->config['youxian_tiaojian_tip'];
		$data['shuoming']=$this->config['youxian_tip'];
		
		
        $this->ajaxReturn($data);*/
    }
    /**
     * 提交申请绿色通道
     */
    public function submit_channel(){
        if (IS_POST){
            $member_id = $this->user['member_id'];
		    $user = $this->user;
		    $today = strtotime('today');
		    
		    $zige = 1; //1可申请  2不可申请
    		$zige_type = 0;//不可申请原因  0无 1在绿色通道有效期内  2在绿色通道间隔期内 3已是代理
    		
    		if($user['vip_level']>1){
    		    $daili = 1;
    		    $zige = 2;
    		    $zige_type = 3;
    		}elseif($user['youxian_days']>0){
    		    $days = $user['youxian_days'];
    		    $zige = 2;
    		    $zige_type = 1;
    		    $youxian_end_time = date('Y-m-d H:i', ($today + $user['youxian_days']*86400));
    		}else{
    		    if($user['youxian_days']>0){
    		        $shenqing_time = $today + ($user['youxian_days']+$this->config['youxian_days'])*86400;
    		        if($shenqing_time>time()){
    		            $zige = 2;
            		    $zige_type = 2;
    		        }
    		    }
    		}
		    
            
			if($zige!=1){
                $data['status'] = 2;
                $data['info'] = '您暂无资格';
                $data['zige_type'] = $zige_type;
                $this->ajaxReturn($data);
            }
            $youxian_heo = $this->config['youxian_heo'];
            
            if($user['heo'] < $youxian_heo){
                $data['status'] = 3;
                $data['info'] = 'HEO余额不足';
                $this->ajaxReturn($data);
            }

            $leader_data['member_id'] = $member_id;
			$leader_data['type'] = 4;
			$leader_data['days'] = 1;
			$leader_data['heo'] = $youxian_heo;
			$leader_data['zhitui_id'] = 0;
			$leader_data['add_time'] = time();
			$leader_data['old_youxian_end_time'] = $user['youxian_days']>0 ? ($today+$user['youxian_days']*86400) : 0;
	        $leader_data['new_youxian_end_time'] = time()+86400;
			$r = M('youxian_set_record')->add($leader_data);
            if($r){
                M('Member')->where("member_id=" . $member_id)->setInc('youxian_days', 1);
                
                //申请优先冻结保证金
				M('Member')->where("member_id=" . $member_id)->setDec('heo', $youxian_heo);
				addFinance($member_id, 35, '申请绿色通道消耗HEO', $youxian_heo, 2, 6);
                
                $data['status'] = 1;
                $data['info'] = '申请成功';
                $this->ajaxReturn($data);
            }else{
                $data['status'] = 0;
                $data['info'] = '服务器繁忙,请稍后重试';
                $this->ajaxReturn($data);
            }
            
        }
    }
    /*
     * 绿色通道申请记录
     */
    public function channel_record()
    {
        $page = intval(I('page'));
        $page = $page == 0 ? 1 : $page;
        $num = intval(I('num'));
        $num = $num == 0 ? 10 : $num;
        
		$member_id = $this->user['member_id'];
		$today = strtotime('today');
		
		$where = 'member_id='.$member_id;
        $count = M('shenqing')->where($where)->count();
        $list = M('shenqing')->where($where)->field('status,add_time,handle_time,reason')->order('add_time desc')->limit($num)->page($page)->select();
        foreach ($list as $k => $v) {
            
            $list[$k]['add_time_1000'] = $v['add_time']*1000;
            $list[$k]['add_date'] = date('Y-m-d H:i',$v['add_time']);
			if($v['status']==1){
			    $list[$k]['status_name'] = '审核中';
			}elseif($v['status']==2){
			    $list[$k]['handle_time_1000'] = $v['handle_time']*1000;
			    $list[$k]['handle_date'] = date('Y-m-d H:i',$v['handle_time']);
			    $list[$k]['status_name'] = '申请通过';
			}elseif($v['status']==3){
			    $list[$k]['handle_time_1000'] = $v['handle_time']*1000;
			    $list[$k]['handle_date'] = date('Y-m-d H:i',$v['handle_time']);
			    $list[$k]['status_name'] = '申请驳回';
			}
        }
        $data['status'] = 1;
		$data['total_num'] = $count;
		$data['num'] = $num;
		$yu = $count % $num;
		$total_page = intval($count/$num); 
		$data['total_page'] = $yu==0 ? $total_page : ($total_page+1);
		$data['page'] = $page;
		
        $data['data'] = $list;
        $this->ajaxReturn($data);
    }
    /**
     * 我的笔杆数据
     */
    public function team(){
        if (IS_POST){
            $member_id = intval(I('member_id'));
            if($member_id<=0){
                $data['status'] = 2;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
            }
            $user = M('member')->where('member_id='.$member_id)->find();
            if(!$user){
                $data['status'] = 3;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
            }
            $data['status'] = 1;
            //我的笔杆人数
            $data['zhitui_num'] = M('member')->where('pid='.$member_id)->count();
            //社区笔杆人数
            $data['team_num'] = get_my_team($member_id);
            //总成交额 团队当日的成交额
            $my_team = my_team_yeji($member_id);
			$data['team_money'] = $my_team['money'];
			$data['zhitui_hy'] = $my_team['hyd'];
            //笔杆列表
            $data['zhitui'] = array();
            $today = strtotime('today');
            $money = $zhuan_money = 0;
            $list = M('member')->field('member_id,nickname,is_hy')->where('pid='.$member_id)->select();
            foreach ($list as $k=>$v){
                $data['zhitui'][$k]['member_id'] = $v['member_id'];
                $money = 0;
                $zhuan_money = M('pai')->where("status = 1 AND sourse=2 AND member_id = ".$v['member_id'].' AND add_time<'.$today)->sum('money');
		    	$money = M('pai_order')->where("status = 3 AND buy_uid = ".$v['member_id'].' AND deal_time>'.$today)->sum('yuan_money');
		    	$data['zhitui'][$k]['nickname'] = $v['nickname'];
		    	$data['zhitui'][$k]['money'] = $money == '' ? 0 : $money;
		    	$data['zhitui'][$k]['team_num'] = get_my_team($v['member_id']);
		    	if(($money+$zhuan_money) > 0){
		    	    $data['zhitui'][$k]['is_hy'] = 1;
		    	}else{
		    	    $data['zhitui'][$k]['is_hy'] = 0;
		    	}
            }
            
            $this->ajaxReturn($data);
        }
    }
    /**
     * 商家入驻数据
     */
    public function businessman(){
        $member_id = $this->user['member_id'];
        $data['status']=1;
        $bus = M('businessman')->where('member_id='.$member_id)->order('id desc')->find();
        if($bus){
            $bus['add_time_1000'] = $bus['add_time']*1000;
            $bus['handle_time_1000'] = $bus['handle_time']*1000;
            unset($bus['id']);
            unset($bus['member_id']);
            
            $bus['idcard_pic1_path'] = $bus['idcard_pic1'];
            $bus['idcard_pic2_path'] = $bus['idcard_pic2'];
            $bus['company_pic_path'] = $bus['company_pic'];
            
            $bus['idcard_pic1'] = $this->config['oss_url'].$bus['idcard_pic1_path'];
            $bus['idcard_pic2'] = $this->config['oss_url'].$bus['idcard_pic2_path'];
            $bus['company_pic'] = $this->config['oss_url'].$bus['company_pic_path'];
            
            $data['bus']=$bus;
        }else{
            $data['info']='可以申请入驻';
        }
        $this->ajaxReturn($data);
    }
    /**
     * 商家入驻  提交申请
     */
    public function submit_businessman(){
        if (IS_POST){
            $member_id = $this->user['member_id'];
            $bus = M('businessman')->where('status IN (1,2) AND member_id='.$member_id)->find();
            if($bus){
                $data['status'] = 2;
                $data['info'] = '您已申请，请勿重复提交';
                $this->ajaxReturn($data);
            }
            $bus_data['member_id'] = $member_id;
            $bus_data['title'] = trim(I('title'));
            $bus_data['classify'] = trim(I('classify'));
            $bus_data['info'] = trim(I('info'));
            $bus_data['name'] = trim(I('name'));
            $bus_data['mobile'] = trim(I('mobile'));
            $bus_data['idcard_pic1'] = trim(I('idcard_pic1'));
            $bus_data['idcard_pic2'] = trim(I('idcard_pic2'));
            $bus_data['company_pic'] = trim(I('company_pic'));
            $bus_data['address'] = trim(I('address'));
            $bus_data['status'] = 1; 
            $bus_data['add_time'] = time(); 
            M('businessman')->add($bus_data);
            
            $data['status'] = 1;
            $data['info'] = '提交成功，请等待审核';
            $this->ajaxReturn($data);
        }
    }
    /**
     * 奖金池 推广奖
     */
    public function bonus_tuiguang(){
        if (IS_POST){
            
            $time = time();
            $thisday = date(Ymd,$time);
			$thisdate = date('m-d',$time);
            $member_id = $this->user['member_id'];
            
            //今日日期
            $today = strtotime('today');
            $yesterday = $today - 86400;
            
            $show_time = $today + $this->config['jiangjinchi_shi']*3600 + $this->config['jiangjinchi_fen']*60;
            if(time()>$show_time){
                //奖池金额 昨日竞价总额的百分比
                $order_money = M('jingjia_order')->where('status>2 AND fukuan_time>'.$yesterday.' AND fukuan_time<'.$today)->sum('price');
                $jiangchi = round($order_money * $this->config['jjc_jingjia_rate'] * 0.01 , 2);
                $jiangchi = $jiangchi + $this->config['jiangjinchi_tiao'];
            }else{
                $jiangchi = 0;
            }

            $stop_time = $today + 3600*22 + 60*30;
            //22:30点以前的当前排名
            if($time<=$stop_time){
                $mem_list = M('member')->where('status=1')->field('member_id,nickname,head')->select();
                foreach ($mem_list as $k=>$v){
                    $mem_list[$k]['zhitui'] = M('member')->where('status=1 AND pid='.$v['member_id'].' AND reg_time>'.$today)->count();
                    if($mem_list[$k]['zhitui']<=0){
                        unset($mem_list[$k]);
                    }
                }
                $dqpm = array_slice(arraySort($mem_list,'zhitui'),0,10);
                $zhitui_num = 0;
                $i = 1;
                foreach ($dqpm as $k=>$v){
                    $zhitui_num+=$v['zhitui'];
                    $dqpm[$k]['sort'] = $i;
                    $dqpm[$k]['nickname'] = substr_cut($v['nickname']);
                    $dqpm[$k]['head'] = $this->config['oss_url'].$v['head'];
                    $dqpm[$k]['money'] = 0;
                    $i++;
                }
                if($zhitui_num>0 && $jiangchi>0){
                    foreach ($dqpm as $k=>$v){
                        $dqpm[$k]['money'] = round(($v['zhitui']/$zhitui_num)*$jiangchi*0.5,2); 
                    }
                }
                $kaijiang = 0;
                $kaijiang_name = '今日未开奖';
            }else{
            //22:30点以后的当前排名
                $dqpm_list = M('jiangjinchi')->where('type=1 AND add_time>'.$today)->order('sort')->select();
                foreach ($dqpm_list as $k=>$v){
                    $dqpm[$k]['sort'] = $v['sort'];
                    $user = M('member')->where('member_id='.$v['member_id'])->field('nickname,head')->find();
                    $dqpm[$k]['nickname'] = substr_cut($user['nickname']);
                    $dqpm[$k]['head'] = $this->config['oss_url'].$user['head'];
                    $dqpm[$k]['zhitui'] = $v['zhitui'];
                    $dqpm[$k]['money'] = $v['money'];
                }
                $kaijiang = 1;
                $kaijiang_name = '今日已开奖';
            }
            //昨日排名
            $zrpm = [];
            $zrpm_list = M('jiangjinchi')->where('type=1 AND add_time>'.$yesterday.' AND add_time<'.$today)->order('sort')->select();
            foreach ($zrpm_list as $k=>$v){
                $zrpm[$k]['sort'] = $v['sort'];
                $user = M('member')->where('member_id='.$v['member_id'])->field('nickname,head')->find();
                $zrpm[$k]['nickname'] = substr_cut($user['nickname']);
                $zrpm[$k]['head'] = $this->config['oss_url'].$user['head'];
                $zrpm[$k]['zhitui'] = $v['zhitui'];
                $zrpm[$k]['money'] = $v['money'];
            }

            //我的推广
            $my_tui = M('member')->where('status=1 AND pid='.$member_id.' AND reg_time>'.$today.' AND reg_time<'.$stop_time)->count();
            
            
            $data['status'] = 1;
            $data['thisday'] = $thisday;
			$data['thisdate'] = $thisdate;
            $data['jiangchi'] = $jiangchi;
            $data['kaijiang_time'] = $stop_time;
            $data['kaijiang_time_1000'] = $stop_time*1000;
            $data['kaijiang'] = $kaijiang;
            $data['dqpm'] = $dqpm;
            $data['zrpm'] = $zrpm;
            $data['my_tui'] = $my_tui;
            $this->ajaxReturn($data);
        }
    }
    /**
     * 奖金池 参与奖
     */
    public function bonus_canyu(){
        if (IS_POST){
            $time = time();
            $thisday = date(Ymd,$time);
			$thisdate = date('m-d',$time);
            $member_id = $this->user['member_id'];
            
            //今日日期
            $today = strtotime('today');
            $yesterday = $today - 86400;
            
            $show_time = $today + $this->config['jiangjinchi_shi']*3600 + $this->config['jiangjinchi_fen']*60;
            if(time()>$show_time){
                //奖池金额 昨日竞价总额的百分比
                $order_money = M('jingjia_order')->where('status>2 AND fukuan_time>'.$yesterday.' AND fukuan_time<'.$today)->sum('price');
                $jiangchi = round($order_money * $this->config['jjc_jingjia_rate'] * 0.01 , 2);
                $jiangchi = $jiangchi + $this->config['jiangjinchi_tiao'];
            }else{
                $jiangchi = 0;
            }
            
            
            $stop_time = $today + 3600*20 + 60*30;
            //22:30点以前的当前排名
            if($time<=$stop_time){
                $mem_list = M('member')->where('status=1')->field('member_id,nickname,head')->select();
                foreach ($mem_list as $k=>$v){
                    
                    //活跃度 竞价加1，交割加5，竞拍加1
                    $jj_num = M('jingjia_order')->where('status=0 AND member_id='.$v['member_id'].' AND add_time>'.$today)->count();
                    $jg_num = M('jingjia_order')->where('status>0 AND member_id='.$v['member_id'].' AND add_time>'.$today)->count();
                    $jp_num = M('pai_order')->where('status=3 AND buy_uid='.$v['member_id'].' AND deal_time>'.$today)->count();
                    $mem_list[$k]['hyd'] = $jj_num + $jg_num*5 + $jp_num;
                    if($mem_list[$k]['hyd']<=0){
                        unset($mem_list[$k]);
                    }
                }
                $dqpm = array_slice(arraySort($mem_list,'hyd'),0,9);
                $hyd_num = 0;
                $i = 1;
                foreach ($dqpm as $k=>$v){
                    $hyd_num+=$v['hyd'];
                    $dqpm[$k]['sort'] = $i;
                    $dqpm[$k]['nickname'] = substr_cut($v['nickname']);
                    $dqpm[$k]['head'] = $this->config['oss_url'].$v['head'];
                    $dqpm[$k]['money'] = 0;
                    $i++;
                }
                if($hyd_num>0 && $jiangchi>0){
                    foreach ($dqpm as $k=>$v){
                        $dqpm[$k]['money'] = round(($v['hyd']/$hyd_num)*$jiangchi*0.5,2); 
                    }
                }
                $kaijiang = 0;
                $kaijiang_name = '今日未开奖';
            }else{
            //22:30点以后的当前排名
                $dqpm_list = M('jiangjinchi')->where('type=2 AND add_time>'.$today)->order('sort')->select();
                foreach ($dqpm_list as $k=>$v){
                    $dqpm[$k]['sort'] = $v['sort'];
                    $user = M('member')->where('member_id='.$v['member_id'])->field('nickname,head')->find();
                    $dqpm[$k]['nickname'] = substr_cut($user['nickname']);
                    $dqpm[$k]['head'] = $this->config['oss_url'].$user['head'];
                    $dqpm[$k]['hyd'] = $v['hyd'];
                    $dqpm[$k]['money'] = $v['money'];
                }
                $kaijiang = 1;
                $kaijiang_name = '今日已开奖';
            }
            //昨日排名
            $zrpm = [];
            $zrpm_list = M('jiangjinchi')->where('type=2 AND add_time>'.$yesterday)->order('sort')->select();
            foreach ($zrpm_list as $k=>$v){
                $zrpm[$k]['sort'] = $v['sort'];
                $user = M('member')->where('member_id='.$v['member_id'])->field('nickname,head')->find();
                $zrpm[$k]['nickname'] = substr_cut($user['nickname']);
                $zrpm[$k]['head'] = $this->config['oss_url'].$user['head'];
                $zrpm[$k]['hyd'] = $v['hyd'];
                $zrpm[$k]['money'] = $v['money'];
            }

            //我的活跃度
            $my_jj_num = M('jingjia_order')->where('status=0 AND member_id='.$member_id.' AND add_time>'.$today.' AND add_time<'.$stop_time)->count();
            $my_jg_num = M('jingjia_order')->where('status>0 AND member_id='.$member_id.' AND add_time>'.$today.' AND add_time<'.$stop_time)->count();
            $my_jp_num = M('pai_order')->where('status=3 AND buy_uid='.$member_id.' AND deal_time>'.$today.' AND deal_time<'.$stop_time)->count();
            $my_hyd = $my_jj_num + $my_jg_num*5 + $my_jp_num;
            
            
            $data['status'] = 1;
            $data['thisday'] = $thisday;
			$data['thisdate'] = $thisdate;
            $data['jiangchi'] = $jiangchi;
            $data['kaijiang_time'] = $stop_time;
            $data['kaijiang_time_1000'] = $stop_time*1000;
            $data['kaijiang'] = $kaijiang;
            $data['dqpm'] = $dqpm;
            $data['zrpm'] = $zrpm;
            $data['my_hyd'] = $my_hyd;
            $this->ajaxReturn($data);
        }
    }
    /**
     * 奖金池 获奖记录
     */
    public function bonus_record(){
        if (IS_POST){
            $member_id = $this->user['member_id'];
            
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;
            
            $where = 'member_id='.$member_id;
            $type = intval(I('type'));
            if($type>0){
                
                $where.= ' AND type='.$type;
            }
            $count      =  M('jiangjinchi')->where($where)->count();
            $list = M('jiangjinchi')->where($where)->limit($num)->page($page)->order('id desc')->field('type,money,add_time,add_date')->select();

            foreach ($list as $k=>$v){
                if($v['type']==1){
                    $list[$k]['type_name'] = '推广奖';
                }elseif($v['type']==2){
                    $list[$k]['type_name'] = '参与奖';
                }
                $list[$k]['add_time_1000'] = $v['add_time']*1000;
                $list[$k]['add_date'] = date('Y-m-d H:i',$v['add_time']);
                $list[$k]['thatday'] = $v['add_date'];
            }
            
            $data['status'] = 1;
            $data['total_num'] = $count;
    		$data['num'] = $num;
    		$yu = $count % $num;
    		$total_page = intval($count/$num); 
    		$data['total_page'] = $yu==0 ? $total_page : ($total_page+1);
    		$data['page'] = $page;
            $data['data'] = $list;
            $this->ajaxReturn($data);

        }
    }
    /**
     * 我的奖金页面数据
     */
    public function jiangjin_list(){
        if (IS_POST){
            $member_id = $this->user['member_id'];
            
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;
            
            $where = 'member_id='.$member_id;
            //今天时间戳
            $today = strtotime('today');
            //22点以后
            if(time()>= $today+22*3600){
    		    $where.=' AND add_time<'.$today;
    		}
    		//7点以前
    		if(time()< $today+7*3600){
    		    $where.=' AND add_time<'.($today-86400);
    		}
            
            $count      =  M('dong_record')->where($where)->group('member_id,add_date')->count();
            $list = M('dong_record')->where($where)->limit($num)->page($page)->order('id desc')->group('member_id,add_date')->field('*,SUM(yongjin) as shouyi')->select();
            if($list){
                foreach ($list as $k=>$v){
                    $res[$k]['id'] = $v['id']; 
                    $res[$k]['add_date'] = $v['add_date']; 
                    $res[$k]['money'] = $v['shouyi']; 
                }
            }else{
                $res = array();
            }
            
            //总奖金
            $jiangjin = M('dong_record')->where($where)->sum('yongjin');
            //冻结中
            $dongjie = M('dong_record')->where($where.' AND status=1')->sum('yongjin');
            //已释放
            $shifang = M('dong_record')->where($where.' AND status=2')->sum('yongjin');
            
            $data['status'] = 1;
            $data['total_num'] = $count;
    		$data['num'] = $num;
    		$yu = $count % $num;
    		$total_page = intval($count/$num); 
    		$data['total_page'] = $yu==0 ? $total_page : ($total_page+1);
    		$data['page'] = $page;
    		
    		$data['jiangjin'] = $jiangjin=='' ? 0 : $jiangjin;
    		$data['dongjie'] = $dongjie=='' ? 0 : $dongjie;
    		$data['shifang'] = $shifang=='' ? 0 : $shifang;
    		
            $data['data'] = $res;
            $this->ajaxReturn($data);

        }
    }
    /**
     * 我的奖金详情页面数据
     */
    public function jiangjin_info(){
        if (IS_POST){
            $member_id = $this->user['member_id'];
            
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
            }
            $record = M('dong_record')->where('member_id='.$member_id.' AND id='.$id)->find();
            if(!$record){
                $data['status'] = 3;
                $data['info'] = '参数错误！';
                $this->ajaxReturn($data);
            }
            $where = 'member_id='.$member_id.' AND add_date="'.$record['add_date'].'"'; 
            $count      =  M('dong_record')->where($where)->count();
            $list = M('dong_record')->where($where)->limit($num)->page($page)->order('id desc')->select();
            foreach ($list as $k=>$v){
                $res[$k]['id'] = $v['id']; 
                if($v['dai']==1){
			        $res[$k]['type_name'] = '直接分享';
			    }
			    if($v['dai']==2){
			        $res[$k]['type_name'] = '间接分享';
			    }
			    if($v['dai']>2 && $v['my_level']>1){
			        $res[$k]['type_name'] = '代理商奖励';
			    }
			    if($v['dai']>2 && $v['my_level']>4){
			        $res[$k]['type_name'] = '合伙人奖励';
			    }
			    if($v['status']==1){
			        $res[$k]['status_name'] = '冻结中';
			    }
			    if($v['status']==2){
			        $res[$k]['status_name'] = '已释放';
			    }
                $res[$k]['money'] = $v['yongjin']; 
                
                $res[$k]['add_date'] = $v['add_date'];
                $res[$k]['shifang_date'] = date('Y-m-d',$v['yuji_time']);
                $res[$k]['zhuanpai_id'] = $v['zhuanpai_id'];
                $res[$k]['info'] = '来自'.$v['dai'].'代用户'.$v['xiaji_phone'].$v['xiaji_name']; 
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
   
}