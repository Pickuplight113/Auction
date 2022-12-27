<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class WalletController extends CommonController {
    protected $user;
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
    }
    /*
     * 我的钱包页面数据
     */
    public function my_wallet(){
        if(IS_POST){
            $member_id = $this->user['member_id'];
    		$user['rmb'] = $this->user['rmb'];
    		$user['heo'] = $this->user['heo'];
    		
    		
    		$list[1]['icon'] = $this->config['oss_url'].$this->config['zc_icon1']; 
    		$list[1]['name'] = $this->config['zc_name1']; 
            $list[1]['text'] = $this->user['gxz'];
            $list[1]['url'] = '/Wallet/gxz_record';
            
            $list[2]['icon'] = $this->config['oss_url'].$this->config['zc_icon2']; 
    		$list[2]['name'] = $this->config['zc_name2']; 
    		
    		if($this->user['is_event']==1){
    		    $all_list = M('member')->where('is_event=1')->order('gxz desc,member_id')->select();
        		$my_sort = array_search($member_id,array_column($all_list, 'member_id'))+1;
                $list[2]['text'] = '第'.$my_sort.'名';
    		}else{
    		    $list[2]['text'] = '0';
    		}
    		$list[2]['url'] = null;
    		
            
            $list[3]['icon'] = $this->config['oss_url'].$this->config['zc_icon3']; 
    		$list[3]['name'] = $this->config['zc_name3']; 
            $list[3]['text'] = $this->user['sfj'];
            $list[3]['url'] = '/Wallet/sfj_record';
            
            $list[4]['icon'] = $this->config['oss_url'].$this->config['zc_icon4']; 
    		$list[4]['name'] = $this->config['zc_name4']; 
            $list[4]['text'] = $this->user['chicang'];
            $list[4]['url'] = null;
            
            foreach ($list as $k=>$v){
    		    $list_res[] = $v;
    		}
            
            
		    $data['status'] = 1;
			$data['user'] = $user;
			$data['list'] = $list_res;
			$this->ajaxReturn($data);
        }
	}
	/*
     * 财务明细
     */
    public function record(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;

            $member_id = $this->user['member_id'];
            $where[C("DB_PREFIX") . "member.member_id"] = $member_id;
            $wallet_type = intval(I('wallet_type'));
            $wallet_type = $wallet_type == 0 ? 1 : $wallet_type;
            
            $type = intval(I('type'));
            if ($type > 0) {
                $where[C("DB_PREFIX") . "finance.money_type"] = $type;
            }
            if($wallet_type==1){
                $where[C("DB_PREFIX") . "finance.currency_id"] = 3;
            }elseif($wallet_type==2){
                $where[C("DB_PREFIX") . "finance.currency_id"] = 6;
            }elseif($wallet_type==3){
                $where[C("DB_PREFIX") . "finance.currency_id"] = 7;
            }
            
            $count = M('Finance')
            ->field(C("DB_PREFIX") . "finance.*," . C("DB_PREFIX") . "member.name as username," . C("DB_PREFIX") . "finance_type.name as typename")
            ->join("left join " . C("DB_PREFIX") . "member on " . C("DB_PREFIX") . "member.member_id=" . C("DB_PREFIX") . "finance.member_id")
            ->join("left join " . C("DB_PREFIX") . "finance_type on " . C("DB_PREFIX") . "finance_type.id=" . C("DB_PREFIX") . "finance.type")
            ->where($where)->count(); 
            
            $list = M('Finance')
            ->field(C("DB_PREFIX") . "finance.*," . C("DB_PREFIX") . "member.name as username," . C("DB_PREFIX") . "finance_type.name as typename")
            ->join("left join " . C("DB_PREFIX") . "member on " . C("DB_PREFIX") . "member.member_id=" . C("DB_PREFIX") . "finance.member_id")
            ->join("left join " . C("DB_PREFIX") . "finance_type on " . C("DB_PREFIX") . "finance_type.id=" . C("DB_PREFIX") . "finance.type")
            ->limit($num)->page($page)
            ->where($where)
            ->order('add_time desc')
            ->select();
            
            foreach ($list as $k => $v) {
                $list[$k]['add_time_1000'] = $v['add_time']*1000;
                $list[$k]['add_date'] = date("Y-m-d H:i", $v['add_time']);
                $list[$k]['wallet_name'] = getCurrencynameByCurrency($v['currency_id']);
                unset($list[$k]['member_id']);
                unset($list[$k]['type']);
                unset($list[$k]['currency_id']);
                unset($list[$k]['ip']);
                unset($list[$k]['status']);
                unset($list[$k]['beizhu']);
                unset($list[$k]['username']);
                //$list[$k]['money'] = $v['money_xian'];
                //$list[$k]['money_xian'] = $v['money']; 
            }
            
            $where[C("DB_PREFIX") . "finance.money_type"] = 1;
		    $tongji['shouru'] = M('Finance')->where($where)->join("left join " . C("DB_PREFIX") . "member on " . C("DB_PREFIX") . "member.member_id=" . C("DB_PREFIX") . "finance.member_id")
                ->join("left join " . C("DB_PREFIX") . "finance_type on " . C("DB_PREFIX") . "finance_type.id=" . C("DB_PREFIX") . "finance.type")->sum('money');
		    $where[C("DB_PREFIX") . "finance.money_type"] = 2;
		    $tongji['zhichu'] = M('Finance')->where($where)->join("left join " . C("DB_PREFIX") . "member on " . C("DB_PREFIX") . "member.member_id=" . C("DB_PREFIX") . "finance.member_id")
                ->join("left join " . C("DB_PREFIX") . "finance_type on " . C("DB_PREFIX") . "finance_type.id=" . C("DB_PREFIX") . "finance.type")->sum('money');
            
            $data['status'] = 1;
    		$user['rmb'] = $this->user['rmb'];
    		$user['heo'] = $this->user['heo'];
    		$user['heo_bind'] = $this->user['heo_bind'];
    		$data['user'] = $user;
			$data['tongji'] = $tongji;
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
	/*
     * 转账HEO 页面数据
     */
    public function zhuan() {
        $data['status'] = 1;
        $data['heo_min'] = $this->config['heo_min'];
        $data['heo_max'] = $this->config['heo_max'];
        $data['heo_bei'] = $this->config['heo_bei'];
        $data['heo_tip'] = $this->config['heo_tip'];
		$data['yuan_heo'] = $this->user['heo'];
        $data['heo'] = ($this->user['heo']-200)>0 ? ($this->user['heo']-200) : 0;
        $data['rmb_min'] = $this->config['rmb_min'];
        $data['rmb_max'] = $this->config['rmb_max'];
        $data['rmb_bei'] = $this->config['rmb_bei'];
        $data['rmb'] = $this->user['rmb'];
		$data['tip'] = '只能转账多于200的部分';
		$this->ajaxReturn($data);
    }
	
	/*
     * 提交转账
     */
    public function submit_zhuan() {
		if (IS_POST) {
		    $user = $this->user;
		    $member_id = $this->user['member_id'];
		    
            $phone = I('post.phone');
            $num = intval(I('post.num'));
			$pwd_trade = md5(md5(I('pwd_trade')));
			$type = intval(I('post.type'));
			
			if($pwd_trade != $user['pwd_trade']){
				$data['status']=2;
				$data['info']='密码错误';
				$this->ajaxReturn($data);
			}

            if($phone==''){
                $data['status'] = 0;
                $data['info'] = '请输入对方账号';
                $this->ajaxReturn($data);
            }
            
            if($type==1){
                $type_name = 'HEO';
                $wallet_type = 'heo';
                $wallet_id = 6;
                
                if($num<$this->config['heo_min'] || $num>$this->config['heo_max']){
    				$data['status']=2;
    				$data['info']='转账HEO数量应在'.$this->config['heo_min'].'~'.$this->config['heo_max'];
    				$this->ajaxReturn($data);
    			}
    			$yu = $num % $this->config['heo_bei'];
    			if($yu>0){
    				$data['status']=2;
    				$data['info']='转账HEO数量应为'.$this->config['heo_bei'].'的倍数';
    				$this->ajaxReturn($data);
    			}
    			if($num > ($user['heo']-200)){
    				$data['status']=2;
    				$data['info']='可用HEO余额不足';
    				$this->ajaxReturn($data);
    			}
            }elseif($type==2){
                $type_name = '现金余额';
                $wallet_type = 'rmb';
                $wallet_id = 3;
                
                if($num<$this->config['rmb_min'] || $num>$this->config['rmb_max']){
    				$data['status']=2;
    				$data['info']='转账现金数量应在'.$this->config['rmb_min'].'~'.$this->config['rmb_max'];
    				$this->ajaxReturn($data);
    			}
    			$yu = $num % $this->config['rmb_bei'];
    			if($yu>0){
    				$data['status']=2;
    				$data['info']='转账现金数量应为'.$this->config['rmb_bei'].'的倍数';
    				$this->ajaxReturn($data);
    			}
    			if($num > $user['rmb']){
    				$data['status']=2;
    				$data['info']='可用现金余额不足';
    				$this->ajaxReturn($data);
    			}
            }else{
                $data['status'] = 3;
                $data['info'] = '请选择转账钱包';
                $this->ajaxReturn($data);
            }
            
            $jieshou = M('Member')->where('phone="'.$phone.'" OR code_id="'.$phone.'"')->field('member_id,nickname,status')->find();
            if(!$jieshou){
                $data['status'] = 5;
                $data['info'] = '对方账号不存在';
                $this->ajaxReturn($data);
            }
			if($jieshou['member_id'] == $member_id){
                $data['status'] = 6;
                $data['info'] = '不能转给自己';
                $this->ajaxReturn($data);
            }
            if($type==1){
    			$you = $this->checkmyteam($member_id,$jieshou['member_id']);
    			if(!$you){
    				$data['status'] = 7;
                    $data['info'] = '对方不是您伞下成员';
                    $this->ajaxReturn($data);
    			}
            }

            $zhuan_data['zhuanchu_id'] = $member_id;
            $zhuan_data['jieshou_id'] = $jieshou['member_id'];
            $zhuan_data['num'] = $num;
			$zhuan_data['type'] = $type_name;
            $zhuan_data['add_time'] = time();

            $r = M('zhuan')->add($zhuan_data);
            if($r){
                //转出用户减少
                M('Member')->where("member_id=" . $zhuan_data['zhuanchu_id'])->setDec($wallet_type, $num);
                //资金变化记录
                addFinance($zhuan_data['zhuanchu_id'], 3, '转账'.$type_name.'给'.$jieshou['nickname'], $num, 2, $wallet_id);
				//接收用户增加
                M('Member')->where("member_id=" . $zhuan_data['jieshou_id'])->setInc($wallet_type, $num);
                //资金变化记录
                addFinance($zhuan_data['jieshou_id'], 4, '接收'.$user['nickname'].'的'.$type_name, $num, 1, $wallet_id);
				
                $data['status'] = 1;
                $data['info'] = '转账成功';
                $this->ajaxReturn($data);
            }else{
                $data['status'] = 0;
                $data['info'] = '转账失败';
                $this->ajaxReturn($data);
            }
        }
    }
	/*
     * 充值页面数据
     */
    public function recharge() {
		$data['status'] = 1;
		
		$data['alipay_rmb_min'] = $this->config['alipay_rmb_min'];
        $data['alipay_rmb_max'] = $this->config['alipay_rmb_max'];
        $data['alipay_rmb_bei'] = $this->config['alipay_rmb_bei'];
        $data['weixin_rmb_min'] = $this->config['weixin_rmb_min'];
        $data['weixin_rmb_max'] = $this->config['weixin_rmb_max'];
        $data['weixin_rmb_bei'] = $this->config['weixin_rmb_bei'];
		
        $data['alipay_heo_min'] = $this->config['alipay_heo_min'];
        $data['alipay_heo_max'] = $this->config['alipay_heo_max'];
        $data['alipay_heo_bei'] = $this->config['alipay_heo_bei'];
        $data['weixin_heo_min'] = $this->config['weixin_heo_min'];
        $data['weixin_heo_max'] = $this->config['weixin_heo_max'];
        $data['weixin_heo_bei'] = $this->config['weixin_heo_bei'];
		
		$data['rmb_heo_min'] = $this->config['rmb_heo_min'];
        $data['rmb_heo_max'] = $this->config['rmb_heo_max'];
        $data['rmb_heo_bei'] = $this->config['rmb_heo_bei'];

		$this->ajaxReturn($data);
	}
	/*
     * 提交充值
     */
    public function submit_recharge() {
		if (IS_POST) {
		    $user = $this->user;
		    $member_id = $this->user['member_id'];
		    
			$money = floatval(I('post.money'));
			$money_type = intval(I('post.money_type'));
			$pay_type = intval(I('post.pay_type'));

            if($money<=0){
                $data['status'] = 0;
                $data['info'] = '请输入充值金额';
                $this->ajaxReturn($data);
            }
			if($money_type<=0){
                $data['status'] = 0;
                $data['info'] = '请选择充值金额类型';
                $this->ajaxReturn($data);
            }
			if($pay_type<=0){
                $data['status'] = 0;
                $data['info'] = '请选择支付方式';
                $this->ajaxReturn($data);
            }
            //充值现金
            if($money_type==1){
				//微信支付
				if($pay_type==1){
					
					//充值记录
					$recharge_data['member_id'] = $member_id;
					$recharge_data['money'] = $total_amount;
					$recharge_data['money_type'] = $money_type;
					$recharge_data['pay_type'] = $pay_type;
					$recharge_data['sn'] = $out_trade_no;
					$recharge_data['status'] = 1;
					$recharge_data['add_time'] = time();
					M('recharge')->add($recharge_data);
				}
				//支付宝支付
				elseif($pay_type==2){
					
					if($money<$this->config['alipay_rmb_min']){
						$data['status'] = 2;
						$data['info'] = '最低充值金额'.$this->config['alipay_rmb_min'];
						$this->ajaxReturn($data);
					}
					if($money<$this->config['alipay_rmb_min']){
						$data['status'] = 2;
						$data['info'] = '最低充值金额'.$this->config['alipay_rmb_min'];
						$this->ajaxReturn($data);
					}
					
					
					//导入支付宝类
					Vendor('Alipay.aop.AopCertClient');
					Vendor('Alipay.aop.request.AlipayTradeAppPayRequest');
					
					$aop = new \AopCertClient();
					$aliConfig = C('ALIPAY_CONFIG');
					
					$aop->gatewayUrl = $aliConfig['gatewayUrl'];
        			$aop->appId = $aliConfig['appId'];
        			$aop->rsaPrivateKey = $aliConfig['rsaPrivateKey'];
        			$aop->alipayrsaPublicKey = $aliConfig['alipayrsaPublicKey'];
        			$aop->apiVersion = '1.0';
        			$aop->signType = 'RSA2';
        			$aop->postCharset = 'UTF-8';
        			$aop->format = 'json';

					//商户订单编号
					$out_trade_no = $this->get_sn();
					//描述
					$body = '充值现金余额';
					//标题
					$subject = '充值现金余额';
					//金额
					$total_amount = sprintf("%.2f", $money);
					
					$request = new \AlipayTradeAppPayRequest();
					$bizContent['body'] = $body;
					$bizContent['subject'] = $subject;
					$bizContent['out_trade_no'] = $out_trade_no;
				    $bizContent['total_amount'] = $total_amount;
					$bizContent['product_code'] = 'QUICK_MSECURITY_PAY';
					$bizContent_new = json_encode($bizContent);
					$request->setBizContent($bizContent_new);
					//异步回调地址
					$request->setNotifyUrl($aliConfig['notifyUrl']);
					$result = $aop->sdkExecute($request);
					
					//充值记录
					$recharge_data['member_id'] = $member_id;
					$recharge_data['money'] = $total_amount;
					$recharge_data['money_type'] = $money_type;
					$recharge_data['pay_type'] = $pay_type;
					$recharge_data['sn'] = $out_trade_no;
					$recharge_data['status'] = 1;
					$recharge_data['add_time'] = time();
					M('recharge')->add($recharge_data);
					
					$data['status'] = 1;
					$data['data'] = $result;
					$this->ajaxReturn($data);
				}
				else{
					$data['status'] = 3;
					$data['info'] = '支付方式错误';
					$this->ajaxReturn($data);
				}
            }
			//HEO充值
			elseif($money_type==2){
				//微信支付
				if($pay_type==1){
					
				}
				//支付宝支付
				elseif($pay_type==2){
					//导入支付宝类
					Vendor('Alipay.aop.AopCertClient');
					Vendor('Alipay.aop.request.AlipayTradeAppPayRequest');
					
					$aop = new \AopCertClient();
					$aliConfig = C('ALIPAY_CONFIG');
					
					$aop->gatewayUrl = $aliConfig['gatewayUrl'];
        			$aop->appId = $aliConfig['appId'];
        			$aop->rsaPrivateKey = $aliConfig['rsaPrivateKey'];
        			$aop->alipayrsaPublicKey = $aliConfig['alipayrsaPublicKey'];
        			$aop->apiVersion = '1.0';
        			$aop->signType = 'RSA2';
        			$aop->postCharset = 'UTF-8';
        			$aop->format = 'json';

					//商户订单编号
					$out_trade_no = $this->get_sn();
					//描述
					$body = '充值HEO';
					//标题
					$subject = '充值HEO';
					//金额
					$total_amount = sprintf("%.2f", $money);
					
					$request = new \AlipayTradeAppPayRequest();
					$bizContent['body'] = $body;
					$bizContent['subject'] = $subject;
					$bizContent['out_trade_no'] = $out_trade_no;
				    $bizContent['total_amount'] = $total_amount;
					$bizContent['product_code'] = 'QUICK_MSECURITY_PAY';
					$bizContent_new = json_encode($bizContent);
					$request->setBizContent($bizContent_new);
					//异步回调地址
					$request->setNotifyUrl($aliConfig['notifyUrl']);
					$result = $aop->sdkExecute($request);
					
					//充值记录
					$recharge_data['member_id'] = $member_id;
					$recharge_data['money'] = $total_amount;
					$recharge_data['money_type'] = $money_type;
					$recharge_data['pay_type'] = $pay_type;
					$recharge_data['sn'] = $out_trade_no;
					$recharge_data['status'] = 1;
					$recharge_data['add_time'] = time();
					M('recharge')->add($recharge_data);
					
					$data['status'] = 1;
					$data['data'] = $result;
					$this->ajaxReturn($data);
				}
				//现金余额支付
				elseif($pay_type==3){
					
					
					
				}else{
					$data['status'] = 3;
					$data['info'] = '支付方式错误';
					$this->ajaxReturn($data);
				}
 
            }else{
                $data['status'] = 3;
                $data['info'] = '充值金额类型错误';
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
    
    /**
     * 释放记录
     */
    public function sfj_record(){
        $page = intval(I('page'));
        $page = $page == 0 ? 1 : $page;
        $num = intval(I('num'));
        $num = $num == 0 ? 10 : $num;

        $user = $this->user;
		$member_id = $this->user['member_id'];

		$where = 'member_id='.$member_id;
		$type = intval(I('type'));
		if($type>0){
			$where.= ' AND type='.$type;
			$this->assign('type',$type);
		}
		
		$count = M('event_shifang')->where($where)->count();
        $list = M('event_shifang')->where($where)->order("id desc")->limit($num)->page($page)->select();
        if($list){
            foreach ($list as $k=>$v){
                $res[$k]['money'] = $v['money'];
                $res[$k]['money_type'] = 2;
                $res[$k]['typename'] = '释放金变动';
                $res[$k]['content'] = '发布作品【'.$v['title'].'】释放';
                $res[$k]['add_date'] = date('Y-m-d H:i',$v['add_time']); 
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
		$data['money'] = $user['sfj'];
		$data['data'] = $res;
		$this->ajaxReturn($data);
	}
	
	/**
     * 贡献值记录
     */
    public function gxz_record(){
        $page = intval(I('page'));
        $page = $page == 0 ? 1 : $page;
        $num = intval(I('num'));
        $num = $num == 0 ? 10 : $num;

        $user = $this->user;
		$member_id = $this->user['member_id'];

		$where = 'member_id='.$member_id;
		$type = intval(I('type'));
		if($type>0){
			$where.= ' AND money_type='.$type;
			$this->assign('type',$type);
		}
		
		$count = M('event_gxz')->where($where)->count();
        $list = M('event_gxz')->where($where)->order("id desc")->limit($num)->page($page)->select();
        if($list){
            foreach ($list as $k=>$v){
                $res[$k]['money'] = $v['gxz'];
                $res[$k]['money_type'] = $v['money_type'];
                $res[$k]['typename'] = '贡献值变动';
                $res[$k]['content'] = $v['info'];
                $res[$k]['add_date'] = date('Y-m-d H:i',$v['add_time']); 
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
		$data['money'] = $user['gxz'];
		$data['data'] = $res;
		$this->ajaxReturn($data);
	}
    
}