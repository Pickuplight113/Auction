<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use Think\Page;
class PaiController extends CommonController {
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
    }
    /*
     * redis
     */
    public function test_redis()
    {
        $redis = new \redis();
        $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
        $redis->auth(C('REDIS_AUTH'));
        $id = 3;
        
        $redis_lock = 'lockminpin'.$id;
        $redis_key = 'qpminpin'.$id;
        /*
        $lock_res = $redis->setnx($redis_lock,1);
        if($lock_res==1){
            $redis->lpush($redis_key,1);
        }
        */
        $redis->lpush($redis_key,1);
        $redis_key_len = $redis->LLEN($redis_key);
        
        $data['app_fen'] = C('APP_FEN');
        $data['redis_key_len'] = $redis_key_len;
        $this->ajaxReturn($data);
    }
    /*
     * redis
     */
    public function get_redis()
    {
        $redis = new \redis();
        $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
        $redis->auth(C('REDIS_AUTH'));
        $id = 33068;
        
        $redis_lock = 'lockminpin'.$id;
        $redis_key = 'qpminpin'.$id;

        $redis_key_len = $redis->LLEN($redis_key);
        
        $data['app_fen'] = C('APP_FEN');
        $data['redis_key_len'] = $redis_key_len;
        $this->ajaxReturn($data);
    }
    /*
     * 竞拍场次 名称
     */
    public function get_jingpai_name()
    {
        for($i=1;$i<=3;$i++){
           $list[]=$this->jingpai_time($i);
        }
        $data['status'] = 1;
        $data['data'] = $list;
        $this->ajaxReturn($data);
    }
    /*
     * 产品列表
     */
    public function listing()
    {
        $page = intval(I('page'));
        $page = $page == 0 ? 1 : $page;
        $num = intval(I('num'));
        //$num = 24;
        $num = $num == 0 ? 10 : $num;
        
		$member_id = $this->user['member_id'];
		$user = $this->user;
		$time = time();
		$today = strtotime('today');
		$jian_time = $today + 3600*12;
		$yesterday = strtotime('today')-86400*200;
		$yesterday_end = strtotime('today')-86400*201;
		
		//if($time<$jian_time){
		//    $where = 'status =1 AND sourse=2 AND type IN(1,2,3) AND (end_time=0 OR end_time>'.$today.')';
		//}else{
		    $where = 'status =1 AND type IN(1,2,3) AND (end_time=0 OR end_time>'.$today.')';
		//}
	
		$order = 'sourse desc,yuji_time,chao_time desc,id desc';
		
		$type = intval(I('type'));
		if($type==1){
			$zao_time_stop = $today + $this->config['zao_hour_stop']*3600 + $this->config['zao_minute_stop']*60;
			if($time<$zao_time_stop){
				$where.=' AND type=1 AND yuji_time<='.$today;
			}else{
				$where.=' AND type=1 AND yuji_time>'.$today;
			}
		}elseif($type==2){
			$wu_time_stop = $today + $this->config['wu_hour_stop']*3600 + $this->config['wu_minute_stop']*60;
			if($time<$wu_time_stop){
				$where.=' AND type=2 AND yuji_time<='.$today;
			}else{
				$where.=' AND type=2 AND yuji_time>'.$today;
			}
		}elseif($type==3){
			$ye_time_stop = $today + $this->config['ye_hour_stop']*3600 + $this->config['ye_minute_stop']*60;
			if($time<$ye_time_stop){
				$where.=' AND type=3 AND yuji_time<='.$today;
			}else{
				$where.=' AND type=3 AND yuji_time>'.$today;
			}
		}
		
		/*
		$status = intval(I('status'));
		if($status>0){
			$where.=' AND status='.$status;
		}
		
		$this_day = intval(I('this_day'));
		if($this_day==1){
			$where.=' AND yuji_time<='.$today;
			$order = 'sourse desc,yuji_time,status,chao_time desc,id desc';
		}elseif($this_day==2){
		    $where.=' AND yuji_time>'.$today;
		    $order = 'yuji_time,sourse desc,status,chao_time desc,id desc';
		}else{
		    $order = 'status,yuji_time,sourse desc,chao_time desc,id desc';
		}
		*/
		
		//绿色通道资格
		if($this->config['is_lvtong_on']=='2'){
    		$have_trade = M('pai_order')->where('buy_uid='.$member_id.' AND source=1 AND type='.$type.' AND pp_time>'.$today)->count();
    		if($have_trade){
    			$shenqing = 0;
    		}else{
    			if($user['youxian_days']>0){
    				$shenqing = 1;
    			}else{
    				if($user['vip_level']>1){
    					$shenqing = 1;
    				}else{
    					$shenqing = 0;
    				}
    			}
    		}
		}else{
	        //绿通次数
		    $have_trade_lvtong = M('pai_order')->where('buy_uid='.$member_id.' AND is_lvtong=1 AND source=1 AND type='.$type.' AND pp_time>'.$today)->count();
		    $data['have_trade_lvtong'] = $have_trade_lvtong;

    		if($have_trade_lvtong>=2){
    		    //有两次绿通
    			$shenqing = 0;
    		}elseif($have_trade_lvtong<=0){
    		    //无绿通
    		    if($user['youxian_days']>0){
    				$shenqing = 1;
    			}else{
    				if($user['vip_level']>1){
    					$shenqing = 1;
    				}else{
    					$shenqing = 0;
    				}
    			}
    		}else{
    		    //有一次绿通
    		    if($user['vip_level']>1){
					$shenqing = 1;
				}else{
					$shenqing = 0;
				}
    		} 
		}

        $count = M('pai')->where($where)->count();
        //if($type==1 && $member_id==773){
            $list = M('pai')->where($where)->order($order)->limit($num)->page($page)->select();
            foreach ($list as $k => $v) {
                $aaa[$k] = $this->get_pai_product($v['id'],$shenqing);
    			if($aaa[$k]['status']==1){
    			    $list[$k] = $aaa[$k]['info'];
    			}
            }
        //}
        $data['is_lvtong'] = $shenqing;
        $data['user_vip'] = $user['vip_level'];
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
     * 获取单个竞拍区商品
     */
    public function get_jingpai_info(){
        if(IS_POST){
            $member_id = $this->user['member_id'];
            $user = $this->user;
            $today = strtotime('today');
            $id = intval(I('id'));
			
			//绿色通道资格
			$pro = M('pai')->where('id='.$id)->find();
			$have_trade = M('pai_order')->where('buy_uid='.$member_id.' AND source=1 AND type='.$pro['type'].' AND pp_time>'.$today)->count();
			if($this->config['is_lvtong_on']=='2'){
				if($have_trade){
					$shenqing = 0;
				}else{
					if($user['youxian_days']>0){
						$shenqing = 1;
					}else{
						if($user['vip_level']>1){
							$shenqing = 1;
						}else{
							$shenqing = 0;
						}
					}
				}
			}else{
			    $have_trade_lvtong = M('pai_order')->where('buy_uid='.$member_id.' AND is_lvtong=1 AND source=1 AND type='.$pro['type'].' AND pp_time>'.$today)->count();
			    $data['have_trade_lvtong'] = $have_trade_lvtong;
        		if($have_trade_lvtong>=2){
        		    //有两次绿通
        			$shenqing = 0;
        		}elseif($have_trade_lvtong<=0){
        		    //无绿通
        		    if($user['youxian_days']>0){
        				$shenqing = 1;
        			}else{
        				if($user['vip_level']>1){
        					$shenqing = 1;
        				}else{
        					$shenqing = 0;
        				}
        			}
        		}else{
        		    //有一次绿通
        		    if($user['vip_level']>1){
    					$shenqing = 1;
    				}else{
    					$shenqing = 0;
    				}
        		} 
			}

            $res = $this->get_pai_product($id,$shenqing);
            
            if($res['status']==1){
                
                $redis = new \redis();
                $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
                $redis->auth(C('REDIS_AUTH'));
                
                $redis_lock = 'lockminpin'.$id;
                $redis_key = 'qpminpin'.$id;
                $lock_res = $redis->setnx($redis_lock,1);

                if($lock_res==1){
                    $redis->lpush($redis_key,1);
                }
                
                $redis_key_len = $redis->LLEN($redis_key);
                if($redis_key_len>1){
                    for($h=1;$h<$redis_key_len;$h++){
                        $redis->lpop($redis_key);
                    }
                }

                $product = $res['info'];
                $product['bzj'] = 200;
                $product['user_heo'] = $user['heo'];
                //绿色通道资格
				if($this->config['is_lvtong_on']=='2'){
				    //本场抢拍数量是否已达到限制
        		    if($have_trade>=$this->config['pai_limit_num']){
        		        $product['limit_num'] = 1;
        		    }else{
        		        $product['limit_num'] = 0;
        		    }
        		    
				}else{
				    $have_trade_lvtong = M('pai_order')->where('buy_uid='.$member_id.' AND is_lvtong=1 AND source=1 AND type='.$product['type'].' AND pp_time>'.$today)->count();
				    //本场抢拍数量是否已达到限制
            		if($have_trade_lvtong>=2){
            		    if(($have_trade-1) >= $this->config['pai_limit_num']){
            				$product['limit_num'] = 1;
            		    }else{
            		        $product['limit_num'] = 0;
            		    }
            		}elseif($have_trade_lvtong<=0){
            		    if($have_trade >= $this->config['pai_limit_num']){
        					$product['limit_num'] = 1;
            		    }else{
            		        $product['limit_num'] = 0;
            		    }
            		}else{
            		    if($user['vip_level']>1){
            		        if(($have_trade-1) >= $this->config['pai_limit_num']){
            					$product['limit_num'] = 1;
                		    }else{
                		        $product['limit_num'] = 0;
                		    }
            		    }else{
            		        if($have_trade >= $this->config['pai_limit_num']){
            					$product['limit_num'] = 1;
                		    }else{
                		        $product['limit_num'] = 0;
                		    }
            		    }
            		} 
				}
    		    
    		    //今日竞拍金额是否已达到限制
    		    $have_money = M('pai_order')->where('buy_uid='.$member_id.' AND source=1 AND pp_time>'.$today)->sum('money');
    		    $have_money = $have_money + $product['money'];
    		    
    		    //$data['have_money'] = $have_money;
    		    //$data['pai_limit_money'] = $this->config['pai_limit_money'];
    		    
    		    if($have_money>=$this->config['pai_limit_money']){
    		        $product['limit_money'] = 1;
    		    }else{
    		        $product['limit_money'] = 0;
    		    }

    		    //是否为自己的商品
    		    if($member_id == $product['member_id']){
    			    $product['my_product'] = 1;
    			}else{
    			    $product['my_product'] = 2;
    			}
    			$product['my_paytype_num'] = $this->get_pay_type_num($member_id);
    			$product['pai_paytype_num'] = $this->config['pai_paytype_num'];
    			
    			$product['my_weiguan'] = M('weiguan')->where('pai_id='.$id.' AND member_id='.$member_id)->count();
    			
    			if( intval($user['heo']) >=200){
                    $product['bzj_status'] = 1;
                    $product['bzj_status_name'] = '保证金充足';
                }else{
                    $product['bzj_status'] = 2;
                    $product['bzj_status_name'] = '保证金不足...';
                    //$product['bzj_status_name'] = '888888';
                    
                    $text = '详情页【保证金不足】，member_id='.$member_id.'，phone='.$user['phone'].'，抢拍ID='.$id.'，用户当前HEO='.$user['heo'];
					file_put_contents("qiang.txt",date('Y-m-d H:i:s', time()). $text.PHP_EOL, FILE_APPEND);
                    
                }
                
    			$data['app_fen'] = C('APP_FEN');
    			$data['is_lvtong'] = $shenqing;
    			$data['user_vip'] = $user['vip_level'];
                $data['status'] = 1;
                $data['info'] = '获取成功';
                $data['data'] = $product;
                
                
                $text_info = ' member_id:'.$member_id.PHP_EOL;
                $text_info.= 'pai_id:'.$id.PHP_EOL;
                $text_info.= '原redis_key_len:'.$redis_key_len.PHP_EOL;
                $text_info.= '现redis_key_len:'.$redis->LLEN($redis_key).PHP_EOL;
                $text_info.= 'is_lvtong:'.$shenqing.PHP_EOL;
                $text_info.= 'user_vip:'.$data['user_vip'].PHP_EOL;
                foreach ($product as $k=>$v){
                    $text_info.=$k.'='.$v.PHP_EOL;
                }
                file_put_contents("jingpai_info.txt",date('Y-m-d H:i:s', time()). $text_info.PHP_EOL, FILE_APPEND);
                
                
                $this->ajaxReturn($data);
            }else{
               $this->ajaxReturn($res); 
            }
        }
    }
    /*
     * 记录抢单
     */
    public function jilu(){
        if(IS_POST){
    		$member_id = $this->user['member_id'];
    		$id = intval(I('id'));
    		$today = strtotime('today');
    		if($id>0){
                $where = 'id='.$id;
                $product = M('pai')->where($where)->find();
    			if(!$product){
    				$data['status'] = 4;
    				$data['info'] = '参数错误';
    				$this->ajaxReturn($data);
    			}
	            $jilu = M('jilu')->where('add_time>'.$today.' AND member_id='.$member_id.' AND type='.$product['type'])->count();
	            if($jilu==0){
	                $jl['member_id'] = $member_id;
	                $jl['product_id'] = $id;
	                $jl['type'] = $product['type'];
	                $jl['add_time'] = time();
	                M('jilu')->add($jl);
	            }
	            $data['status'] = 1;
				$data['info'] = '已记录';
				$this->ajaxReturn($data);
    		}
        }
    }
 
	/*
     * 抢拍
     */
    public function qiang(){
        if(IS_POST){
    		$member_id = $this->user['member_id'];
            $user = $this->user;
            $today = strtotime('today');
            $id = intval(I('id'));
    		if($id<=0){
    		    $data['status'] = 2;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
    		}else{
    		    /*
    		    $data['status'] = 3;
                $data['info'] = '暂停抢单';
                $this->ajaxReturn($data);
    		    */
				/*if($id==2679 && $member_id==773){
					
				}else{
					$data['status'] = -5;
					$data['info'] = '无资格抢拍';
					$this->ajaxReturn($data);
				}*/
				
				$redis = new \redis();
                $redis->connect(C('REDIS_HOST'), C('REDIS_PORT'));
                $redis->auth(C('REDIS_AUTH'));
				
				$redis_key = 'qpminpin'.$id;
				$redis_key_len = $redis->LLEN($redis_key);
                if($redis_key_len>1){
                    for($h=1;$h<$redis_key_len;$h++){
                        $redis->lpop($redis_key);
                    }
                }
                
                file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' 原redis_key_len:'.$redis_key_len.' 现redis_key_len:'.$redis->LLEN($redis_key).PHP_EOL, FILE_APPEND);

                $pop_res = $redis->lpop($redis_key);
                if(empty($pop_res)){
                    $data['status'] = -10;
    				$data['info'] = '商品已售罄...';
    				file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
    				$this->ajaxReturn($data);
                }
                
                file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' pop_res:'.$pop_res.' 最新redis_key_len:'.$redis->LLEN($redis_key).PHP_EOL, FILE_APPEND);
                
                
				// 开启事务
                $model = M();
                $model->startTrans();
				
                $where = 'id='.$id;
                $product = M('pai')->where($where)->find();
    			if(!$product){
    			    $redis->lpush($redis_key,1);
    				$data['status'] = 3;
                    $data['info'] = '无商品信息';
                    file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
                    $this->ajaxReturn($data);
    			}
				//检测是否已拍卖
				if($product['status']!=1 || $product['is_pipei']>0){
					//$redis->lpush($redis_key,1);
					$data['status'] = -1;
					$data['info'] = '商品已售罄';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					$this->ajaxReturn($data);
				}
				if($product['yuji_time']>$today){
					$redis->lpush($redis_key,1);
					$data['status'] =4;
					$data['info'] = '该商品不可拍';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					$this->ajaxReturn($data);
				}
				if($product['member_id']==$member_id){
					$redis->lpush($redis_key,1);
				    $data['status'] =5;
					$data['info'] = '不可以抢拍自己的商品';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					$this->ajaxReturn($data);
				}
				
				
				//锁定竞品
				M('pai')->where($where)->setField('status',5);
				//检测是否在交易时间内
				$time = time();
				if($product['type']==1){
					$start = $today+$this->config['zao_hour_start']*3600+$this->config['zao_minute_start']*60;
					$end = $today + $this->config['zao_hour_stop']*3600+$this->config['zao_minute_stop']*60;	
				}elseif($product['type']==2){
					$start = $today+$this->config['wu_hour_start']*3600+$this->config['wu_minute_start']*60;
					$end = $today + $this->config['wu_hour_stop']*3600+$this->config['wu_minute_stop']*60;
				}elseif($product['type']==3){
					$start = $today+$this->config['ye_hour_start']*3600+$this->config['ye_minute_start']*60;
					$end = $today + $this->config['ye_hour_stop']*3600+$this->config['ye_minute_stop']*60;
				}else{
					$redis->lpush($redis_key,1);
				    $data['status'] =4;
					$data['info'] = '该商品不可拍';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					M('pai')->where($where)->setField('status',$product['status']);
					$this->ajaxReturn($data);
				}
				
				
    		    //今日竞拍金额是否已达到限制
    		    $have_money = M('pai_order')->where('buy_uid='.$member_id.' AND source=1 AND pp_time>'.$today)->sum('money');
    		    $have_money = $have_money + $product['money'];
    		    if($have_money > $this->config['pai_limit_money']){
					$redis->lpush($redis_key,1);
    		        $data['status'] = 5;
					$data['info'] = '今日抢拍金额已达到限制，不能再次抢拍';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					M('pai')->where($where)->setField('status',$product['status']);
					$this->ajaxReturn($data);
    		    }
    		    
    		    $have_trade = M('pai_order')->where('buy_uid='.$member_id.' AND source=1 AND type='.$product['type'].' AND pp_time>'.$today)->count();
                $data['have_trade'] = $have_trade;
				//绿色通道资格
				if($this->config['is_lvtong_on']=='2'){
				    //本场抢拍数量是否已达到限制
        		    if($have_trade>=$this->config['pai_limit_num']){
    					$redis->lpush($redis_key,1);
        		        $data['status'] = 5;
    					$data['info'] = '本场抢拍数量已达到限制，不能再次抢拍';
    					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
    					M('pai')->where($where)->setField('status',$product['status']);
    					$this->ajaxReturn($data);
        		    }
        		    
    				if($have_trade){
    					$shenqing = 0;
    				}else{
    					if($user['youxian_days']>0){
    						$shenqing = 1;
    					}else{
    						if($user['vip_level']>1){
    							$shenqing = 1;
    						}else{
    							$shenqing = 0;
    						}
    					}
    				}
				}else{
				    $have_trade_lvtong = M('pai_order')->where('buy_uid='.$member_id.' AND is_lvtong=1 AND source=1 AND type='.$product['type'].' AND pp_time>'.$today)->count();
				    $data['have_trade_lvtong'] = $have_trade_lvtong;
				    //本场抢拍数量是否已达到限制
            		if($have_trade_lvtong>=2){
            		    if(($have_trade-1) >= $this->config['pai_limit_num']){
        					$redis->lpush($redis_key,1);
            		        $data['status'] = 5;
        					$data['info'] = '本场抢拍数量已达到限制，不能再次抢拍';
        					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
        					M('pai')->where($where)->setField('status',$product['status']);
        					$this->ajaxReturn($data);
            		    }
            		    //有两次绿通
            			$shenqing = 0;
            		}elseif($have_trade_lvtong<=0){
            		    if($have_trade >= $this->config['pai_limit_num']){
        					$redis->lpush($redis_key,1);
            		        $data['status'] = 5;
        					$data['info'] = '本场抢拍数量已达到限制，不能再次抢拍';
        					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
        					M('pai')->where($where)->setField('status',$product['status']);
        					$this->ajaxReturn($data);
            		    }
            		    //无绿通
            		    if($user['youxian_days']>0){
            				$shenqing = 1;
            			}else{
            				if($user['vip_level']>1){
            					$shenqing = 1;
            				}else{
            					$shenqing = 0;
            				}
            			}
            		}else{
            		    //有一次绿通
            		    if($user['vip_level']>1){
            		        if(($have_trade-1) >= $this->config['pai_limit_num']){
            					$redis->lpush($redis_key,1);
                		        $data['status'] = 5;
            					$data['info'] = '本场抢拍数量已达到限制，不能再次抢拍';
            					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
            					M('pai')->where($where)->setField('status',$product['status']);
            					$this->ajaxReturn($data);
                		    }
        					$shenqing = 1;
        				}else{
        				    if($have_trade >= $this->config['pai_limit_num']){
            					$redis->lpush($redis_key,1);
                		        $data['status'] = 5;
            					$data['info'] = '本场抢拍数量已达到限制，不能再次抢拍';
            					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
            					M('pai')->where($where)->setField('status',$product['status']);
            					$this->ajaxReturn($data);
                		    }
        					$shenqing = 0;
        				}
            		} 
				}
				
				/*
				$data['shenqing'] = $shenqing;
				$redis->lpush($redis_key,1);
		        $data['status'] = 6;
				$data['info'] = '测试中';
				M('pai')->where($where)->setField('status',$product['status']);
				$this->ajaxReturn($data);
				*/
				
				if($shenqing){
					$start = $start - $this->config['youxian_time']*60;
				}
				if(intval($user['heo']) < 200){
					$redis->lpush($redis_key,1);
					$data['status'] =3;
					$data['info'] = '您的保证金不足200HEO';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					M('pai')->where($where)->setField('status',$product['status']);
					
					$text = '抢单时【您的保证金不足200HEO】，member_id='.$member_id.'，phone='.$user['phone'].'，抢拍ID='.$id.'，用户当前HEO='.$user['heo'];
					
					file_put_contents("qiang.txt",date('Y-m-d H:i:s', time()). $text.PHP_EOL, FILE_APPEND);

					$this->ajaxReturn($data);
				}
				if($start>$time || $end<$time){
					$redis->lpush($redis_key,1);
					$data['status'] = 2;
					$data['info'] = '不在竞拍时间内';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					M('pai')->where($where)->setField('status',$product['status']);
					$this->ajaxReturn($data);
				}
	
				//匹配
				$trade_data['buy_uid'] = $member_id;
				$trade_data['sell_uid'] = $product['member_id'];
				$trade_data['type'] = $product['type'];
				$trade_data['pai_id'] = $product['id'];
				$trade_data['money'] = $product['money'];
				$trade_data['yuan_money'] = $product['money'];
				$trade_data['status'] = 1;
				$trade_data['source'] = 1;
				$trade_data['pp_time'] = $time;
				$trade_data['sn'] = $this->get_sn();
				//$trade_data['dongjie'] = $dongjie;
				$trade_data['alipay_beizhu'] = M('member_alipay')->where('member_id='.$product['member_id'].' AND alipay_pic IS NOT NULL')->getField('beizhu');
				$trade_data['weixin_beizhu'] = M('member_weixin')->where('member_id='.$product['member_id'].' AND weixin_pic IS NOT NULL')->getField('beizhu');
				$trade_data['bank_beizhu'] = M('member_bankcard')->where('member_id='.$product['member_id'].' AND bankcard<>"" AND bankcard IS NOT NULL')->getField('beizhu');
				
				$trade_data['is_lvtong'] = $shenqing;
				$trade_data['is_dai'] = $product['is_dai'];
				
				$r = M('pai_order')->add($trade_data);
				if($r){
					//竞拍品表改变状态
					$pro_data['id'] = $id;
					$pro_data['status'] = 2;
					$pro_data['end_time'] = $time;
					$pro_data['order_id'] = $r;
					M('pai')->save($pro_data);
					/*if($shenqing){
					    M('shenqing')->where("id=" . $shenqing['id'])->setField('trade_id', $r);
					}else{
    					//抢拍冻结保证金
    					M('Member')->where("member_id=" . $member_id)->setDec('heo', $dongjie);
    					M('Member')->where("member_id=" . $member_id)->setInc('bzj', $dongjie);
    					addFinance($member_id, 33, '抢拍【'.$product['title'].'】冻结保证金', $dongjie, 2, 6);
    				    addFinance($member_id, 33, '抢拍【'.$product['title'].'】冻结保证金', $dongjie, 1, 5);
					}*/
					
					$zhifu_data['order_id'] = $r;
					$zhifu_data['buy_uid'] = $member_id;
					$zhifu_data['sell_uid'] = $product['member_id'];
					$alipay = M('member_alipay')->where('member_id='.$product['member_id'].' AND alipay_pic IS NOT NULL')->find();
					if($alipay){
						$zhifu_data['alipay'] = $alipay['alipay'];
						$zhifu_data['alipay_name'] = $alipay['alipay_name'];
						$zhifu_data['alipay_phone'] = $alipay['alipay_phone'];
						$zhifu_data['alipay_pic'] = $alipay['alipay_pic'];
						$zhifu_data['alipay_beizhu'] = $alipay['alipay_beizhu'];
					}
					$weixin = M('member_weixin')->where('member_id='.$product['member_id'].' AND weixin_pic IS NOT NULL')->find();
					if($weixin){
						$zhifu_data['weixin'] = $weixin['weixin'];
						$zhifu_data['weixin_name'] = $weixin['weixin_name'];
						$zhifu_data['weixin_phone'] = $weixin['weixin_phone'];
						$zhifu_data['weixin_pic'] = $weixin['weixin_pic'];
						$zhifu_data['weixin_beizhu'] = $weixin['weixin_beizhu'];
					}
					$bankcard = M('member_bankcard')->where('member_id='.$product['member_id'].' AND bankcard<>"" AND bankcard IS NOT NULL')->find();
					if($bankcard){
						$zhifu_data['bankcard'] = $bankcard['bankcard'];
						$zhifu_data['bankcard_name'] = $bankcard['bankcard_name'];
						$zhifu_data['bank'] = $bankcard['bank'];
						$zhifu_data['bank_address'] = $bankcard['bank_address'];
						$zhifu_data['bankcard_beizhu'] = $bankcard['bankcard_beizhu'];
					}
					$zhifu_data['add_time'] = time();
					M('zhifu_beifen')->add($zhifu_data);
					
					$model->commit();
					
					$data['status'] = 1;
					$data['info'] = '恭喜您，抢单成功！';
					$data['order_id'] = $r;
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].'order_id:'.$r.PHP_EOL, FILE_APPEND);
					$this->ajaxReturn($data);
				}else{
					M('pai')->where($where)->setField('status',$product['status']);
					$redis->lpush($redis_key,1);
					$model->rollback();
					
					$data['status'] = 4;
					$data['info'] = '操作失败，请重试';
					file_put_contents("qiangpai.txt",date('Y-m-d H:i:s', time()). ' member_id:'.$member_id.' pai_id:'.$id.' info:'.$data['info'].PHP_EOL, FILE_APPEND);
					$this->ajaxReturn($data);
				}
				
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
    /**
     * 获取我的订单列表（竞拍+置换）
     */
    public function order_list(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;

            $member_id = $this->user['member_id'];
            $role = intval(I('role'));
            if($role==1){
                $where = 'buy_uid='.$member_id;
            }elseif($role==2){
                $where = 'sell_uid='.$member_id;
            }else{
                $data['status'] = 2;
                $data['info'] = '订单类型参数缺失';
                $this->ajaxReturn($data);
            }
            
            $where.=' AND pp_time>1626883200';

            $status = intval(I('status'));
            if($status>0){
                if($status==1){
                    $where.=' AND status=1';
                }elseif($status==3){
                    $where.=' AND status=5';
                }elseif($status==4){
                    $where.=' AND status=3';
                }elseif($status==5){
                    $where.=' AND status=7';
                }
                if($status==2){
                    if($role==1){
                        $where.=' AND status IN (2,6,7)';
                    }
                    if($role==2){
                        $where.=' AND status IN (2,6)';
                    }
                }
            }
            
            $count      =  M('pai_order')->where($where)->count();
            $order = M('pai_order')->where($where)->limit($num)->page($page)->order('status,id desc')->select();
            
            if($order){
                foreach ($order as $k=>$v){
                    $re = $this->get_pai_order($v['id']);
                    if($re['status']==1){
                        $res[$k] = $re['info'];
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
     * 获取我的竞拍订单详情
     */
    public function get_my_order_info(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 3;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $role = intval(I('role'));
            if($role==1){
                $where = 'buy_uid='.$member_id;
            }elseif($role==2){
                $where = 'sell_uid='.$member_id;
            }else{
                $data['status'] = 2;
                $data['info'] = '订单类型参数缺失';
                $this->ajaxReturn($data);
            }
            $where.=' AND id='.$id;
            $order = M('pai_order')->where($where)->find();
            $res = $this->get_pai_order($order['id']);
            if($res['status']==1){
                $r = $res['info'];
                //买单去支付，返回卖家收款方式
                $pay = intval(I('pay'));
                if($pay==1){
                    $r['alipay'] = M('member_alipay')->where('member_id='.$order['sell_uid'].' AND alipay_pic IS NOT NULL')->field('alipay,alipay_name,alipay_pic,beizhu')->find();
                    if($r['alipay']['alipay']){
                        $r['alipay']['alipay_pic_path'] = $r['alipay']['alipay_pic'];
                        $r['alipay']['alipay_pic'] = $this->config['oss_url'].$r['alipay']['alipay_pic'];
                    }else{
                        $r['alipay'] = array();
                    }
                    $r['weixin'] = M('member_weixin')->where('member_id='.$order['sell_uid'].' AND weixin_pic IS NOT NULL')->field('weixin,weixin_name,weixin_pic,beizhu')->find();
                    if($r['weixin']['weixin']){
                        $r['weixin']['weixin_pic_path'] = $r['weixin']['weixinpic'];
                        $r['weixin']['weixin_pic'] = $this->config['oss_url'].$r['weixin']['weixin_pic'];
                    }else{
                        $r['weixin'] = array();
                    }
                    $r['bankcard'] = M('member_bankcard')->where('member_id='.$order['sell_uid'].' AND bankcard<>"" AND bankcard IS NOT NULL')->field('bankcard,bankcard_name,bank,bank_address,beizhu')->find();
                    if(!$r['bankcard']){
                        $r['bankcard'] = array();
                    }
                    
                    $r['user_hex'] = $user['hex'];
                }
                
                $r['service_time'] = time();
                $r['service_time_1000'] = $r['service_time']*1000;
                $r['service_date'] = date('Y/m/d H:i:s',$r['service_time']);
                
                $data['status'] = 1;
                $data['info'] = '获取成功';
                $data['data'] = $r;
                $this->ajaxReturn($data);
            }else{
               $this->ajaxReturn($res); 
            }
        }
    }
	/**
     * 买家支付
     */
    public function order_pay(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            $pay_type = intval(I('pay_type'));
			$ping_pic = I('ping_pic');
            if($id<=0 || $pay_type<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
			
			if(($pay_type==1 || $pay_type==2 || $pay_type==3) && empty($ping_pic)){
                $data['status'] = 6;
                $data['info'] = '请上传支付截图';
                return json($data);
            }
			
            $order = M('pai_order')->where('id='.$id.' AND buy_uid='.$member_id)->find();
            if(!$order){
                $data['status'] = 3;
                $data['info'] = '无订单信息';
                $this->ajaxReturn($data);
            }
            if($order['status']!=1 && $order['status']!=5){
                $data['status'] = 4;
                $data['info'] = '订单已支付，请勿重复提交';
                $this->ajaxReturn($data);
            }
            
            if($pay_type ==1){
                $pay_data['type'] = '支付宝';
				$order_data['ping_pic'] = $ping_pic;
            }elseif($pay_type ==2){
                $pay_data['type'] = '微信';
				$order_data['ping_pic'] = $ping_pic;
            }elseif($pay_type ==3){
                $pay_data['type'] = '银行卡';
				$order_data['ping_pic'] = $ping_pic;
            }elseif($pay_type ==4){
                $pay_data['type'] = 'HEX';
                //扣除HEX ？？？
                
                
            }
            $pay_data['scene'] = '竞拍支付';
            $pay_data['money'] = $order['money'];
            $pay_data['scene_id'] = $id;
            $pay_data['add_time'] = time();
            $pay_id = M('pay')->add($pay_data);
            
			$order_data['id'] = $id;
			$order_data['status'] = 2;
			$order_data['ping_time'] = time();
            $order_data['pay_pai_id'] = $pay_id;
            $order_data['pay_beizhu'] = trim(I('pay_beizhu'));
            M('pai_order')->save($order_data);
            
		    //给卖家发送短信
		    $seller_phone = M('member')->where('member_id='.$order['sell_uid'])->getField('phone');
            $content = '您的卖单已支付，请尽快放货';
            $record['phone'] = $seller_phone;
            $record['content'] = $content;
            $record['add_time'] = time();
            M('Mobile_record')->add($record);
            /*$params = array(
                "templateId" => "13594",
                "mobile" => $seller_phone,
        		"paramType" => "json",
                "params" => json_encode($json_param),
            );
    		$this->send_message($params);*/
            
			$data['status'] = 1;
			$data['info'] = '提交成功';
			$this->ajaxReturn($data);
        }
    }
	/**
     * 卖家放货
     */
    public function order_fanghuo(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('pai_order')->where('id='.$id.' AND sell_uid='.$member_id)->find();
            if(!$order){
                $data['status'] = 3;
                $data['info'] = '无订单信息';
                $this->ajaxReturn($data);
            }
            if($order['status']!=2 && $order['status']!=6){
                $data['status'] = 4;
                $data['info'] = '订单已提交，请勿重复提交';
                $this->ajaxReturn($data);
            }
            $order_data['id'] = $id;
			$order_data['status'] = 3;
			$order_data['deal_time'] = time();
            M('pai_order')->save($order_data);
            
            if($order['source']==1){
                $title = M('pai')->where('id='.$order['pai_id'])->getField('title');
                //推荐奖励
    		    $buyer = M('Member')->where("reg_time>1626017701 AND member_id=" . $order['buy_uid'])->field('paidanjiang,pid,name,phone,reg_time')->find();
    			if($this->config['is_tuijian_jiangli']==1 && $buyer['paidanjiang']<=0 && $this->config['tuijian_jiangli']>0 && $buyer['pid']>0 && ($buyer['reg_time']+48*3600)>=$order['pp_time'] ){
    			    M('Member')->where("member_id=" . $order['buy_uid'])->setInc('paidanjiang', $this->config['tuijian_jiangli']);
    			    $leader = M('member')->where('member_id='.$buyer['pid'])->count();
    			    if($leader>0){
    			        M('Member')->where("member_id=" . $buyer['pid'])->setInc('heo', $this->config['tuijian_jiangli']);
    			        addFinance($buyer['pid'], 27, '直推用户'.$buyer['phone'].' '.$buyer['name'].'完成竞拍，给与推荐奖励，竞拍订单ID【'.$id.'】', $this->config['tuijian_jiangli'], 1, 6);
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
                			$gxz_data['title'] = $title;
                			$gxz_data['info'] = '直推用户'.$buyer['phone'].'完成交易【'.$gxz_data['title'].'】获得'.$gxz_data['gxz'].'贡献值';
                			$gxz_data['add_time'] = time();
                			$gxz_res = M('event_gxz')->add($gxz_data);
                			M('Member')->where("member_id=" . $gxz_data['member_id'])->setInc('gxz', $gxz_data['gxz']);
            			}
    			    }
    			}
    			$today = strtotime('today');
    			
    			//交易完成，奖励买家贡献值15分，每日4单
    			$gxz_count = M('event_gxz')->where('member_id='.$order['buy_uid'].' AND type=1 AND add_time>'.$today)->count();
    			$zige = M('member')->where('is_event = 1 AND member_id='.$order['buy_uid'])->count();
    			if($gxz_count<4 && $zige>0){
    			    $gxz_data = array();
        			$gxz_data['member_id'] = $order['buy_uid'];
        			$gxz_data['gxz'] = 15;
        			$gxz_data['yuan_gxz'] = M('Member')->where("member_id=" . $gxz_data['member_id'])->getField('gxz');
        			$gxz_data['xian_gxz'] = $gxz_data['yuan_gxz'] + $gxz_data['gxz'];
        			$gxz_data['type'] = 1;
        			$gxz_data['money_type'] = 1;
        			$gxz_data['title'] = $title;
        			$gxz_data['info'] = '完成交易【'.$gxz_data['title'].'】获得'.$gxz_data['gxz'].'贡献值';
        			$gxz_data['add_time'] = time();
        			$gxz_res = M('event_gxz')->add($gxz_data);
        			M('Member')->where("member_id=" . $gxz_data['member_id'])->setInc('gxz', $gxz_data['gxz']);
    			}
    			//交易买卖双方在20分内完成订单。各加15分。（每日2单封顶）
    			if(($order['pp_time'] + 1200) >= time()){
    			    //买方
    			    $gxz_count_buy = M('event_gxz')->where('member_id='.$order['buy_uid'].' AND type=2 AND add_time>'.$today)->count();
    			    if($gxz_count_buy<2 && $zige>0){
    			        $gxz_data = array();
            			$gxz_data['member_id'] = $order['buy_uid'];
            			$gxz_data['gxz'] = 15;

            			$gxz_data['yuan_gxz'] = M('Member')->where("member_id=" . $gxz_data['member_id'])->getField('gxz');
        			    $gxz_data['xian_gxz'] = $gxz_data['yuan_gxz'] + $gxz_data['gxz'];
            			
            			$gxz_data['type'] = 2;
            			$gxz_data['money_type'] = 1;
            			$gxz_data['title'] = $title;
            			$gxz_data['info'] = '20分钟内完成交易【'.$gxz_data['title'].'】获得'.$gxz_data['gxz'].'贡献值';
            			$gxz_data['add_time'] = time();
            			$gxz_res = M('event_gxz')->add($gxz_data);
            			M('Member')->where("member_id=" . $gxz_data['member_id'])->setInc('gxz', $gxz_data['gxz']);
    			    }
    			    //卖方
    			    $gxz_count_sell = M('event_gxz')->where('member_id='.$order['sell_uid'].' AND type=2 AND add_time>'.$today)->count();
    			    $zige_sell = M('member')->where('is_event = 1 AND member_id='.$order['sell_uid'])->count();
    			    if($gxz_count_sell<2 && $zige_sell>0){
    			        $gxz_data = array();
            			$gxz_data['member_id'] = $order['sell_uid'];
            			$gxz_data['gxz'] = 15;

            			$gxz_data['yuan_gxz'] = M('Member')->where("member_id=" . $gxz_data['member_id'])->getField('gxz');
        			    $gxz_data['xian_gxz'] = $gxz_data['yuan_gxz'] + $gxz_data['gxz'];
            			
            			$gxz_data['type'] = 2;
            			$gxz_data['money_type'] = 1;
            			$gxz_data['title'] = $title;
            			$gxz_data['info'] = '20分钟内完成交易【'.$gxz_data['title'].'】获得'.$gxz_data['gxz'].'贡献值';
            			$gxz_data['add_time'] = time();
            			$gxz_res = M('event_gxz')->add($gxz_data);
            			M('Member')->where("member_id=" . $gxz_data['member_id'])->setInc('gxz', $gxz_data['gxz']);
    			    }
    			}
            }

		    
            $data['status'] = 1;
			$data['info'] = '放货成功';
			$this->ajaxReturn($data);
        }
    }
    /**
     * 卖家申诉
     */
    public function order_shensu(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('pai_order')->where('id='.$id.' AND sell_uid='.$member_id)->find();
            if(!$order){
                $data['status'] = 3;
                $data['info'] = '无订单信息';
                $this->ajaxReturn($data);
            }
            if($order['status']!=2 && $order['status']!=6){
                $data['status'] = 4;
                $data['info'] = '订单已提交，请勿重复提交';
                $this->ajaxReturn($data);
            }
            $order_data['id'] = $id;
			$order_data['status'] = 7;
			$order_data['shensu_info'] = trim(I('shensu_info'));
            M('pai_order')->save($order_data);
            
            $data['status'] = 1;
			$data['info'] = '申诉成功，请等待处理，或联系客服处理';
			$this->ajaxReturn($data);
        }
    }
    /**
     * 卖家取消申诉
     */
    public function order_shensu_cancel(){
        if(IS_POST){
            $user = $this->user;
            $member_id = $user['member_id'];
            $id = intval(I('id'));
            if($id<=0){
                $data['status'] = 2;
                $data['info'] = '参数缺失';
                $this->ajaxReturn($data);
            }
            $order = M('pai_order')->where('id='.$id.' AND sell_uid='.$member_id)->find();
            if(!$order){
                $data['status'] = 3;
                $data['info'] = '无订单信息';
                $this->ajaxReturn($data);
            }
            if($order['status']!=7){
                $data['status'] = 4;
                $data['info'] = '订单已不可取消申诉';
                $this->ajaxReturn($data);
            }
            $order_data['id'] = $id;
			$order_data['status'] = 2;
			$order_data['handle_time'] = time();
            M('pai_order')->save($order_data);
            
            $data['status'] = 1;
			$data['info'] = '取消成功，请及时放货';
			$this->ajaxReturn($data);
        }
    }
    /**
     * 获取我的转拍列表（竞拍+置换）
     */
    public function zhuanpai_list(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;

            $member_id = $this->user['member_id'];
            $where = 'sourse=2 AND status=1 AND member_id='.$member_id;

            $count      =  M('pai')->where($where)->count();
            $list = M('pai')->where($where)->limit($num)->page($page)->order('id desc')->select();
            if($list){
                foreach ($list as $k=>$v){
                    $res[$k]['id'] = $v['id'];
                    $res[$k]['title'] = $v['title'];
                    $product = explode(",", $v['pic']);
                    $res[$k]['pic'] = $this->config['oss_url'].$product[0];
                    $res[$k]['yuji_time'] = $v['yuji_time'];
                    $res[$k]['yuji_time_1000'] = $v['yuji_time']*1000;
                    $res[$k]['yuji_date'] = date('Y-m-d',$v['yuji_time']);
                    $res[$k]['type_name'] = '竞拍 '.$this->jingpai_name($v['type']);
                    
                    $res[$k]['money'] = $v['money'];
                    $res[$k]['yuan_money'] = $v['yuan_money'];
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
    
    
	
	

}