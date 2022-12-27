<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class LoginController extends CommonController {
    /**
     * 获取access_token 舍弃
     */
    public function get_access_token(){
        $at['token'] = uniqid();
        $at['time'] = time();
        S($at['token'] , $at);
        $at['status']=1;
        $this->ajaxReturn($at);
    }
    /**
     * 签名验证  舍弃
     */
    public function checkSign($sign){
    	if(!$sign){
    	    $data['status']=2;
    	    $data['info']='验签失败';
            $this->ajaxReturn($data);
    	}
    	$sign_arr = explode('-', $sign);
    	if(count($sign_arr) != 2){
    	    $data['status']=3;
    	    $data['info']='验签失败';
            $this->ajaxReturn($data);
    	}
    	$token = S($sign_arr[1]);
    	if(!$token){
    	    $data['status']=4;
    	    $data['info']='验签失败';
            $this->ajaxReturn($data);
    	}
    	$signMd5 = md5($token['token'].$token['time']);
    	if($signMd5 != $sign_arr[0]){
    	    $data['status']=5;
    	    $data['info']='验签失败';
            $this->ajaxReturn($data);
    	}
    	// 验证成功则删除
    	S($sign_arr[1],null);
    }
    /**
     * 登录
     */
    public function login(){
        $data['status']=0;
	    $data['info']='请及时更新APP';
        $this->ajaxReturn($data);
        
    }
    
    /**
     * 登录
     */
    public function login_new_v2(){
        if(IS_POST){
            $token = uniqid();
            $login_type = intval(I('login_type'));
            if($login_type<=0){
                $data['status']=2;
        	    $data['info']='登录方式参数错误';
                $this->ajaxReturn($data);
            }
            //账号密码方式
            if($login_type==1){
                $phone = trim(I('post.phone'));
                $pwd = md5(md5(trim(I('post.pwd'))));
				if($phone=='' || trim(I('post.pwd'))=='' ){
					$data['status']=3;
					$data['info']='请填写完整信息';
					$this->ajaxReturn($data);
				}
                $member = M('Member')->where('(phone="'.$phone.'" OR code_id="'.$phone.'" ) AND pwd="'.$pwd.'"')->find();
                if(!$member){
                    $data['status']=2;
                    $data['info']="账号、密码错误";
                    $this->ajaxReturn($data);
                }
            }
            //微信授权登录方式
            elseif($login_type==2){
				$openid = trim(I('openid'));
				$access_token = trim(I('access_token'));
				if($openid=='' || empty($openid) ){
					$data['status']=3;
					$data['info']='openid参数缺失';
					$this->ajaxReturn($data);
				}
				if($access_token=='' || empty($access_token) ){
					$data['status']=4;
					$data['info']='access_token参数缺失';
					$this->ajaxReturn($data);
				}
				$member = M('Member')->where('weixin_openid="'.$openid.'"')->find();
                if(!$member){
					$url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
					$res = $this->https_request($url);
					$res = json_decode($res,true);
					if($res['headimgurl']){
					//注册
						//写入注册数据
						$reg_data['weixin_openid'] = $openid;
						$reg_data['nickname'] = $res['nickname'];
						$reg_data['head'] = $res['headimgurl'];
						$reg_data['status'] = 0;
						$reg_data['reg_time'] = time();
						$reg_data['reg_ip'] = get_client_ip();
						$reg_data['pwd_trade'] = md5(md5('123456'));
						$reg_data['reg_type'] = $login_type;
						$uid = M('member')->add($reg_data);
						$member = M('Member')->where('member_id='.$uid)->find();
					}else{
						$data['status']=5;
						$data['info']='微信用户信息获取失败';
						$this->ajaxReturn($data);
					}
				}	
            }
            //支付宝授权登录方式
            elseif($login_type==3){
                $user_id = trim(I('post.user_id'));
				if($user_id=='' || empty($user_id)){
					$data['status']=3;
					$data['info']='支付宝id参数缺失';
					$this->ajaxReturn($data);
				}
				$member = M('Member')->where('alipay_user_id="'.$user_id.'"')->find();
                if(!$member){
				//注册
                    //写入注册数据
					$reg_data['alipay_user_id'] = $user_id;
					$reg_data['nickname'] = I('post.nick_name');
					$reg_data['head'] = I('post.avatar');
					$reg_data['status'] = 0;
					$reg_data['reg_time'] = time();
					$reg_data['reg_ip'] = get_client_ip();
					$reg_data['pwd_trade'] = md5(md5('123456'));
					$reg_data['reg_type'] = $login_type;
					$uid = M('member')->add($reg_data);
					$member = M('Member')->where('member_id='.$uid)->find();
                }	
            }
            //uni一键登录方式
            elseif($login_type==4){
                $phone = trim(I('post.phone'));
				if($phone==''){
					$data['status']=3;
					$data['info']='手机号不能为空';
					$this->ajaxReturn($data);
				}
				$member = M('Member')->where('phone="'.$phone.'"')->find();
                if(!$member){
				//注册
                    //写入注册数据
					$reg_data['phone'] = $phone;
					$reg_data['nickname'] = $this->nickname();
					$reg_data['head'] = '/Uploads/head/default.jpg';
					$reg_data['status'] = 0;
					$reg_data['reg_time'] = time();
					$reg_data['reg_ip'] = get_client_ip();
					$reg_data['pwd_trade'] = md5(md5('123456'));
					$reg_data['reg_type'] = $login_type;
					$uid = M('member')->add($reg_data);
					$member = M('Member')->where('member_id='.$uid)->find();
                }
            }
            //验证
            if($member['status']==2){
                if(time()>$member['open_time']){
                    M('Member')->where('member_id='.$member['member_id'])->setField('status',1);
                    M('Member')->where('member_id='.$member['member_id'])->setField('open_time',0);
                }else{
                    $data['status']=2;
                    if($member['open_time']>0){
                        $data['info']="您的账号已被禁用，".date('Y-m-d H:i',$member['open_time']).'解封';
                    }else{
                        $data['info']="您的账号已被禁用";
                    }
                    $this->ajaxReturn($data);
                }
            }
            
            $time = time();
    	    $today = strtotime('today');
    	    $new_ip = get_client_ip();
            $mem_data['login_ip'] = $new_ip;
            $mem_data['login_time']= $time;
    		$mem_data['token']= $token;
            $where['member_id'] = $member['member_id'];
            $r = M('Member')->where($where)->save($mem_data);
            if($r===false){
                $data['status']=2;
                $data['info']="服务器繁忙,请稍后重试";
                $this->ajaxReturn($data);
            }
            $login_data['member_id'] = $member['member_id'];
    		$login_data['login_ip'] = $new_ip;
    		$login_data['login_time'] = $time;
    		$login_data['token']= $token;
    		$login_data['login_type']= $login_type;
    		M('Member_login')->add($login_data);
    		
    		$online_data['member_id'] = $member['member_id'];
            $online_data['add_time'] = time();
            $online_data['controller'] = strtolower(CONTROLLER_NAME);
    		$online_data['action'] = strtolower(ACTION_NAME);
    		$online_data['url'] = str_replace(__APP__,'',__SELF__);
    		$online_data['ip'] = $new_ip;
    		if(IS_GET){
    			$online_data['type'] = 'get';
    		}
    		if(IS_POST){
    			$online_data['type'] = 'post';
    			$online_data['post_data'] = json_encode($_POST);
    		}

            M('member_online')->add($online_data);
            
            $data['status']=1;
    	    $data['info']='登录成功';
    	    $data['token'] = $token.'-'.$member['member_id'];
            $this->ajaxReturn($data);
        }
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
	
    /**
     * 退出
     */
    public function logout(){
        if ($_SESSION['USER_KEY_ID']!=null){
            session(null);
        }
        $this->redirect('Wap/Index/index');
    }
    /**
     * 忘记密码
     */
    public function findpwd(){
        if(IS_POST){
            if(empty($_POST['phone'])){
                $data['status']=2;
                $data['info']="请填写手机号";
                $this->ajaxReturn($data);
            }
            if(empty($_POST['code'])){
                $data['status']=2;
                $data['info']="请填写验证码";
                $this->ajaxReturn($data);
            }
			if(empty($_POST['pwd'])){
                $data['status']=2;
                $data['info']="请输入新密码！";
                $this->ajaxReturn($data);
            }
			if (strlen($_POST['pwd']) < 6 || strlen($_POST['pwd']) > 10 ){
                $data['status'] = 0;
                $data['info'] = '密码长度在6~10位！';
                $this->ajaxReturn($data);
            }
            if(preg_match("/^[a-zA-Z0-9]{1,}$/", $_POST['pwd']) == false){
                $data['status'] = 0;
                $data['info'] = '密码只允许数字字母！';
                $this->ajaxReturn($data);
            }
			
			
			$info = M('Member')->where(array('phone'=>$_POST['phone']))->find();
            if($info==false){
                $data['status']=2;
                $data['info']="用户不存在";
                $this->ajaxReturn($data);
            }
			
			$mcode = M('mobile_code')->where('phone="'.$info['phone'].'" AND type="findpwd"')->order('add_time desc')->find();
			if($mcode){
				if($mcode['code'] != $_POST['code']){
					$data['status'] = 0;
					$data['info'] = '手机验证码不正确！';
					$this->ajaxReturn($data);
				}
				if($mcode['stop_time'] < time()){
					$data['status'] = 0;
					$data['info'] = '验证码已过期！';
					$this->ajaxReturn($data);
				}
			}else{
				$data['status'] = 0;
                $data['info'] = '手机验证码不正确！';
                $this->ajaxReturn($data);
			}
            $member_info = M('member')->where(array('phone'=>$_POST['phone']))->find();
            $member_newPwd = md5(md5($_POST['pwd']));
            $r = M('member')->where(array('member_id'=>$member_info['member_id']))->setField('pwd',$member_newPwd);
            if($r===false){
                $data['status']=2;
                $data['info']="服务器繁忙,请稍后重试";
                $this->ajaxReturn($data);
            }else{
                session('phone',null);
                $data['status']=1;
                $data['info']="设置成功";
                $this->ajaxReturn($data);
            }
        }else{
            $this->display();
        }
    }
    
    
    /**
     * 收款资料
     */
    public function wanshan(){
		if(!session('USER_KEY_ID')){
			$this->redirect('Wap/Login/index');
            return;
		}
        if(IS_POST){
			$user = M('Member')->where(array('member_id'=>session('USER_KEY_ID')))->find();
			$mcode = M('mobile_code')->where('phone="'.I('post.phone').'" AND type="wanshan"')->order('add_time desc')->find();
			if($mcode){
				if($mcode['code'] != $_POST['code']){
					$data['status'] = 0;
					$data['info'] = '手机验证码不正确！';
					$this->ajaxReturn($data);
				}
				if($mcode['stop_time'] < time()){
					$data['status'] = 0;
					$data['info'] = '验证码已过期！';
					$this->ajaxReturn($data);
				}
			}else{
				$data['status'] = 0;
                $data['info'] = '手机验证码不正确！';
                $this->ajaxReturn($data);
			}
			
            $member_id = session('USER_KEY_ID');
            $user_data['member_id'] = session('USER_KEY_ID');
            $user_data['weixin'] = I('post.weixin'); 
            $user_data['alipay'] = I('post.alipay'); 
			$user_data['name'] = I('post.name'); 
			$user_data['phone'] = I('post.phone'); 
            $r = M('member')->save($user_data);
            $data['status']=1;
            $data['info']='提交成功';
            $this->ajaxReturn($data);
        }else{
			$user = M('Member')->where(array('member_id'=>session('USER_KEY_ID')))->find();
            $this->assign('user', $user);
            $this->display();
        }
    }
    /**
     * 生成昵称
     */
    public function nickname()
    {
        $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYabcdefghjkmnpqrstuvwxy';
        $nickname = '';
        for ($i = 0; $i < 8; $i++) {
            $nickname .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        if (M('Member')->where('nickname="' . $nickname . '"')->find()) {
            $nickname = $this->nickname();
        }
        return $nickname;
    }
}
// <?php
// namespace Home\Controller;
// use Common\Controller\CommonController;
// class LoginController extends CommonController {
//     /**
//      * 获取access_token 舍弃
//      */
//     public function get_access_token(){
//         $at['token'] = uniqid();
//         $at['time'] = time();
//         S($at['token'] , $at);
//         $at['status']=1;
//         $this->ajaxReturn($at);
//     }
//     /**
//      * 签名验证  舍弃
//      */
//     public function checkSign($sign){
//     	if(!$sign){
//     	    $data['status']=2;
//     	    $data['info']='验签失败';
//             $this->ajaxReturn($data);
//     	}
//     	$sign_arr = explode('-', $sign);
//     	if(count($sign_arr) != 2){
//     	    $data['status']=3;
//     	    $data['info']='验签失败';
//             $this->ajaxReturn($data);
//     	}
//     	$token = S($sign_arr[1]);
//     	if(!$token){
//     	    $data['status']=4;
//     	    $data['info']='验签失败';
//             $this->ajaxReturn($data);
//     	}
//     	$signMd5 = md5($token['token'].$token['time']);
//     	if($signMd5 != $sign_arr[0]){
//     	    $data['status']=5;
//     	    $data['info']='验签失败';
//             $this->ajaxReturn($data);
//     	}
//     	// 验证成功则删除
//     	S($sign_arr[1],null);
//     }
    
//     /**
//      * 登录
//      */
//     public function login(){
//         if(IS_POST){
//             $token = uniqid();
//             $login_type = intval(I('login_type'));
//             if($login_type<=0){
//                 $data['status']=2;
//         	    $data['info']='登录方式参数错误';
//                 $this->ajaxReturn($data);
//             }
//             //账号密码方式
//             if($login_type==1){
//                 $phone = trim(I('post.phone'));
//                 $pwd = md5(md5(trim(I('post.pwd'))));
// 				if($phone=='' || trim(I('post.pwd'))=='' ){
// 					$data['status']=3;
// 					$data['info']='请填写完整信息';
// 					$this->ajaxReturn($data);
// 				}
//                 $member = M('Member')->where('(phone="'.$phone.'" OR code_id="'.$phone.'" ) AND pwd="'.$pwd.'"')->find();
//                 if(!$member){
//                     $data['status']=2;
//                     $data['info']="账号、密码错误";
//                     $this->ajaxReturn($data);
//                 }
//             }
//             //微信授权登录方式
//             elseif($login_type==2){
// 				$openid = trim(I('openid'));
// 				$access_token = trim(I('access_token'));
// 				if($openid=='' || empty($openid) ){
// 					$data['status']=3;
// 					$data['info']='openid参数缺失';
// 					$this->ajaxReturn($data);
// 				}
// 				if($access_token=='' || empty($access_token) ){
// 					$data['status']=4;
// 					$data['info']='access_token参数缺失';
// 					$this->ajaxReturn($data);
// 				}
// 				$member = M('Member')->where('weixin_openid="'.$openid.'"')->find();
//                 if(!$member){
// 					$url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
// 					$res = $this->https_request($url);
// 					$res = json_decode($res,true);
// 					if($res['headimgurl']){
// 					//注册
// 						//写入注册数据
// 						$reg_data['weixin_openid'] = $openid;
// 						$reg_data['nickname'] = $res['nickname'];
// 						$reg_data['head'] = $res['headimgurl'];
// 						$reg_data['status'] = 0;
// 						$reg_data['reg_time'] = time();
// 						$reg_data['reg_ip'] = get_client_ip();
// 						$reg_data['pwd_trade'] = md5(md5('123456'));
// 						$reg_data['reg_type'] = $login_type;
// 						$uid = M('member')->add($reg_data);
// 						$member = M('Member')->where('member_id='.$uid)->find();
// 					}else{
// 						$data['status']=5;
// 						$data['info']='微信用户信息获取失败';
// 						$this->ajaxReturn($data);
// 					}
// 				}	
//             }
//             //支付宝授权登录方式
//             elseif($login_type==3){
//                 $user_id = trim(I('post.user_id'));
// 				if($user_id=='' || empty($user_id)){
// 					$data['status']=3;
// 					$data['info']='支付宝id参数缺失';
// 					$this->ajaxReturn($data);
// 				}
// 				$member = M('Member')->where('alipay_user_id="'.$user_id.'"')->find();
//                 if(!$member){
// 				//注册
//                     //写入注册数据
// 					$reg_data['alipay_user_id'] = $user_id;
// 					$reg_data['nickname'] = I('post.nick_name');
// 					$reg_data['head'] = I('post.avatar');
// 					$reg_data['status'] = 0;
// 					$reg_data['reg_time'] = time();
// 					$reg_data['reg_ip'] = get_client_ip();
// 					$reg_data['pwd_trade'] = md5(md5('123456'));
// 					$reg_data['reg_type'] = $login_type;
// 					$uid = M('member')->add($reg_data);
// 					$member = M('Member')->where('member_id='.$uid)->find();
//                 }	
//             }
//             //uni一键登录方式
//             elseif($login_type==4){
//                 $phone = trim(I('post.phone'));
// 				if($phone==''){
// 					$data['status']=3;
// 					$data['info']='手机号不能为空';
// 					$this->ajaxReturn($data);
// 				}
// 				$member = M('Member')->where('phone="'.$phone.'"')->find();
//                 if(!$member){
// 				//注册
//                     //写入注册数据
// 					$reg_data['phone'] = $phone;
// 					$reg_data['nickname'] = $this->nickname();
// 					$reg_data['head'] = '/Uploads/head/default.jpg';
// 					$reg_data['status'] = 0;
// 					$reg_data['reg_time'] = time();
// 					$reg_data['reg_ip'] = get_client_ip();
// 					$reg_data['pwd_trade'] = md5(md5('123456'));
// 					$reg_data['reg_type'] = $login_type;
// 					$uid = M('member')->add($reg_data);
// 					$member = M('Member')->where('member_id='.$uid)->find();
//                 }
//             }
//             //验证
//             if($member['status']==2){
//                 if(time()>$member['open_time']){
//                     M('Member')->where('member_id='.$member['member_id'])->setField('status',1);
//                     M('Member')->where('member_id='.$member['member_id'])->setField('open_time',0);
//                 }else{
//                     $data['status']=2;
//                     if($member['open_time']>0){
//                         $data['info']="您的账号已被禁用，".date('Y-m-d H:i',$member['open_time']).'解封';
//                     }else{
//                         $data['info']="您的账号已被禁用";
//                     }
//                     $this->ajaxReturn($data);
//                 }
//             }
            
//             $time = time();
//     	    $today = strtotime('today');
//     	    $new_ip = get_client_ip();
//             $mem_data['login_ip'] = $new_ip;
//             $mem_data['login_time']= $time;
//     		$mem_data['token']= $token;
//             $where['member_id'] = $member['member_id'];
//             $r = M('Member')->where($where)->save($mem_data);
//             if($r===false){
//                 $data['status']=2;
//                 $data['info']="服务器繁忙,请稍后重试";
//                 $this->ajaxReturn($data);
//             }
//             $login_data['member_id'] = $member['member_id'];
//     		$login_data['login_ip'] = $new_ip;
//     		$login_data['login_time'] = $time;
//     		$login_data['token']= $token;
//     		$login_data['login_type']= $login_type;
//     		M('Member_login')->add($login_data);
    		
//     		$online_data['member_id'] = $member['member_id'];
//             $online_data['add_time'] = time();
//             $online_data['controller'] = strtolower(CONTROLLER_NAME);
//     		$online_data['action'] = strtolower(ACTION_NAME);
//     		$online_data['url'] = str_replace(__APP__,'',__SELF__);
//     		$online_data['ip'] = $new_ip;
//     		if(IS_GET){
//     			$online_data['type'] = 'get';
//     		}
//     		if(IS_POST){
//     			$online_data['type'] = 'post';
//     			$online_data['post_data'] = json_encode($_POST);
//     		}

//             M('member_online')->add($online_data);
            
//             $data['status']=1;
//     	    $data['info']='登录成功';
//     	    $data['token'] = $token.'-'.$member['member_id'];
//             $this->ajaxReturn($data);
//         }
//     }

    
//     // 二维数组根据某键值去重
//  	public function array_unset_tt($arr,$key){     
//         //建立一个目标数组  
//         $res = array();        
//         foreach ($arr as $value) {           
//           //查看有没有重复项  
//           if(isset($res[$value[$key]])){  
//                  //有：销毁  
//                  unset($value[$key]);  
//           }  
//           else{   
//                 $res[$value[$key]] = $value;  
//           }    
//         }  
//         return $res;  
//     }
	
//     /**
//      * 退出
//      */
//     public function logout(){
//         if ($_SESSION['USER_KEY_ID']!=null){
//             session(null);
//         }
//         $this->redirect('Wap/Index/index');
//     }
//     /**
//      * 忘记密码
//      */
//     public function findpwd(){
//         if(IS_POST){
//             if(empty($_POST['phone'])){
//                 $data['status']=2;
//                 $data['info']="请填写手机号";
//                 $this->ajaxReturn($data);
//             }
//             if(empty($_POST['code'])){
//                 $data['status']=2;
//                 $data['info']="请填写验证码";
//                 $this->ajaxReturn($data);
//             }
// 			if(empty($_POST['pwd'])){
//                 $data['status']=2;
//                 $data['info']="请输入新密码！";
//                 $this->ajaxReturn($data);
//             }
// 			if (strlen($_POST['pwd']) < 6 || strlen($_POST['pwd']) > 10 ){
//                 $data['status'] = 0;
//                 $data['info'] = '密码长度在6~10位！';
//                 $this->ajaxReturn($data);
//             }
//             if(preg_match("/^[a-zA-Z0-9]{1,}$/", $_POST['pwd']) == false){
//                 $data['status'] = 0;
//                 $data['info'] = '密码只允许数字字母！';
//                 $this->ajaxReturn($data);
//             }
			
			
// 			$info = M('Member')->where(array('phone'=>$_POST['phone']))->find();
//             if($info==false){
//                 $data['status']=2;
//                 $data['info']="用户不存在";
//                 $this->ajaxReturn($data);
//             }
			
// 			$mcode = M('mobile_code')->where('phone="'.$info['phone'].'" AND type="findpwd"')->order('add_time desc')->find();
// 			if($mcode){
// 				if($mcode['code'] != $_POST['code']){
// 					$data['status'] = 0;
// 					$data['info'] = '手机验证码不正确！';
// 					$this->ajaxReturn($data);
// 				}
// 				if($mcode['stop_time'] < time()){
// 					$data['status'] = 0;
// 					$data['info'] = '验证码已过期！';
// 					$this->ajaxReturn($data);
// 				}
// 			}else{
// 				$data['status'] = 0;
//                 $data['info'] = '手机验证码不正确！';
//                 $this->ajaxReturn($data);
// 			}
//             $member_info = M('member')->where(array('phone'=>$_POST['phone']))->find();
//             $member_newPwd = md5(md5($_POST['pwd']));
//             $r = M('member')->where(array('member_id'=>$member_info['member_id']))->setField('pwd',$member_newPwd);
//             if($r===false){
//                 $data['status']=2;
//                 $data['info']="服务器繁忙,请稍后重试";
//                 $this->ajaxReturn($data);
//             }else{
//                 session('phone',null);
//                 $data['status']=1;
//                 $data['info']="设置成功";
//                 $this->ajaxReturn($data);
//             }
//         }else{
//             $this->display();
//         }
//     }
    
    
//     /**
//      * 收款资料
//      */
//     public function wanshan(){
// 		if(!session('USER_KEY_ID')){
// 			$this->redirect('Wap/Login/index');
//             return;
// 		}
//         if(IS_POST){
// 			$user = M('Member')->where(array('member_id'=>session('USER_KEY_ID')))->find();
// 			$mcode = M('mobile_code')->where('phone="'.I('post.phone').'" AND type="wanshan"')->order('add_time desc')->find();
// 			if($mcode){
// 				if($mcode['code'] != $_POST['code']){
// 					$data['status'] = 0;
// 					$data['info'] = '手机验证码不正确！';
// 					$this->ajaxReturn($data);
// 				}
// 				if($mcode['stop_time'] < time()){
// 					$data['status'] = 0;
// 					$data['info'] = '验证码已过期！';
// 					$this->ajaxReturn($data);
// 				}
// 			}else{
// 				$data['status'] = 0;
//                 $data['info'] = '手机验证码不正确！';
//                 $this->ajaxReturn($data);
// 			}
			
//             $member_id = session('USER_KEY_ID');
//             $user_data['member_id'] = session('USER_KEY_ID');
//             $user_data['weixin'] = I('post.weixin'); 
//             $user_data['alipay'] = I('post.alipay'); 
// 			$user_data['name'] = I('post.name'); 
// 			$user_data['phone'] = I('post.phone'); 
//             $r = M('member')->save($user_data);
//             $data['status']=1;
//             $data['info']='提交成功';
//             $this->ajaxReturn($data);
//         }else{
// 			$user = M('Member')->where(array('member_id'=>session('USER_KEY_ID')))->find();
//             $this->assign('user', $user);
//             $this->display();
//         }
//     }
//     /**
//      * 生成昵称
//      */
//     public function nickname()
//     {
//         $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYabcdefghjkmnpqrstuvwxy';
//         $nickname = '';
//         for ($i = 0; $i < 8; $i++) {
//             $nickname .= $chars[mt_rand(0, strlen($chars) - 1)];
//         }
//         if (M('Member')->where('nickname="' . $nickname . '"')->find()) {
//             $nickname = $this->nickname();
//         }
//         return $nickname;
//     }
// }