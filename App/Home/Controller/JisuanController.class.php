<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class JisuanController extends CommonController {

    public function test(){
        $today = strtotime('today');
	    $yesterday = $today - 86400;
        
        //发送通知短信
		$mobile_list = M('mobile_list')->select();
		$ye_time_start = $today + $this->config['ye_hour_start']*3600 + $this->config['ye_minute_start']*60;
		$pai_tongji_date['num'] = count($this->array_unset_tt(M('member_online')->where('add_time>='.($ye_time_start-5*60).' AND add_time<='.($ye_time_start+5*60))->select(),'member_id'));
		$online = $pai_tongji_date['num'];//在线人数
		$click = M('jilu')->where('add_time>'.($ye_time_start-5*60).' AND type=3')->count();
		$reg = M('member')->where('reg_time>'.$today)->count();
		$new = M('new_record')->where('add_time>'.$yesterday.' AND add_time<'.$today)->count();
		foreach ($mobile_list as $g=>$h){
			$json_param["online"] = $online;
			$json_param["click"] = $click;
			$json_param["reg"] = $reg;
			$json_param["new"] = $new;
            $params = array(
                "templateId" => "14977",
                "mobile" => $h['phone'],
        		"paramType" => "json",
                "params" => json_encode($json_param),
            );
    		$yy_res = $this->send_message($params);
    		if($yy_res['status']==1){
    		    //发送记录
                $yy_content = '在线人数'.$online.'，点击人数'.$click.'，当日注册人数'.$reg.',当日开通新用户'.$new;
                $yy_record['phone'] = $h['phone'];
                $yy_record['content'] = $yy_content;
                $yy_record['add_time'] = time();
                M('Mobile_record')->add($yy_record);
    		}
    		var_dump($yy_res);
		}
    }

    /**
     * 自动执行
     * 计划任务 每分钟执行一次
     */
	function auto_zhixing(){
	    $time = time();
	    $today = strtotime('today');
	    $yesterday = $today-86400;
	    if($time>($today+3600*22) || $time<($today+3600)){
	    //晚上10点至凌晨1点不执行
	        exit;
	    }
	    //竞价结束
	    $jingjia_list = M('jingjia')->where('status=1 AND (start_time+jp_time*60)<'.$time)->select();
	    foreach ($jingjia_list as $k=>$v){
	        $jingjia_order_end = M('jingjia_order')->where('jingjia_id='.$v['id'].' AND done_status=1')->find();
	        if($jingjia_order_end){
	            //最后一人成交，结束竞价，更新
                M('jingjia')->where('id='.$v['id'])->setField('status',2);
                M('jingjia')->where('id='.$v['id'])->setField('order_id',$jingjia_order_end['id']);
                M('jingjia')->where('id='.$v['id'])->setField('end_time',$time);
                //更新
                $jingjia_order['id'] = $jingjia_order_end['id'];
                $jingjia_order['status'] = 1;
                $jingjia_order['done_status'] = 3;
                $jingjia_order['zeng'] = $v['zeng'];
                $jingjia_order['jingpai_end_time'] = $time;
                M('jingjia_order')->save($jingjia_order);
                $jingjia_end_user['phone'] = M('member')->where('member_id='.$jingjia_order_end['member_id'])->getField('phone');
                //发送通知短信
                $jingjia_params = array(
                    "templateId" => "13604",
                    "mobile" => $jingjia_end_user['phone'],
            		"paramType" => "json",
                    "params" => json_encode($jingjia_params),
                );
        		$this->send_message($jingjia_params);
	        }else{
	            //没有拍的就下架
	            M('jingjia')->where('id='.$v['id'])->setField('status',3);
	            M('jingjia')->where('id='.$v['id'])->setField('start_time',0);
	        }
	        
	    }
	    //竞价未支付自动取消订单
	    $jingjia_order_list = M('jingjia_order')->where('done_status=3 AND status=1 AND jingpai_end_time<'.($time - $this->config["jingjia_pay_time"]*60))->select();
	    foreach ($jingjia_order_list as $k=>$v){
	        //扣除保证金
	        $jingjia_bzj = M('jingjia')->where('id='.$v['jingjia_id'])->getField('bzj');
	        M('member')->where('member_id='.$v['member_id'])->setDec('bzj',$jingjia_bzj);
	        //取消订单
	        M('jingjia_order')->where('id='.$v['id'])->setField('done_status',4);
			M('jingjia_order')->where('id='.$v['id'])->setField('status',0);
	        //封号
			$member_data['member_id'] = $v['member_id'];
			$member_data['status'] = 2;
			$member_data['reason'] = '竞价订单支付超时';
			$member_data['open_time'] = $time + $this->config['fenghao_time']*86400;
			M('Member')->save($member_data);
			//下发所有出价者收益
			$jingjia_order_lists = M('jingjia_order')->where('jingjia_id='.$v['jingjia_id'])->select();
			foreach ($jingjia_order_lists as $kk=>$vv){
				M('member')->where('member_id='.$vv['member_id'])->setInc('rmb',$vv['shouyi']);
				addFinance($v['member_id'], 14, '竞价商品溢价收益', $vv['shouyi'], 1, 3);
			}
	    }
	    //竞拍区
	    //早场
	    $zao_time_start = $today + $this->config['zao_hour_start']*3600 + $this->config['zao_minute_start']*60;
	    $zao_time_stop = $today + $this->config['zao_hour_stop']*3600 + $this->config['zao_minute_stop']*60;
	    //午场
	    $wu_time_start = $today + $this->config['wu_hour_start']*3600 + $this->config['wu_minute_start']*60;
	    $wu_time_stop = $today + $this->config['wu_hour_stop']*3600 + $this->config['wu_minute_stop']*60;
	    //夜场
	    $ye_time_start = $today + $this->config['ye_hour_start']*3600 + $this->config['ye_minute_start']*60;
	    $ye_time_stop = $today + $this->config['ye_hour_stop']*3600 + $this->config['ye_minute_stop']*60;
        //手动匹配的自动抢单
        if($time>=($zao_time_start - $this->config['youxian_time']*60) && $time<=$zao_time_start){
	        $zao_pp_list = M('pai')->where('status=1 AND type=1 AND is_pipei>0')->select();//手动匹配列表  早场
	    }
	    if($time>=($wu_time_start - $this->config['youxian_time']*60) && $time<=$wu_time_start){
	        $wu_pp_list = M('pai')->where('status=1 AND type=2 AND is_pipei>0')->select();//手动匹配列表   午场
	    }  
	    if($time>=($ye_time_start - $this->config['youxian_time']*60) && $time<=$ye_time_start){
	        $ye_pp_list = M('pai')->where('status=1 AND type=3 AND is_pipei>0')->select();//手动匹配列表   夜场
	    }
	    //手动匹配
	    $pp_list = array_merge((array)$zao_pp_list,(array)$wu_pp_list,(array)$ye_pp_list);
	    foreach ($pp_list as $k=>$v){
            $trade_data['buy_uid'] = $v['is_pipei'];
			$trade_data['sell_uid'] = $v['member_id'];
			$trade_data['type'] = $v['type'];
			$trade_data['pai_id'] = $v['id'];
			$trade_data['money'] = $v['money'];
			$trade_data['yuan_money'] = $v['money'];
			$trade_data['status'] = 1;
			$trade_data['source'] = 1;
			$trade_data['pp_time'] = $time;
			$trade_data['sn'] = $this->get_sn();
			//$trade_data['dongjie'] = $this->config['dongjie'];
			$trade_data['is_dai'] = $v['is_dai'];
			$r = M('pai_order')->add($trade_data);
			
			//竞拍品表改变状态
			$pro_data['id'] = $v['id'];
			$pro_data['status'] = 2;
			$pro_data['end_time'] = $time;
			$pro_data['order_id'] = $r;
			M('pai')->save($pro_data);
        }
		//每场竞拍开始5分钟时统计前后5分钟的在线人数
		//早场
		$pai_tongji_date = array();
		$zao_tongji_have = M('pai_tongji')->where('type=1 AND add_time>'.$today)->count();
		if($time>=($zao_time_start+5*60) && $zao_tongji_have<=0){
			$pai_tongji_date['type'] = 1;
			$pai_tongji_date['num'] = count($this->array_unset_tt(M('member_online')->where('add_time>='.($zao_time_start-5*60).' AND add_time<='.($zao_time_start+5*60))->select(),'member_id'));
	        $pai_tongji_date['add_time'] = time();
			$pai_tongji_date['add_date'] = date('Y-m-d',$time);
			M('pai_tongji')->add($pai_tongji_date);
	    }
		//午场
		$pai_tongji_date = array();
		$wu_tongji_have = M('pai_tongji')->where('type=2 AND add_time>'.$today)->count();
		if($time>=($wu_time_start+5*60) && $wu_tongji_have<=0){
			$pai_tongji_date['type'] = 2;
			$pai_tongji_date['num'] = count($this->array_unset_tt(M('member_online')->where('add_time>='.($wu_time_start-5*60).' AND add_time<='.($wu_time_start+5*60))->select(),'member_id'));
	        $pai_tongji_date['add_time'] = time();
			$pai_tongji_date['add_date'] = date('Y-m-d',$time);
			M('pai_tongji')->add($pai_tongji_date);
			
			//发送通知短信
			$mobile_list = M('mobile_list')->select();
			$online = $pai_tongji_date['num'];//在线人数
			$click = M('jilu')->where('add_time>'.($wu_time_start-5*60).' AND type=2')->count();
			$reg = M('member')->where('reg_time>'.$today)->count();
			$new = M('new_record')->where('add_time>'.$yesterday.' AND add_time<'.$today)->count();
			foreach ($mobile_list as $g=>$h){
    			$json_param["online"] = $online;
    			$json_param["click"] = $click;
    			$json_param["reg"] = $reg;
    			$json_param["new"] = $new;
                $params = array(
                    "templateId" => "14977",
                    "mobile" => $h['phone'],
            		"paramType" => "json",
                    "params" => json_encode($json_param),
                );
        		$yy_res = $this->send_message($params);
        		if($yy_res['status']==1){
        		    //发送记录
                    $yy_content = '在线人数'.$online.'，点击人数'.$click.'，当日注册人数'.$reg.',当日开通新用户'.$new;
                    $yy_record['phone'] = $h['phone'];
                    $yy_record['content'] = $yy_content;
                    $yy_record['add_time'] = time();
                    M('Mobile_record')->add($yy_record);
        		}
			}
	    }
		//晚场
		$pai_tongji_date = array();
		$wan_tongji_have = M('pai_tongji')->where('type=3 AND add_time>'.$today)->count();
		if($time>=($ye_time_start+5*60) && $wan_tongji_have<=0){
			$pai_tongji_date['type'] = 3;
			$pai_tongji_date['num'] = count($this->array_unset_tt(M('member_online')->where('add_time>='.($ye_time_start-5*60).' AND add_time<='.($ye_time_start+5*60))->select(),'member_id'));
	        $pai_tongji_date['add_time'] = time();
			$pai_tongji_date['add_date'] = date('Y-m-d',$time);
			M('pai_tongji')->add($pai_tongji_date);
			//发送通知短信
			$mobile_list = M('mobile_list')->select();
			$online = $pai_tongji_date['num'];//在线人数
			$click = M('jilu')->where('add_time>'.($ye_time_start-5*60).' AND type=3')->count();
			$reg = M('member')->where('reg_time>'.$today)->count();
			$new = M('new_record')->where('add_time>'.$yesterday.' AND add_time<'.$today)->count();
			foreach ($mobile_list as $g=>$h){
    			$json_param["online"] = $online;
    			$json_param["click"] = $click;
    			$json_param["reg"] = $reg;
    			$json_param["new"] = $new;
                $params = array(
                    "templateId" => "14977",
                    "mobile" => $h['phone'],
            		"paramType" => "json",
                    "params" => json_encode($json_param),
                );
        		$yy_res = $this->send_message($params);
        		if($yy_res['status']==1){
        		    //发送记录
                    $yy_content = '在线人数'.$online.'，点击人数'.$click.'，当日注册人数'.$reg.',当日开通新用户'.$new;
                    $yy_record['phone'] = $h['phone'];
                    $yy_record['content'] = $yy_content;
                    $yy_record['add_time'] = time();
                    M('Mobile_record')->add($yy_record);
        		}
			}
	    }
	    
	    //早场
		//开场前待售进场（正品售完时）
		//$zao_zheng_num = M('pai')->where('status=1 AND type=1 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->count();
        /*$zao_zheng_num = 0;
        if($time>=($zao_time_start - $this->config['daishou_tiqian']*60) && $time<$zao_time_start && $zao_zheng_num<=0){
	        $zao_dai_list = M('pai')->where('status=1 AND type=11 AND is_dai=0')->field('id,type')->select();
	        if($zao_dai_list){
	            foreach ($zao_dai_list as $k=>$v){
	                $zao_dai_data['id'] = $v['id'];
	                //$zao_dai_data['type'] = 1;
	                $zao_dai_data['is_dai'] = 1;
	                M('pai')->save($zao_dai_data);
	            }
	            sleep(2);
	        }
	    }*/
	    $zao_dai_num = M('pai')->where('status=1 AND type=11 AND is_dai=0')->count();
        if($time>=$zao_time_start && $time<($zao_time_start+5) && $zao_dai_num>0){
            $zao_dai_list2 = M('pai')->where('status=1 AND type=11 AND is_dai=0')->field('id,type,is_dai')->select();
	        if($zao_dai_list2){
	            foreach ($zao_dai_list2 as $k=>$v){
	                $zao_dai_data2['id'] = $v['id'];
	                $zao_dai_data2['type'] = 1;
	                $zao_dai_data2['is_dai'] = 1;
	                M('pai')->save($zao_dai_data2);
	            }
	        }
        }
	    
	    //午场
		//开场前待售进场（正品售完时）
		//$wu_zheng_num = M('pai')->where('status=1 AND type=2 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->count();
		/*$wu_zheng_num = 0;
        if($time>=($wu_time_start - $this->config['daishou_tiqian']*60) && $time<$wu_time_start && $wu_zheng_num<=0){
	        $wu_dai_list = M('pai')->where('status=1 AND type=12 AND is_dai=0')->field('id,type')->select();
	        if($wu_dai_list){
	            foreach ($wu_dai_list as $k=>$v){
	                $wu_dai_data['id'] = $v['id'];
	                //$wu_dai_data['type'] = 2;
	                $wu_dai_data['is_dai'] = 1;
	                M('pai')->save($wu_dai_data);
	            }
	            sleep(2);
	        }
	    }*/
	    $wu_dai_num = M('pai')->where('status=1 AND type=12 AND is_dai=0')->count();
        if($time>=$wu_time_start && $time<($wu_time_start+5) && $wu_dai_num>0){
            $wu_dai_list2 = M('pai')->where('status=1 AND type=12 AND is_dai=0')->field('id,type,is_dai')->select();
	        if($wu_dai_list2){
	            foreach ($wu_dai_list2 as $k=>$v){
	                $wu_dai_list2['id'] = $v['id'];
	                $wu_dai_list2['type'] = 2;
	                $wu_dai_list2['is_dai'] = 1;
	                M('pai')->save($wu_dai_list2);
	            }
	        }
        }
	    
	    //晚场
		//开场前待售进场（正品售完时）
		//$wan_zheng_num = M('pai')->where('status=1 AND type=3 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->count();
        /*$wan_zheng_num = 0;
        if($time>=($ye_time_start - $this->config['daishou_tiqian']*60) && $time<$ye_time_start && $wan_zheng_num<=0){
	        $wan_dai_list = M('pai')->where('status=1 AND type=13 AND is_dai=0')->field('id,type')->select();
	        if($wan_dai_list){
	            foreach ($wan_dai_list as $k=>$v){
	                $wan_dai_data['id'] = $v['id'];
	                //$wan_dai_data['type'] = 3;
	                $wan_dai_data['is_dai'] = 1;
	                M('pai')->save($wan_dai_data);
	            }
	            sleep(2);
	        }
	    }*/
	    $wan_dai_num = M('pai')->where('status=1 AND type=13 AND is_dai=0')->count();
        if($time>=$ye_time_start && $time<($ye_time_start+5) && $wan_dai_num>0){
            $wan_dai_list2 = M('pai')->where('status=1 AND type=13 AND is_dai=0')->field('id,type,is_dai')->select();
	        if($wan_dai_list2){
	            foreach ($wan_dai_list2 as $k=>$v){
	                $wan_dai_list2['id'] = $v['id'];
	                $wan_dai_list2['type'] = 3;
	                $wan_dai_list2['is_dai'] = 1;
	                M('pai')->save($wan_dai_list2);
	            }
	        }
        }
	    
	    
	    
        //买家超时付款，继续拍卖 （竞拍）
        /*$trade_chaoshi = M('pai_order')->where('status=1 AND source=1 AND pp_time<='.($time-$this->config['jiaoyi_minite']*60))->select();
        if($trade_chaoshi){
			foreach ($trade_chaoshi as $k=>$v){
				//竞拍品表改变状态
				$pro_hf_data['id'] = $v['pai_id'];
				$pro_hf_data['status'] = 1;
				$pro_hf_data['end_time'] = 0;
				$pro_hf_data['order_id'] = 0;
				$pro_hf_data['is_chao'] = 1;
				$pro_hf_data['chao_time'] = $time;
				M('pai')->save($pro_hf_data);
				M('pai_order')->where('id='.$v['id'])->setField('status',5);
				//封号
				$member_data['member_id'] = $v['buy_uid'];
				$member_data['status'] = 2;
				$member_data['reason'] = '竞拍订单支付超时';
				$member_data['open_time'] = $time + $this->config['fenghao_time']*86400;
				M('Member')->save($member_data);
				//M('Member')->where('member_id='.$v['buy_uid'])->setDec('bzj', $v['dongjie']);
				//扣除200保证金
				M('Member')->where("member_id=" . $v['buy_uid'])->setDec('heo', 200);
    			M('Member')->where("member_id=" . $v['sell_uid'])->setInc('heo', 200);
    			$product = M('pai')->where('id='.$v['pai_id'])->find();
    			addFinance($v['buy_uid'], 38, '抢拍订单【'.$product['title'].'】支付超时扣除保证金', 200, 2, 6);
    		    addFinance($v['sell_uid'], 37, '抢拍订单【'.$product['title'].'】对方支付超时获得保证金', 200, 1, 6);
    		    //返回继续拍卖
    		    $redis = new \redis();
                $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
                $redis->auth(C('REDIS_AUTH'));
				$redis_key = 'qpminpin'.$v['pai_id'];
				$redis->lpush($redis_key,1);
				//付款超时扣买家100贡献分
				$zige = M('member')->where('is_event = 1 AND member_id='.$v['buy_uid'])->count();
				if($zige>0){
    				$yuan_gxz = M('Member')->where("member_id=" . $v['buy_uid'])->getField('gxz');
    			    $gxz_data = array();
        			$gxz_data['member_id'] = $v['buy_uid'];
        			$gxz_data['gxz'] = 100;
        			$gxz_data['yuan_gxz'] = $yuan_gxz;
        			$gxz_data['xian_gxz'] = $yuan_gxz - $gxz_data['gxz'];
        			$gxz_data['type'] = 3;
        			$gxz_data['money_type'] = 2;
        			$gxz_data['title'] = $product['title'];
        			$gxz_data['info'] = '付款超时【'.$gxz_data['title'].'】扣除'.$gxz_data['gxz'].'贡献值';
        			$gxz_data['add_time'] = time();
        			$gxz_res = M('event_gxz')->add($gxz_data);
        			M('Member')->where("member_id=" . $gxz_data['member_id'])->setDec('gxz', $gxz_data['gxz']);
				}
			}
        }*/
        
        //卖家未放货，自动成交
        $weifanghuo = M('pai_order')->where('status=2 AND ping_time<'.($time-$this->config['jiaoyi_minite']*60))->select();
        foreach ($weifanghuo as $k=>$v){
            $order_data['id'] = $v['id'];
			$order_data['status'] = 3;
			$order_data['auto_deal'] = 1;
			$order_data['deal_time'] = time();
            M('pai_order')->save($order_data);
            if($v['source']==1){
                $product = M('pai')->where('id='.$v['pai_id'])->find();
                //释放买家冻结保证金
                /*
                M('Member')->where("member_id=" . $v['buy_uid'])->setInc('heo', $v['dongjie']);
    			M('Member')->where("member_id=" . $v['buy_uid'])->setDec('bzj', $v['dongjie']);
    			addFinance($v['buy_uid'], 34, '抢拍订单【'.$product['title'].'】完成释放保证金', $v['dongjie'], 1, 6);
    		    addFinance($v['buy_uid'], 34, '抢拍订单【'.$product['title'].'】完成释放保证金', $v['dongjie'], 2, 5);
    		    */
    		    //推荐奖励
    		    $buyer = M('Member')->where("member_id=" . $v['buy_uid'])->field('paidanjiang,pid,name,phone')->find();
    			if($this->config['is_tuijian_jiangli']==1 && $buyer['paidanjiang']<=0 && $this->config['tuijian_jiangli']>0 && $buyer['pid']>0){
    			    M('Member')->where("member_id=" . $v['buy_uid'])->setInc('paidanjiang', $this->config['tuijian_jiangli']);
    			    $leader = M('member')->where('member_id='.$buyer['pid'])->count();
    			    if($leader>0){
    			        M('Member')->where("member_id=" . $buyer['pid'])->setInc('heo', $this->config['tuijian_jiangli']);
    			        addFinance($buyer['pid'], 27, '直推用户'.$buyer['phone'].' '.$buyer['name'].'完成竞拍，给与推荐奖励', $this->config['tuijian_jiangli'], 1, 6);
    			        //722用户 给50贡献值
    			        $zige_leader = M('member')->where('is_event = 1 AND member_id='.$buyer['pid'])->find();
            			if($zige_leader){
            			    $gxz_data = array();
                			$gxz_data['member_id'] = $zige_leader['member_id'];
                			$gxz_data['gxz'] = 50;
                			$gxz_data['yuan_gxz'] = $zige_leader['gxz'];
                			$gxz_data['xian_gxz'] = $zige_leader['gxz'] + $gxz_data['gxz'];
                			$gxz_data['type'] = 5;
                			$gxz_data['money_type'] = 1;
                			$gxz_data['title'] = $product['title'];
                			$gxz_data['info'] = '直推用户'.$buyer['phone'].'完成交易【'.$gxz_data['title'].'】获得'.$gxz_data['gxz'].'贡献值';
                			$gxz_data['add_time'] = time();
                			$gxz_res = M('event_gxz')->add($gxz_data);
                			M('Member')->where("member_id=" . $gxz_data['member_id'])->setInc('gxz', $gxz_data['gxz']);
            			}
    			    }
    			}
    			//交易完成，奖励买家贡献值15分，每日4单
    			$gxz_count = M('event_gxz')->where('member_id='.$v['buy_uid'].' AND type=1 AND add_time>'.$today)->count();
    			$zige = M('member')->where('is_event = 1 AND member_id='.$v['buy_uid'])->count();
    			if($gxz_count<4 && $zige>0){
    			    $yuan_gxz = M('Member')->where("member_id=" . $v['buy_uid'])->getField('gxz');
    			    $gxz_data = array();
        			$gxz_data['member_id'] = $v['buy_uid'];
        			$gxz_data['gxz'] = 15;
        			$gxz_data['yuan_gxz'] = $yuan_gxz;
        			$gxz_data['xian_gxz'] = $yuan_gxz + $gxz_data['gxz'];
        			$gxz_data['type'] = 1;
        			$gxz_data['money_type'] = 1;
        			$gxz_data['title'] = $product['title'];
        			$gxz_data['info'] = '完成交易【'.$gxz_data['title'].'】获得'.$gxz_data['gxz'].'贡献值';
        			$gxz_data['add_time'] = time();
        			$gxz_res = M('event_gxz')->add($gxz_data);
        			M('Member')->where("member_id=" . $gxz_data['member_id'])->setInc('gxz', $gxz_data['gxz']);
    			}
    			
            }
        }
		//买家超时付款，继续售卖 （置换）
        $huan_chaoshi = M('pai_order')->where('status=1 AND source=2 AND pp_time<='.($time-$this->config['jiaoyi_minite']*60))->select();
        foreach ($huan_chaoshi as $k=>$v){
            //竞拍品表改变状态
			$pro_huan_data['id'] = $v['pai_id'];
			$pro_huan_data['status'] = 1;
			$pro_huan_data['end_time'] = 0;
			$pro_huan_data['order_id'] = 0;
			M('huan')->save($pro_huan_data);
            M('pai_order')->where('id='.$v['id'])->setField('status',5);
            //封号
            $member_data['member_id'] = $v['buy_uid'];
			$member_data['status'] = 2;
			$member_data['reason'] = '置换订单支付超时';
			$member_data['open_time'] = $time + $this->config['fenghao_time']*86400;
			M('Member')->save($member_data);
        }
        
        //申请优先 取消资格
        $youxian_list = M('shenqing')->where('youxiao = 1 AND status=2 AND trade_id=0 AND dongjie>0 AND handle_time<'.($time-86400))->select();
        foreach ($youxian_list as $k=>$v){
            M('Member')->where("member_id=" . $v['member_id'])->setDec('bzj', $v['dongjie']);
            M('shenqing')->where('id=' . $v['id'])->setField('youxiao', 2);
        }
		//VIP到期检测
		$daoqi = M('Member')->where('vip_flag=1 AND vip_end_time<='.$time)->select();
		foreach ($daoqi as $k=>$v){
			$jf_data['member_id'] = $v['member_id'];
			$jf_data['vip_level'] = 0;
			$jf_data['vip_flag'] = 0;
			M('Member')->save($jf_data);
		}
		

        file_put_contents("jiance.txt",date('Y-m-d H:i:s', time()). '实时检测完毕'.PHP_EOL, FILE_APPEND);
		echo '实时检测完毕'.PHP_EOL;
		exit;
	}
    
    /**
     * 奖金池结算
     * 每日10:30开奖
     * 计划任务 每天10:30
     */
    public function kaijiang(){
        $time = time();
        $thisday = date(Ymd,$time);
		$today = strtotime('today');
        $yesterday = $today-86400;
        $jiesuan_time = $today + 22*3600 + 30*60 -5; //每日 10:30 结算
		$record_last = M('jiangjinchi')->order('add_time desc')->find();
		if (strtotime($record_last['add_time']) >= $today || $jiesuan_time > $time) {
			file_put_contents("kaijiang.txt",date('Y-m-d H:i:s', time()). '奖金池开奖无效'.PHP_EOL, FILE_APPEND);
			echo '奖金池开奖无效'.PHP_EOL;
			exit;
		}
		//奖池金额 昨日竞价总额的百分比
        $order_money = M('jingjia_order')->where('status>2 AND fukuan_time>'.$yesterday.' AND fukuan_time<'.$today)->sum('price');
        $jiangchi_yuan = round($order_money * $this->config['jjc_jingjia_rate'] * 0.01 , 2);
        $jiangchi = $jiangchi_yuan + $this->config['jiangjinchi_tiao'];
        if($jiangchi<=0){
            file_put_contents("kaijiang.txt",date('Y-m-d H:i:s', time()). '奖金池金额为0'.PHP_EOL, FILE_APPEND);
			echo '奖金池金额为0'.PHP_EOL;
			exit;
        }
        
        //筛选前10名推广奖、参与奖
		$mem_list = M('member')->where('status=1')->field('member_id,nickname,head')->select();
        foreach ($mem_list as $k=>$v){
            //直推人数
            $zhitui = M('member')->where('status=1 AND pid='.$v['member_id'].' AND reg_time>'.$today)->count();
            if($zhitui>0){
                $tui_list[$k] = $v;
                $tui_list[$k]['zhitui'] = $zhitui;
            }
            //活跃度 竞价加1，交割加5，竞拍加1
            $jj_num = M('jingjia_order')->where('status=0 AND member_id='.$v['member_id'].' AND add_time>'.$today)->count();
            $jg_num = M('jingjia_order')->where('status>0 AND member_id='.$v['member_id'].' AND add_time>'.$today)->count();
            $jp_num = M('pai_order')->where('status=3 AND buy_uid='.$v['member_id'].' AND deal_time>'.$today)->count();
            $hyd = $jj_num + $jg_num*5 + $jp_num;
            if($hyd>0){
                $canyu_list[$k] = $v;
                $canyu_list[$k]['hyd'] = $hyd;
            }
        }
        //推广奖 记录
        $tui_list = array_slice(arraySort($tui_list,'zhitui'),0,10);
        $zhitui_num = 0;
        $i = 1;
        foreach ($tui_list as $k=>$v){
            $zhitui_num+=$v['zhitui'];
            $tui_list[$k]['money'] = 0;
            $tui_list[$k]['sort'] = $i;
            $i++;
        }
        if($zhitui_num>0){
            foreach ($tui_list as $k=>$v){
                $tui_data['type'] = 1;
                $tui_data['sort'] = $v['sort'];
                $tui_data['member_id'] = $v['member_id'];
                $tui_data['zhitui'] = $v['zhitui'];
                $tui_data['money'] = round(($v['zhitui']/$zhitui_num)*$jiangchi*0.5,2);
                $tui_data['add_time'] = $time;
                $tui_data['add_date'] = $thisday;
                $tui_data['jiangjinchi'] = $jiangchi;
                $tui_data['jiangjinchi_yuan'] = $jiangchi_yuan;
                $tui_data['jiangjinchi_tiao'] = $this->config['jiangjinchi_tiao'];
                M('jiangjinchi')->add($tui_data);
                if($tui_data['money']>0){
                    M('Member')->where("member_id=" . $v['member_id'])->setInc('rmb', $tui_data['money']);
        			addFinance($v['member_id'], 29, '第'.$thisday.'期，推广奖',$tui_data['money'], 1, 3);
                }
            }
            file_put_contents("kaijiang.txt",date('Y-m-d H:i:s', time()). '推广奖记录完毕，累计'.$zhitui_num.'名新直推'.PHP_EOL, FILE_APPEND);
			echo '推广奖记录完毕，累计'.$zhitui_num.'名新直推'.PHP_EOL;
        }else{
            file_put_contents("kaijiang.txt",date('Y-m-d H:i:s', time()). '今日无新直推'.PHP_EOL, FILE_APPEND);
			echo '今日无新直推'.PHP_EOL;
        }
		//参与奖 记录
		$canyu_list = array_slice(arraySort($canyu_list,'zhitui'),0,10);
        $hyd_num = 0;
        $i = 1;
        foreach ($canyu_list as $k=>$v){
            $hyd_num+=$v['hyd'];
            $canyu_list[$k]['money'] = 0;
            $canyu_list[$k]['sort'] = $i;
            $i++;
        }
        if($hyd_num>0){
            foreach ($canyu_list as $k=>$v){
                $canyu_data['type'] = 2;
                $canyu_data['sort'] = $v['sort'];
                $canyu_data['member_id'] = $v['member_id'];
                $canyu_data['hyd'] = $v['hyd'];
                $canyu_data['money'] = round(($v['hyd']/$hyd_num)*$jiangchi*0.5,2);
                $canyu_data['add_time'] = $time;
                $canyu_data['add_date'] = $thisday;
                $canyu_data['jiangjinchi'] = $jiangchi;
                $canyu_data['jiangjinchi_yuan'] = $jiangchi_yuan;
                $canyu_data['jiangjinchi_tiao'] = $this->config['jiangjinchi_tiao'];
                M('jiangjinchi')->add($canyu_data);
                if($canyu_data['money']>0){
                    M('Member')->where("member_id=" . $v['member_id'])->setInc('rmb', $canyu_data['money']);
        			addFinance($v['member_id'], 30, '第'.$thisday.'期，参与奖',$canyu_data['money'], 1, 3);
                }
            }
            file_put_contents("kaijiang.txt",date('Y-m-d H:i:s', time()). '参与奖记录完毕，累计'.$hyd_num.'活跃度'.PHP_EOL, FILE_APPEND);
			echo '参与奖记录完毕，累计'.$hyd_num.'活跃度'.PHP_EOL;
        }else{
            file_put_contents("kaijiang.txt",date('Y-m-d H:i:s', time()). '今日无活跃度用户'.PHP_EOL, FILE_APPEND);
			echo '今日无活跃度用户'.PHP_EOL;
        }
        file_put_contents("kaijiang.txt",date('Y-m-d H:i:s', time()). '奖金池结算完毕'.PHP_EOL, FILE_APPEND);
		echo '奖金池结算完毕'.PHP_EOL;
    }
    
    /**
     * 登录过期时间 定期清除 token 
     * 计划任务 每12小时执行一次 3点，15点
     */
    public function clear_token(){
        $time = time();
		$today = strtotime('today');
		//3天过期时间
		$stay_time = 86400*3;
		$user_list = M('member')->where('login_time>0')->field('member_id,login_time')->select();
		foreach ($user_list as $k=>$v){
		    if(($v['login_time']+$stay_time)<$time){
		        M('member')->where('member_id='.$v['member_id'])->setField('token','');
		    }
		}
    }
    
    /**
     * 更新级别
     * 计划任务 每日 22:05
     */
    public function dengji(){
        $time = time();
		$today = strtotime('today');
		$jisuan_time = $today + 22*3600;
		if ($jisuan_time > time()) {
			file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '级别确定无效'.PHP_EOL, FILE_APPEND);
			echo '级别确定无效'.PHP_EOL;
			exit;
		}
		
		$dengji_count = M('dengji_record')->where('add_time>'.$today)->count();
		if($dengji_count>0){
		    file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '级别已确定'.PHP_EOL, FILE_APPEND);
			echo '级别已确定'.PHP_EOL;
			exit;
		}else{
		    $dj_data['add_time'] = time();
		    $dj_data['add_date'] = date('Y-m-d');
		    M('dengji_record')->add($dj_data);
		}
		

		//查询活跃
		$user_list = M('member')->where('vip_flag=0 AND member_id<>8')->field('member_id,vip_level,vip_flag')->select();

		foreach($user_list as $k=>$v){

			$mem_data['member_id'] = $v['member_id'];
			$mem_data['huoyue_team'] = 0;
			$mem_data['is_hy'] = 0;
			$mem_data['vip_level'] = 0;
			M('member')->save($mem_data);
			
			
			$pai_count = M('pai')->where('status = 1 AND sourse=2 AND member_id='.$v['member_id'].' AND yuji_time>='.$today )->count();
			
			$money = 0;
			//$money+= M('pai')->where("status = 1 AND sourse=2 AND member_id = ".$v['member_id'].' AND add_time<'.$today.' AND yuji_time>='.$today)->sum('money');
			$money= M('pai_order')->where("status = 3 AND buy_uid = ".$v['member_id'].' AND deal_time>'.$today)->sum('yuan_money');
			
			if($money>0){
				M('member')->where('member_id='.$v['member_id'])->setField('is_hy',1);
				if($v['vip_flag']==0){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',1);
					
				}
				$path = M('member')->where('member_id='.$v['member_id'])->getField('dai_path'); 
				if($path){
					$id_arr = explode(',',$path);
					foreach($id_arr as $m=>$n){
					    if($n){
    						$leader = M('member')->where('member_id='.$n)->count();
    						if($leader){
    							M('member')->where('member_id='.$n)->setInc('huoyue_team',1);
    						}
					    }
					}
				} 
			}
		}
		
		
		
		//活跃者
		$user_hy = M('member')->where('is_hy=1 AND vip_flag=0')->field('member_id,vip_level,pid,huoyue_team')->order('member_id desc')->select();
		foreach($user_hy as $k=>$v){
			//三条线有单
			$zhitui_num =  M('member')->where('huoyue_team>0 AND pid='.$v['member_id'])->count();
			if($zhitui_num>=3){
				$team_num = $this->my_team_huoyue($v['member_id']);
				//区代理
				if($team_num>=$this->config['hyd_v2']){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',2); 
				}
				//市代理  3名 区代理
			    $zhitui_v2 = M('member')->where('vip_level>=2 AND pid='.$v['member_id'])->count();
				if($zhitui_v2>=3){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',3); 
				}
				//省代理  3名 市代理
			    $zhitui_v3 = M('member')->where('vip_level>=3 AND pid='.$v['member_id'])->count();
				if($zhitui_v3>=3){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',4); 
				}
			    //初级合伙人 3名 省级代理人
			    $zhitui_v4 = M('member')->where('vip_level>=4 AND pid='.$v['member_id'])->count();
			    if($zhitui_v4>=$this->config['hyd_v5']){
			        M('member')->where('member_id='.$v['member_id'])->setField('vip_level',5); 
			    }
			    //高级合伙人 3名 初级合伙人
			    $zhitui_v5 = M('member')->where('vip_level>=5 AND pid='.$v['member_id'])->count();
			    if($zhitui_v5>=$this->config['hyd_v6']){
			        M('member')->where('member_id='.$v['member_id'])->setField('vip_level',6);
			    }
			}
		}
		//记录
		file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '级别确定完毕'.PHP_EOL, FILE_APPEND);
		echo '级别确定完毕'.PHP_EOL;
    }
    
    /**
     * 动态奖励
     * 今日计算冻结收益，转拍T+N那天释放
     * 计划任务 每日 22:10
     */
    public function dong(){
        $time = time();
		$today = strtotime('today');
        $yesterday = $today-86400;
        $tomorrow = $today+86400;
        $jiesuan_time = $today + 22*3600 + 9*60; //每日 22:10后 结算
        $jiesuan_time_end = $today + 22*3600 + 11*60;

		$dong_record_last2 = M('dong_record')->where('add_time>'.$today)->count();
		if($dong_record_last2 || $jiesuan_time >= $time){
		    file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '冻结无效2'.PHP_EOL, FILE_APPEND);
			echo '冻结无效2'.PHP_EOL;
			exit;
		}
		
		//今日转拍的订单
		$zhuanpai_list = M('pai')->where('sourse=2  AND yongjin_kou>0 AND add_time>='.$today)->select();
		
		foreach($zhuanpai_list as $k=>$v){
			$user = M('member')->where('member_id='.$v['member_id'])->field('member_id,phone,name,vip_level,pid,dai_path')->find();
    		if($user['dai_path'] == '' || $user['dai_path'] == null || empty($user['dai_path'])){
    		    continue;
    		}
    		$path_arr = explode(',',$user['dai_path'] );
    		$path_arr = array_reverse($path_arr);
    		$path_num = count($path_arr);
    		if($path_num<=1){
    		    continue;
    		}
    		$father = array();
    		$res = array();
    		for($dai=1;$dai<$path_num;$dai++){
    		    if(intval($path_arr[$dai])>0){
    		        if($path_arr[$dai]==8){
    		            continue;
    		        }
					$father[$dai] = M('member')->where('member_id='.$path_arr[$dai])->field('member_id,phone,name,vip_level,pid')->find();
					if($dai>1){
						$res[$dai] = $this->set_dongtai($path_arr[$dai],$user['member_id'],$v['yongjin_kou'], $dai,$father[$dai]['vip_level'],$father[$dai-1]['vip_level'],$res[$dai-1]['vip_level_max'],$res[$dai-1]['rate_max'],$res[$dai-1]['is_jie'],$v['id'],$v['yuji_time']);
					}else{
						$res[$dai] = $this->set_dongtai($path_arr[$dai],$user['member_id'],$v['yongjin_kou'], $dai,$father[$dai]['vip_level'],$user['vip_level'],0,0,0,$v['id'],$v['yuji_time']);
					}
				}
    		    
    		}
    		
    		//增加竞拍收益
            M('member')->where('member_id='.$v['member_id'])->setInc('pai_shouyi',($v['shouyi']-$v['yongjin_kou']));
		}
		file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '动态冻结完毕'.PHP_EOL, FILE_APPEND);
		echo '动态冻结完毕'.PHP_EOL;
    }
	/*
     * 计算动态收益
     * $member_id 上级ID
     * $user_id   原始ID
     * $shouyi    原始收益
     * $dai       上级代数
     * $vip_level 上级代理级别
	 * $vip_level_xia 上级代理的下级的级别
	 * $vip_level_max 所有上级代理中最大级别
	 * $rate_max 所有上级代理中最大级别的奖励利率
	 * $is_jie 是否已结算平级（越级）
     * $zhuanpai_id    转拍ID
     */
    public function set_dongtai($member_id,$user_id,$shouyi,$dai,$vip_level,$vip_level_xia,$vip_level_max,$rate_max,$is_jie,$zhuanpai_id,$yuji_time){
        $today = strtotime('today');
		$xiaji = M('Member')->where('member_id='.$user_id)->field('phone,name,vip_level')->find();
		switch ($vip_level){
			case 0://散客
			  $vip_level_max = $xiaji['vip_level'];
			  //$is_jie = 1;
			  break;
			case 1://拍客 拿一代手续费的10%，二代手续费的5%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  $vip_level_max = $xiaji['vip_level'] > $vip_level_max ? $xiaji['vip_level'] : $vip_level_max;
			  break;
			case 2://vip1 拿一代手续费的10%，二代手续费的5%，级差8%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  if($vip_level>$vip_level_max || $rate_max < $this->config['dong_v2_rate']){
				  $yongjin+= round($shouyi*($this->config['dong_v2_rate']-$rate_max)*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = $this->config['dong_v2_rate'];
			  }
			  break;
			case 3://vip2 拿一代手续费的10%，二代手续费的5%，级差%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  if($vip_level>$vip_level_max || $rate_max<$this->config['dong_v3_rate']){
				  $yongjin+= round($shouyi*($this->config['dong_v3_rate']-$rate_max)*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = $this->config['dong_v3_rate'];
			  }
			  break;
			case 4://vip3 拿一代手续费的10%，二代手续费的5%，级差
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  if($vip_level>$vip_level_max || $rate_max<$this->config['dong_v4_rate']){
				  $yongjin+= round($shouyi*($this->config['dong_v4_rate']-$rate_max)*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = $this->config['dong_v4_rate'];
			  }
			  break;
			case 5://初级合伙人 拿一代手续费的10%，二代手续费的5%，团队12%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  /*if($vip_level>$vip_level_max || $rate_max<12){
				  $yongjin+= round($shouyi*(12-$rate_max)*$this->config['dong_v5_rate']*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = 12;
			  }*/
			  break;
			case 6://高级合伙人 拿一代手续费的10%，二代手续费的5%，团队15%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  /*if($vip_level>$vip_level_max || $rate_max<15){
				  $yongjin+= round($shouyi*(15-$rate_max)*$this->config['dong_v6_rate']*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = 15;
			  }*/
			  break;
		}
		$chong = M('Dong_record')->where('zhuanpai_id='.$zhuanpai_id.' AND member_id='.$member_id.' AND xiaji_id='.$user_id.' AND add_time>'.$today)->count();
        if($yongjin>0.01 && $chong==0){
			$now_date = date('Y-m-d', time());//现在日期
			//载入数据
			$dong_record['zhuanpai_id'] = $zhuanpai_id;
			$dong_record['member_id'] = $member_id;
			$dong_record['xiaji_id'] = $user_id;
			$dong_record['xiaji_phone'] = $xiaji['phone'];
			$dong_record['xiaji_name'] = $xiaji['name'];
			$dong_record['yongjin'] = $yongjin;
			$dong_record['dai'] = $dai;
			$dong_record['xiaji_level'] = $xiaji['vip_level'];
			$dong_record['my_level'] = $vip_level;
			$dong_record['vip_level_xia'] = $vip_level_xia;
			$dong_record['vip_level_max'] = $vip_level_max;
			$dong_record['rate_max'] = $rate_max;
			$dong_record['is_jie'] = $is_jie;
			$dong_record['add_time'] = time();
			$dong_record['add_date'] = $now_date;
			$dong_record['status'] = 1;
			$dong_record['yuji_time'] = $yuji_time;

			M('Dong_record')->add($dong_record);
        }
		//$res['yongjin']=$yongjin;
		//$res['vip_level']=$vip_level;
		//$res['vip_level_xia']=$vip_level_xia;
		$res['vip_level_max']=$vip_level_max;
		$res['rate_max']=$rate_max;
		$res['is_jie']=$is_jie;
		return $res;
		
    }
    
    /**
     * 动态奖励
     * 今日计算冻结收益，转拍T+N那天释放
     * 计划任务 每日 22:10
     */
    public function dong123(){
        $time = time();
		//$today = strtotime('today');
		$today = 1623168000;
        $yesterday = $today-86400;
        $tomorrow = $today+86400;
        /*$jiesuan_time = $today + 22*3600; //每日 22:00后 结算
		$dong_record_last = M('dong_record')->order('add_time desc')->find();
		if ($dong_record_last['add_time'] >= $today || $jiesuan_time >= $time) {
			file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '冻结无效'.PHP_EOL, FILE_APPEND);
			echo '冻结无效'.PHP_EOL;
			exit;
		}*/
		
		//查询活跃
		$user_list = M('member')->where('status=1 AND member_id<>8')->field('member_id,vip_level,vip_flag')->select();
		foreach($user_list as $k=>$v){
			$mem_data['huoyue_team'] = 0;
			$mem_data['is_hy'] = 0;
			$mem_data['vip_level'] = 0;
			M('member')->where('vip_flag=0 AND member_id='.$v['member_id'])->save($mem_data);
			$pai_count = M('pai')->where('status = 1 AND sourse=2 AND member_id='.$v['member_id'].' AND yuji_time>='.$today )->count();
			
			$money = 0;
			$money+= M('pai')->where("status = 1 AND sourse=2 AND member_id = ".$v['member_id'].' AND add_time<'.$today.' AND yuji_time>='.$today)->sum('money');
			$money+= M('pai_order')->where("status = 3 AND buy_uid = ".$v['member_id'].' AND deal_time>'.$today.' AND deal_time<'.$tomorrow)->sum('yuan_money');
			
			if($money>0){
				M('member')->where('member_id='.$v['member_id'])->setField('is_hy',1);
				if($v['vip_flag']==0){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',1);
				}
				$path = M('member')->where('member_id='.$v['member_id'])->getField('dai_path'); 
				if($path){
					$id_arr = explode(',',$path);
					foreach($id_arr as $m=>$n){
					    if($n){
    						$leader = M('member')->where('member_id='.$n)->count();
    						if($leader){
    							M('member')->where('member_id='.$n)->setInc('huoyue_team',1);
    						}
					    }
					}
				} 
			}
		}
		//活跃者
		$user_hy = M('member')->where('status=1 AND is_hy=1 AND vip_flag=0')->field('member_id,vip_level,pid,huoyue_team')->select();
		foreach($user_hy as $k=>$v){
			//三条线有单
			$zhitui_num =  M('member')->where('status=1 AND huoyue_team>0 AND pid='.$v['member_id'])->count();
			if($zhitui_num>=3){
				$team_num = $this->my_team_huoyue($v['member_id']);
				//区代理
				if($team_num>=$this->config['hyd_v2']){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',2); 
				}
				
				//市代理  3名 区代理
			    $zhitui_v2 = M('member')->where('status=1 AND vip_level>=2 AND pid='.$v['member_id'])->count();
				if($zhitui_v2>=3){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',3); 
				}
				//省代理  3名 市代理
			    $zhitui_v3 = M('member')->where('status=1 AND vip_level>=3 AND pid='.$v['member_id'])->count();
				if($zhitui_v3>=3){
					M('member')->where('member_id='.$v['member_id'])->setField('vip_level',4); 
				}
				
			}
		}
		//最新活跃者，筛选合伙人（初级、高级）
		$user_hy = M('member')->where('status=1 AND is_hy=1 AND vip_flag=0')->field('member_id,vip_level,pid,huoyue_team')->select();
		foreach($user_hy as $k=>$v){
		    //三条线有单
			$zhitui_num =  M('member')->where('status=1 AND huoyue_team>0 AND pid='.$v['member_id'])->count();
			if($zhitui_num>=3){
			    //初级合伙人 3名 省级代理人
			    $zhitui_v4 = M('member')->where('status=1 AND vip_level>=4 AND pid='.$v['member_id'])->count();
			    if($zhitui_v4>=$this->config['hyd_v5']){
			        M('member')->where('member_id='.$v['member_id'])->setField('vip_level',5); 
			    }
			    //高级合伙人 3名 初级合伙人
			    $zhitui_v5 = M('member')->where('status=1 AND vip_level>=5 AND pid='.$v['member_id'])->count();
			    if($zhitui_v5>=$this->config['hyd_v6']){
			        M('member')->where('member_id='.$v['member_id'])->setField('vip_level',6); 
			    }
			}
		}
		//记录
		file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '级别确定完毕'.PHP_EOL, FILE_APPEND);
		echo '级别确定完毕'.PHP_EOL;
		
		
		
		//今日转拍的订单
		$zhuanpai_list = M('pai')->where('sourse=2  AND yongjin_kou>0 AND add_time>='.$today.' AND add_time<'.$tomorrow)->select();
		
		foreach($zhuanpai_list as $k=>$v){
			$user = M('member')->where('member_id='.$v['member_id'])->field('member_id,phone,name,vip_level,pid,dai_path')->find();
    		if($user['dai_path'] == '' || $user['dai_path'] == null || empty($user['dai_path'])){
    		    continue;
    		}
    		$path_arr = explode(',',$user['dai_path'] );
    		$path_arr = array_reverse($path_arr);
    		$path_num = count($path_arr);
    		if($path_num<=1){
    		    continue;
    		}
    		$father = array();
    		$res = array();
    		for($dai=1;$dai<$path_num;$dai++){
    		    if(intval($path_arr[$dai])>0){
					$father[$dai] = M('member')->where('member_id='.$path_arr[$dai])->field('member_id,phone,name,vip_level,pid')->find();
					if($dai>1){
						$res[$dai] = $this->set_dongtai123($path_arr[$dai],$user['member_id'],$v['yongjin_kou'], $dai,$father[$dai]['vip_level'],$father[$dai-1]['vip_level'],$res[$dai-1]['vip_level_max'],$res[$dai-1]['rate_max'],$res[$dai-1]['is_jie'],$v['id'],$v['yuji_time']);
					}else{
						$res[$dai] = $this->set_dongtai123($path_arr[$dai],$user['member_id'],$v['yongjin_kou'], $dai,$father[$dai]['vip_level'],$user['vip_level'],0,0,0,$v['id'],$v['yuji_time']);
					}
				}
    		    
    		}
		}
		file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '动态冻结完毕'.PHP_EOL, FILE_APPEND);
		echo '动态冻结完毕'.PHP_EOL;
    }
    public function set_dongtai123($member_id,$user_id,$shouyi,$dai,$vip_level,$vip_level_xia,$vip_level_max,$rate_max,$is_jie,$zhuanpai_id,$yuji_time){
		$xiaji = M('Member')->where('member_id='.$user_id)->field('phone,name,vip_level')->find();
		switch ($vip_level){
			case 0://散客
			  $vip_level_max = $xiaji['vip_level'];
			  //$is_jie = 1;
			  break;
			case 1://拍客 拿一代手续费的10%，二代手续费的5%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  $vip_level_max = $xiaji['vip_level'] > $vip_level_max ? $xiaji['vip_level'] : $vip_level_max;
			  break;
			case 2://vip1 拿一代手续费的10%，二代手续费的5%，级差8%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  if($vip_level>$vip_level_max || $rate_max < $this->config['dong_v2_rate']){
				  $yongjin+= round($shouyi*($this->config['dong_v2_rate']-$rate_max)*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = $this->config['dong_v2_rate'];
			  }
			  break;
			case 3://vip2 拿一代手续费的10%，二代手续费的5%，级差%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  if($vip_level>$vip_level_max || $rate_max<$this->config['dong_v3_rate']){
				  $yongjin+= round($shouyi*($this->config['dong_v3_rate']-$rate_max)*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = $this->config['dong_v3_rate'];
			  }
			  break;
			case 4://vip3 拿一代手续费的10%，二代手续费的5%，级差
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  if($vip_level>$vip_level_max || $rate_max<$this->config['dong_v4_rate']){
				  $yongjin+= round($shouyi*($this->config['dong_v4_rate']-$rate_max)*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = $this->config['dong_v4_rate'];
			  }
			  break;
			case 5://初级合伙人 拿一代手续费的10%，二代手续费的5%，团队12%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  /*if($vip_level>$vip_level_max || $rate_max<12){
				  $yongjin+= round($shouyi*(12-$rate_max)*$this->config['dong_v5_rate']*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = 12;
			  }*/
			  break;
			case 6://高级合伙人 拿一代手续费的10%，二代手续费的5%，团队15%
			  if($dai==1){
				  $yongjin = round($shouyi*$this->config['dong_zhitui_rate']*0.01,2);
			  }elseif($dai==2){
				  $yongjin = round($shouyi*$this->config['dong_jiantui_rate']*0.01,2);
			  }
			  //平级（越级）奖励
			  if($is_jie<1 && $vip_level<=$vip_level_xia && $dai>1){
				  $yongjin+= round($shouyi*$this->config['dong_pingji_rate']*0.01,2);
				  $is_jie = 1;
			  }
			  //级差
			  /*if($vip_level>$vip_level_max || $rate_max<15){
				  $yongjin+= round($shouyi*(15-$rate_max)*$this->config['dong_v6_rate']*0.01,2);
				  $vip_level_max = $vip_level;
				  $rate_max = 15;
			  }*/
			  break;
		}
        if($yongjin>0.01){
			$now_date = date('Y-m-d', 1623249000);//现在日期
			//载入数据
			$dong_record['zhuanpai_id'] = $zhuanpai_id;
			$dong_record['member_id'] = $member_id;
			$dong_record['xiaji_id'] = $user_id;
			$dong_record['xiaji_phone'] = $xiaji['phone'];
			$dong_record['xiaji_name'] = $xiaji['name'];
			$dong_record['yongjin'] = $yongjin;
			$dong_record['dai'] = $dai;
			$dong_record['xiaji_level'] = $xiaji['vip_level'];
			$dong_record['my_level'] = $vip_level;
			$dong_record['vip_level_xia'] = $vip_level_xia;
			$dong_record['vip_level_max'] = $vip_level_max;
			$dong_record['rate_max'] = $rate_max;
			$dong_record['is_jie'] = $is_jie;
			$dong_record['add_time'] = 1623249000;
			$dong_record['add_date'] = $now_date;
			$dong_record['status'] = 1;
			$dong_record['yuji_time'] = $yuji_time;

			M('Dong_record')->add($dong_record);
        }
		//$res['yongjin']=$yongjin;
		//$res['vip_level']=$vip_level;
		//$res['vip_level_xia']=$vip_level_xia;
		$res['vip_level_max']=$vip_level_max;
		$res['rate_max']=$rate_max;
		$res['is_jie']=$is_jie;
		return $res;
		
    }
    /**
     * 动态奖励结算
     * 计划任务 每日 23:10 
     */
    public function dong_jiesuan123(){
        $time = time();
		//$today = strtotime('today');
		$today = 1620748800;
		$tomorrow = $today+86400;
        $jiesuan_time = $today + 23*3600; //每日 23:00后 结算
		/*$dong_record_last = M('dong_record')->where('status = 2  AND yuji_time>='.$today)->order('add_time desc')->find();
		if ($dong_record_last || $jiesuan_time >= $time) {
			file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '动态释放无效'.PHP_EOL, FILE_APPEND);
			echo '动态释放无效'.PHP_EOL;
			exit;
		}*/
		$list = M('dong_record')->where('status=1 AND yuji_time>='.$today.' AND yuji_time<'.$tomorrow)->select();
		foreach($list as $k=>$v){
		    M('dong_record')->where('id='.$v['id'])->setField('status',2);
		    //上级动态钱包增加
			M('Member')->where("member_id=" . $v['member_id'])->setInc('heo', $v['yongjin']);
			//动态收益
			addFinance($v['member_id'], 7, '动态奖励，来自'.$v['dai'].'代用户'.$v['xiaji_phone'].' '.$v['xiaji_name'], $v['yongjin'], 1, 6);
		}
		file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '动态释放完毕'.PHP_EOL, FILE_APPEND);
		echo '动态释放完毕'.PHP_EOL;
    }

    
    /**
     * 动态奖励结算
     * 计划任务 每日 23:10 
     */
    public function dong_jiesuan(){
        $time = time();
		$today = strtotime('today');
		$tomorrow = $today+86400;
        $jiesuan_time = $today + 23*3600; //每日 23:00后 结算
		$dong_record_last = M('dong_record')->where('status = 2  AND yuji_time>='.$today)->order('add_time desc')->find();
		if ($dong_record_last || $jiesuan_time >= $time) {
			file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '动态释放无效'.PHP_EOL, FILE_APPEND);
			echo '动态释放无效'.PHP_EOL;
			exit;
		}
		$list = M('dong_record')->where('status=1 AND yuji_time>='.$today.' AND yuji_time<'.$tomorrow)->select();
		foreach($list as $k=>$v){
		    M('dong_record')->where('id='.$v['id'])->setField('status',2);
		    //上级动态钱包增加
			M('Member')->where("member_id=" . $v['member_id'])->setInc('heo', $v['yongjin']);
			//动态收益
			addFinance($v['member_id'], 7, '动态奖励，来自'.$v['dai'].'代用户'.$v['xiaji_phone'].' '.$v['xiaji_name'], $v['yongjin'], 1, 6);
		}
		file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). '动态释放完毕'.PHP_EOL, FILE_APPEND);
		echo '动态释放完毕'.PHP_EOL;
		
		//youxian_days减少
		$youxian_user = M('Member')->where('youxian_days>0')->field('member_id,youxian_days')->select();
		foreach ($youxian_user as $k=>$v){
		    $mem_data = array();
		    $mem_data['member_id'] = $v['member_id'];
		    $mem_data['youxian_days'] = $v['youxian_days']-1;
		    M('Member')->save($mem_data);
		}
		file_put_contents("dongtai.txt",date('Y-m-d H:i:s', time()). 'youxian_days减少1天'.PHP_EOL, FILE_APPEND);
		echo 'youxian_days减少1天'.PHP_EOL;
    }

    /**
     * 每日统计，用户剩余总量
     * 计划任务 每日 23:30
     */
    public function tongji(){
        $time = time();
		$today = strtotime('today');
		$now_date = date('Y-m-d', time()); //现在日期
        $tongji_time = $today+23*3600+29*60; //每日 00:00后 结算
		$record_last = M('tongji_record')->order('add_time desc')->find();
		
		if ($record_last['add_time'] >= $today || $tongji_time >= $time) {
			file_put_contents("tongji.txt",date('Y-m-d H:i:s', time()). '统计无效'.PHP_EOL, FILE_APPEND);
			echo '统计无效'.PHP_EOL;
			exit;
		}
		
		$mem_list = M('Member')->where('status>0 AND member_id<>8')->field('member_id')->select();
		foreach ($mem_list as $k=>$v){
		    $user = $this->user_info($v['member_id']);
    		$team_renshu = $user['team']['renshu']>0 ? $user['team']['renshu'] : 0;
    		$team_paike = $user['team']['paike']>0 ? $user['team']['paike'] : 0;
    		$team_rmb = $user['team']['rmb']>0 ? $user['team']['rmb'] : 0;
    		$team_heo = $user['team']['heo']>0 ? $user['team']['heo'] : 0;
    		$team_heo_bind = $user['team']['heo_bind']>0 ? $user['team']['heo_bind'] : 0;
    		$team_chicang = $user['team']['chicang']>0 ? $user['team']['chicang'] : 0;
    		$team_chicang_num = $user['team']['chicang_num']>0 ? $user['team']['chicang_num'] : 0;
    		$team_yeji_today = $user['team']['yeji_today']>0 ? $user['team']['yeji_today'] : 0;
    		$team_yeji_all = $user['team']['yeji_all']>0 ? $user['team']['yeji_all'] : 0;
		    
		    $tongji_data['member_id'] = $user['member_id'];
		    $tongji_data['vip_level'] = $user['vip_level'];
		    $tongji_data['hyd'] = $user['hyd'];
		    $tongji_data['rmb'] = $user['rmb'];
		    $tongji_data['heo'] = $user['heo'];
		    $tongji_data['heo_bind'] = $user['heo_bind'];
		    $tongji_data['chicang'] = $user['chicang'];
		    $tongji_data['chicang_num'] = $user['chicang_num'];
		    $tongji_data['yeji_today'] = $user['yeji_today'];
		    $tongji_data['yeji_all'] = $user['yeji_all'];
		    
		    $tongji_data['team_renshu'] = $team_renshu;
		    $tongji_data['team_rmb'] = $team_rmb;
		    $tongji_data['team_heo'] = $team_heo;
		    $tongji_data['team_heo_bind'] = $team_heo_bind;
		    $tongji_data['team_chicang'] = $team_chicang;
		    $tongji_data['team_chicang_num'] = $team_chicang_num;
		    $tongji_data['team_yeji_today'] = $team_yeji_today;
		    $tongji_data['team_yeji_all'] = $team_yeji_all;
		    
		    $tongji_data['add_time'] = time();
		    $tongji_data['add_date'] = $now_date;
		    
		    M('tongji_record')->add($tongji_data);
		}
		
		file_put_contents("tongji.txt",date('Y-m-d H:i:s', time()). '统计完毕'.PHP_EOL, FILE_APPEND);
		echo '统计完毕'.PHP_EOL;
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
	
	/**
	*  用户的团队活跃人数
	*/
	function my_team_huoyue($member_id){
		$hyd = 0;
		$list=M('Member')->field('member_id,vip_level')->select();
		foreach($list as $k=>$v){
			$money = 0;
			$you = checkmyteam($member_id,$v['member_id']);
			if($you){
				if($v['vip_level']>0){
					$hyd++;
				}
			}
		}
		return $hyd;
	}
	
	/**
     * 更新级别 推客
     * 计划任务 每日 22:30
     */
    public function member_tuike(){
        $time = time();
		$today = strtotime('today');
		$jisuan_time = $today + 22*3600 + 30*60;
		if ($jisuan_time > time()) {
			file_put_contents("tuike.txt",date('Y-m-d H:i:s', time()). '级别确定无效'.PHP_EOL, FILE_APPEND);
			echo '级别确定无效'.PHP_EOL;
			exit;
		}
		
		$dengji_count = M('member_tuike_record')->where('add_time>'.$today)->count();
		if($dengji_count>0){
		    file_put_contents("tuike.txt",date('Y-m-d H:i:s', time()). '级别已确定'.PHP_EOL, FILE_APPEND);
			echo '级别已确定'.PHP_EOL;
			exit;
		}else{
		    $dj_data['add_time'] = time();
		    $dj_data['add_date'] = date('Y-m-d');
		    M('member_tuike_record')->add($dj_data);
		}
		$this_time = strtotime('2021-7-30');
		//活跃者
		$user_hy = M('member')->where('status=1')->field('member_id,vip_level,pid,huoyue_team')->order('member_id desc')->select();
		foreach($user_hy as $k=>$v){
			//三条线有单
			$zhitui_num =  M('member')->where('reg_time>'.$this_time.' AND status=1 AND huoyue_team>0 AND pid='.$v['member_id'])->count();
			if($zhitui_num>=3){
				$team_num = $this->my_team_huoyue_tuike($v['member_id']);
				//符合条件
				if($team_num>=20){
				    $tuike_data = array();
				    $tuike_data['member_id'] = $v['member_id'];
				    $tuike_data['pid'] = $v['pid'];
				    $tuike_data['huoyue_zhitui'] = $zhitui_num;
				    $tuike_data['vip_level'] = $v['vip_level'];
				    $tuike_data['huoyue_team'] = $team_num;
				    $tuike_data['add_time'] = time();
				    $tuike_data['add_date'] = date('Y-m-d');
				    M('member_tuike')->add($tuike_data);
				}
			}
		}
		//记录
		file_put_contents("tuike.txt",date('Y-m-d H:i:s', time()). '级别确定完毕'.PHP_EOL, FILE_APPEND);
		echo '级别确定完毕'.PHP_EOL;
    }
	/**
	*  用户的团队活跃人数
	*/
	function my_team_huoyue_tuike($member_id){
	    $this_time = strtotime('2021-7-30');
		$hyd = 0;
		$list=M('Member')->where('reg_time>'.$this_time.' AND vip_level>0')->field('member_id,vip_level')->select();
		foreach($list as $k=>$v){
			$you = checkmyteam($member_id,$v['member_id']);
			if($you){
				if($v['vip_level']>0){
					$hyd++;
				}
			}
		}
		return $hyd;
	}
	/**
	*  722新直推
	* 23:30释放
	*/
	public function event_zhitui()
    {
		$time = time();
		$today = strtotime('today');
		
		$jisuan_time = $today + 23*3600 + 29*60;
		if ($jisuan_time > time()) {
			file_put_contents("event.txt",date('Y-m-d H:i:s', time()). ' 722新直推记录无效'.PHP_EOL, FILE_APPEND);
			echo '722新直推记录无效'.PHP_EOL;
			exit;
		}
		
		
		$yesterday = $today-86400;
        $start_time = strtotime('2021-8-6');
        $list = M('member')->where('reg_time>'.$start_time.' AND vip_level>0')->select();
        $i = 0;
        $ids = '';
        foreach ($list as $k=>$v){
            if($v['pid']<=0){
                continue;
            }
            $leader = M('member')->where('member_id='.$v['pid'].' AND is_event=1')->find();
            if(!$leader){
                continue;
            }
            $event_zhitui = M('event_zhitui')->where('member_id='.$v['member_id'])->find();
            $event_zhitui_data = array();
            if($event_zhitui){
                $event_data = array();
                //今天已记录
                if($event_zhitui['update_time']>$today){
                    continue;
                }
                $event_data['id'] = $event_zhitui['id'];
                $event_data['leiji_days'] = $event_zhitui['leiji_days'] + 1;
                $event_data['update_time'] = time();
                //昨日有记录
                if($event_zhitui['update_time']>$yesterday){
                    $event_data['lianxu_days'] = $event_zhitui['lianxu_days'] + 1;
                }else{
                    $event_data['lianxu_days'] = 1;
                }
                //未达标情况下
                if($event_zhitui['is_dabiao']==1){
                    //连续7天
                    if($event_data['lianxu_days']>=7){
                        $event_data['is_dabiao'] = 2;
                    }
                    //累计10天
                    if($event_data['leiji_days']>=10){
                        $event_data['is_dabiao'] = 3;
                    }
                }
                
                M('event_zhitui')->save($event_data);
            }else{
                $event_zhitui_data = array();
                $event_zhitui_data['member_id'] = $v['member_id'];
                $event_zhitui_data['pid'] = $v['pid'];
                $event_zhitui_data['lianxu_days'] = 1;
                $event_zhitui_data['leiji_days'] = 1;
                $event_zhitui_data['add_time'] = time();
                $event_zhitui_data['update_time'] = $event_zhitui_data['add_time'];
                $event_zhitui_data['is_dabiao'] = 1;
                $event_zhitui_data['is_shifang'] = 1;
                $event_zhitui_data['shifang_time'] = 0;
                M('event_zhitui')->add($event_zhitui_data);
            }
            $i++;
            $ids.=$v['member_id'].','; 
        }
        $text = '-722新直推已记录-'.$i.'人-'.$ids;
        file_put_contents("event.txt",date('Y-m-d H:i:s', time()).$text.PHP_EOL, FILE_APPEND);
		echo $text.PHP_EOL;
        exit;
    }
    
    /**
	*  开场N秒后回收
	*/
	public function huishou()
    {
        exit;
		$time = time();
		$today = strtotime('today');
		//早场
	    $zao_time_start = $today + $this->config['zao_hour_start']*3600 + $this->config['zao_minute_start']*60;
	    $zao_time_stop = $today + $this->config['zao_hour_stop']*3600 + $this->config['zao_minute_stop']*60;
	    //午场
	    $wu_time_start = $today + $this->config['wu_hour_start']*3600 + $this->config['wu_minute_start']*60;
	    $wu_time_stop = $today + $this->config['wu_hour_stop']*3600 + $this->config['wu_minute_stop']*60;
	    //夜场
	    $ye_time_start = $today + $this->config['ye_hour_start']*3600 + $this->config['ye_minute_start']*60;
	    $ye_time_stop = $today + $this->config['ye_hour_stop']*3600 + $this->config['ye_minute_stop']*60;
		
		$zao_dai_list = array();
		$zao_dai_num = M('pai')->where('status=1 AND type=1 AND is_dai=1 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->count();
		if($time>=($zao_time_start + $this->config['daishou_huishou']) && $time<($zao_time_start + $this->config['daishou_huishou']+5) && $zao_dai_num>0){
		    $zao_dai_list = M('pai')->where('status=1 AND type=1 AND is_dai=1 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->select();
		}
		$wu_dai_list = array();
		$wu_dai_num = M('pai')->where('status=1 AND type=2 AND is_dai=1 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->count();
		if($time>=($wu_time_start + $this->config['daishou_huishou']) && $time<($wu_time_start + $this->config['daishou_huishou']+5) && $wu_dai_num>0){
		    $wu_dai_list = M('pai')->where('status=1 AND type=2 AND is_dai=1 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->select();
		}
		$wan_dai_list = array();
		$wan_dai_num = M('pai')->where('status=1 AND type=3 AND is_dai=1 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->count();
		if($time>=($ye_time_start + $this->config['daishou_huishou']) && $time<($ye_time_start + $this->config['daishou_huishou']+5) && $wan_dai_num>0){
		    $wan_dai_list = M('pai')->where('status=1 AND type=3 AND is_dai=1 AND (end_time=0 OR end_time>'.$today.') AND yuji_time<='.$today)->select();
		}
		$dai_list = array_merge((array)$zao_dai_list,(array)$wu_dai_list,(array)$wan_dai_list);
		$i = 0;
		$pai_ids = $order_ids = ''; 
		foreach ($dai_list as $k=>$v){
		    $nei_id = M('member')->where('is_nei=1')->order('rand()')->getField('member_id');
		    //匹配
		    $trade_data = array();
			$trade_data['buy_uid'] = $nei_id;
			$trade_data['sell_uid'] = $v['member_id'];
			$trade_data['type'] = $v['type'];
			$trade_data['pai_id'] = $v['id'];
			$trade_data['money'] = $v['money'];
			$trade_data['yuan_money'] = $v['money'];
			$trade_data['source'] = 1;
			$trade_data['status'] = 1;
			$trade_data['pp_time'] = $time;
			$trade_data['sn'] = $this->get_sn();
			$trade_data['dongjie'] = 0;
			$trade_data['is_huishou'] = 1;
			$trade_data['is_dai'] = $v['is_dai'];
			$r = M('pai_order')->add($trade_data);
		    if($r){
		        $pro_data = array();
                //竞拍品表改变状态
				$pro_data['id'] = $v['id'];
				$pro_data['status'] = 2;
				$pro_data['end_time'] = $time;
				$pro_data['order_id'] = $r;
				M('pai')->save($pro_data);
		    }
		    $i++;
		    $pai_ids.=$v['id'].',';
		    $order_ids.=$r.','; 
		}
		if($i>0){
    		$text = '-批量回收-'.$i.'单-作品ID '.$pai_ids.' 订单ID'.$order_ids;
            file_put_contents("huishou.txt",date('Y-m-d H:i:s', time()).$text.PHP_EOL, FILE_APPEND);
    		echo $text.PHP_EOL;
            exit;
		}else{
		    echo '未到时间'.PHP_EOL;
            exit;
		}
    }
    
    
    
}