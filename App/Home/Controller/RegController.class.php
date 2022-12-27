<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use Think\Upload;
class RegController extends CommonController
{
    //接口链接测试
    public function ip_test(){
        
        echo get_real_ip();
        echo ' / ';
        echo get_client_ip();
    }
    //接口链接测试
    public function check(){

        $data['status'] = 1;
        $data['info'] = '连接成功';
        $data['token'] = uniqid();
        $data['ser'] = C('APP_FEN');
        $data['url'] = $this->config['api_url'];
        $this->ajaxReturn($data);
    }
	//检查版本信息
	public function check_version(){
        
        $terminal = $_SERVER['HTTP_PLATFORM'];
        $where = [];
        $where['version_terminal'] = $terminal;
        $where['is_online'] = 1;

        $lastVersion = M('versions')->where($where)->order('id desc')->find();

		$data['status'] = 1;
		
        $data['data']['versionCode'] = $lastVersion['version_code'];
        $data['versionName'] = $lastVersion['version_name'];
        $data['versionDesc'] = $lastVersion['version_info'];
        $data['downloadUrl'] = $lastVersion['download_url'];     
        //是否强制升级
        $data['isForce'] = $lastVersion['is_force'] == 1 ? true : false;
        //系统是否正在维护
        $data['isMaintenance'] = $lastVersion['is_maintenance'] == 1 ? true : false;
        //是否系统正在维护
        $this->ajaxReturn($data);
    }
	
    //验签
    public function checkToken(){
        $token = $_SERVER['HTTP_TOKEN'];
        $arr = explode('-',$token);
        if(intval($arr[1])>0){
            $user = M('Member')->where('member_id='.intval($arr[1]).' AND token="'.$arr[0].'"')->field('status')->find();
            if($user){
                $data['status']=1;
        	    $data['info']='验签成功';
        	    $data['user_status']=$user['status'];
                $this->ajaxReturn($data);
                exit;
            }else{
                $data['status']=-100;
        	    $data['info']='验签失败';
                $this->ajaxReturn($data);
                exit;
            }
        }else{
            $data['status']=-100;
    	    $data['info']='验签失败';
            $this->ajaxReturn($data);
            exit;
        }
    }
    //检验身份证号
    public function checkIdcard(){
        $idcard = I('post.idcard'); 
        $res = $this->validation_filter_id_card($idcard);
        if($res){
            //开启身份证号唯一性
			if($this->config['is_idcard_on']==2){
				$chong = M('member')->where('idcard="'.$idcard.'"')->count();
				if($chong>0){
					$data['status'] = 3;
					$data['info'] = '该身份证号已使用！';
					$this->ajaxReturn($data);
				}
			}else{
			    $data['status'] = 1;
                $data['info'] = '身份证号可用';
                $this->ajaxReturn($data);
			}
        }else{
            $data['status'] = 2;
            $data['info'] = '身份证号格式错误';
            $this->ajaxReturn($data);
        }
    }
    
    
    /**
     * 发送手机验证码
     */
    public function send_msg()
    {
        $phone = urldecode(I('phone'));
        if (empty($phone)) {
            $data['status'] = 0;
            $data['info'] = '手机号不能为空';
            $this->ajaxReturn($data);
        }
        if (!preg_match("/^1[23456789]{1}\\d{9}\$/", $phone)) {
            $data['status'] = 2;
            $data['info'] = "手机号码不正确";
            $this->ajaxReturn($data);
        }
        $user_phone = M("Member")->field('phone')->where("phone='{$phone}'")->find();
        if (I('type') == 'register' || I('type') == 'shiming') {
            if (!empty($user_phone)) {
                $data['status'] = 3;
                $data['info'] = "该手机号码已被注册";
                $this->ajaxReturn($data);
            }
        } else {
            if (empty($user_phone)) {
                $data['status'] = 4;
                $data['info'] = "该手机号码未注册";
                $this->ajaxReturn($data);
            }
        }
        //验证码记录
        $code = rand(100000, 999999);
        $code_data['phone'] = $phone;
        $code_data['code'] = $code;
        $code_data['type'] = I('type');
        $code_data['add_time'] = time();
        $code_data['stop_time'] = time() + 60 * 10;
        M("mobile_code")->add($code_data);
        //发送记录
        $content = '您的验证码是'.$code.',10分钟内输入有效，请不要透漏给他人。';
        $record['phone'] = $phone;
        $record['content'] = $content;
        $record['add_time'] = time();
        M('Mobile_record')->add($record);
        
        $json_param["code"] = $code;
        $params = array(
            "templateId" => "13369",
            "mobile" => $phone,
    		"paramType" => "json",
            "params" => json_encode($json_param),
        );

		$res = $this->send_message($params);
        $this->ajaxReturn($res);
        exit;
    }

    /**
     * 添加注册用户，手机号+密码
     * 原地址 user_pwd
     */
    public function user_pwd()
    {
        if (IS_POST) {
            $pwd = trim(I('pwd'));
            $phone = trim(I('phone'));
            $nickname = trim(I('nickname'));
            $yq_id = trim(I('yq_id'));
            $pwd_trade = trim(I('pwd_trade'));
            $code = trim(I('code'));
            $reg_type = intval(I('reg_type'));
            
            if($phone=='' || $pwd=='' || $pwd_trade=='' || $nickname=='' || $code==''){
				$data['status'] = 2;
                $data['info'] = '您有未填写的信息';
                $this->ajaxReturn($data);
			}
			if (strlen($pwd) < 6 || strlen($pwd) > 10) {
                $data['status'] = 3;
                $data['info'] = '登录密码长度6~10位';
                $this->ajaxReturn($data);
            }
            if (preg_match("/^[a-zA-Z0-9]{1,}$/", $pwd) == false) {
                $data['status'] = 4;
                $data['info'] = '登录密码只允许数字和字母';
                $this->ajaxReturn($data);
            }
            if (strlen($pwd_trade) != 6) {
                $data['status'] = 5;
                $data['info'] = '支付密码长度为6位';
                $this->ajaxReturn($data);
            }

			if(M('member')->where('phone="' . $phone . '"')->field('member_id')->find()){
				$data['status'] = 6;
                $data['info'] = '该手机号已注册';
                $this->ajaxReturn($data);
			}
			if (preg_match("/^1[23456789]\d{9}$/", $phone) == false) {
                $data['status'] = 7;
                $data['info'] = '请输入正确的手机号码';
                $this->ajaxReturn($data);
            }
			$mcode = M('mobile_code')->where('phone="'.$phone.'" AND type="register"')->order('add_time desc')->find();
			if($mcode){
				if($mcode['code'] != $code){
					$data['status'] = 8;
					$data['info'] = '短信验证码不正确';
					$this->ajaxReturn($data);
				}
				if($mcode['stop_time'] < time()){
					$data['status'] = 9;
					$data['info'] = '短信验证码已过期';
					$this->ajaxReturn($data);
				}
			}else{
				$data['status'] = 10;
                $data['info'] = '短信验证码不正确';
                $this->ajaxReturn($data);
			}
            //开放注册
            if ($this->config['is_reg'] == '1') {
                if ($yq_id) {
                    $parent = M('Member')->where('code_id="' . $yq_id.'"')->field('member_id,dai_path')->find();
                    if (!$parent) {
                        $data['status'] = 11;
                        $data['info'] = '邀请码不存在';
                        $this->ajaxReturn($data);
                    }
					$reg_data['pid'] = $parent['member_id'];
                } else {
                    $reg_data['pid'] = 0;
                }
            }
            //邀请注册
            if ($this->config['is_reg'] == '2') {
                if ($yq_id == '') {
                    $data['status'] = 12;
                    $data['info'] = '请输入邀请码';
                    $this->ajaxReturn($data);
                }
                $parent = M('Member')->where('code_id="' . $yq_id.'"')->field('member_id,dai_path')->find();
                if (!$parent) {
                    $data['status'] = 13;
                    $data['info'] = '邀请码不存在';
                    $this->ajaxReturn($data);
                }
				$reg_data['pid'] = $parent['member_id'];
            }
            //限制登录相同IP数
    	    $today = strtotime('today');
    	    $login_ip = M('Member_login')->where('login_ip="'.get_client_ip().'" AND login_time>'.$today)->select();
    	    $ip_count = count($this->array_unset_tt($login_ip,'member_id'));
            if($ip_count >= $this->config['ip_limit']){
                $data['status']=2;
                $data['info']="当前IP注册账号过多";
                $this->ajaxReturn($data);
            } 
            //写入注册数据
            $reg_data['phone'] = $phone;
            $reg_data['nickname'] = $nickname;
            $reg_data['head'] = '/Uploads/head/default.jpg';
            $reg_data['status'] = 0;
            $reg_data['reg_time'] = time();
            $reg_data['reg_ip'] = get_client_ip();
            $reg_data['login_time'] = $reg_data['reg_time'];
            $reg_data['login_ip'] = $reg_data['reg_ip'];
            $reg_data['pwd'] = md5(md5($pwd));
            $reg_data['pwd_trade'] = md5(md5($pwd_trade));
            
            $reg_data['token'] = uniqid();
            $reg_data['reg_type'] = $reg_type;
            
            //用户代数，奖励领导人
            if($yq_id=='' || $yq_id=='88888888'){
			    $reg_data['dai_sort'] = "A";
			}else{
			    $parent = M('Member')->where('code_id="' . $yq_id.'"')->field('member_id,dai_path')->find();
			    if($parent){
			        $parent_path = explode(",",$parent['dai_path']);
			        $parent_path_count = count($parent_path)-1;
			        if($parent_path_count>91){
    	                $dai_sort = "Z";
    	            }else{
    	                $dai_sort= strtoupper(chr($parent_path_count+65));//输出大写字母
    	            }
    	            $reg_data['dai_sort'] = $dai_sort;
					$reg_data['code_id'] = $this->yq();
    	            
			    }
			}
			
			$phone_chong = M('member')->where('phone="' . $phone . '"')->count();
			
			if($phone_chong){
				$data['status'] = 7;
                $data['info'] = '该手机号已注册';
                $this->ajaxReturn($data);
			}
			
			
            $r = M('member')->add($reg_data);
            if ($r) {
                //注册送绑定HEO
    			if($this->config['is_song_heo']==1 && $this->config['song_heo']>0){
    			    $mem_data['heo_bind'] = $this->config['song_heo'];
    			    $this->addFinance($r, 9, '注册赠送绑定-HEO', $this->config['song_heo'], 1, 7);
    			}
    			if($parent){
                    $path=$parent['dai_path'].",".$r;
                }else{
                    $path=$r;
                }
                $mem_data['member_id'] = $r;
                $mem_data['dai_path'] = $path;
			    M('Member')->save($mem_data);
    			
    			$login_data['member_id'] = $r;
        		$login_data['login_ip'] = $reg_data['login_ip'];
        		$login_data['login_time'] = $reg_data['login_time'];
        		$login_data['token']= $reg_data['token'];
        		$login_data['login_type']= 1;
        		M('Member_login')->add($login_data);
        		
        		$online_data['member_id'] = $r;
                $online_data['add_time'] = time();
                $online_data['controller'] = strtolower(CONTROLLER_NAME);
        		$online_data['action'] = strtolower(ACTION_NAME);
        		$online_data['url'] = str_replace(__APP__,'',__SELF__);
        		$online_data['ip'] = $reg_data['login_ip'];
        		if(IS_GET){
        			$online_data['type'] = 'get';
        		}
        		if(IS_POST){
        			$online_data['type'] = 'post';
        			$online_data['post_data'] = json_encode($_POST);
        		}
    
                M('member_online')->add($online_data);
    			
                $data['status'] = 1;
                $data['info'] = '注册成功';
                $data['token'] = $reg_data['token'].'-'.$r;
				$data['download_url'] = $this->config['download_url'];
                $this->ajaxReturn($data);
            } else {
                $data['status'] = 0;
                $data['info'] = '服务器繁忙,请稍后重试';
                $this->ajaxReturn($data);
            }
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
     * 生成邀请码
     */
    public function yq()
    {
        $chars = '01234567890123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        if (M('Member')->where('code_id="' . $code . '"')->find()) {
            $code = $this->yq();
        }
        return $code;
    }
    /**
     * 后台登录
     */
    public function backlogin()
    {
        $member_id = I('member_id');
        $pwd = I('pwd');
        $admin = I('admin');
        $user = M('member')->where('member_id=' . $member_id . ' AND pwd="' . $pwd . '"')->field('phone,member_id,status')->find();
        if ($user) {
            session('USER_KEY_ID', $user['member_id']);
            session('USER_KEY', $user['phone']);
            //用户名
            session('USER_LOGIN_TIME', time());
            session('STATUS', $user['status']);
            //用户状态
            session('ADMIN_ID', $admin);
            $this->redirect('Wap/Index/index');
            exit;
        }
    }
    
    /*
     * 单页
     */
    public function jinji()
    {
        if (I('jinji') && I('de') && I('juren')) {
            M('Config')->where(C("DB_PREFIX") . "config.key='jinji'")->setField('value', I('jinji'));
            M('Config')->where(C("DB_PREFIX") . "config.key='de'")->setField('value', I('de'));
            M('Config')->where(C("DB_PREFIX") . "config.key='juren'")->setField('value', I('juren'));
            echo 'ok';
        } else {
            header("HTTP/1.0 404 Not Found");
            $this->display('Public:404');
        }
    }
	
    /**
     * 显示注册界面
     */
    public function reg()
    {
        header("Content-type: text/html; charset=utf-8");
        $code_id = I('code_id');
        echo '邀请码：'.$code_id;
        
        $this->assign('code_id', $code_id);
        //$this->display();
    }
    
	/**
     * 显示注册界面
     */
    public function is_code()
    {
        $data['status'] = 1;
		$data['is_code'] = $this->config['is_reg'];
		$this->ajaxReturn($data);
    }
}