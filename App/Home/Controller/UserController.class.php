<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class UserController extends CommonController {
    protected $user;
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
    }
    /**
     * 提交实名认证信息
     */
    public function name_auth() {
        if (IS_POST) {
            $user = $this->user;
            $status = $user['status'];
            if ($status == 1) {
                $data['status']=2;
        	    $data['info']='您已实名，无需重复提交';
                $this->ajaxReturn($data);
            }
            if ($status == 2) {
                $data['status']=3;
        	    $data['info']='您的账号已被禁用';
                $this->ajaxReturn($data);
            }
    		if ($status == 3) {
                $data['status']=4;
        	    $data['info']='请先完善手机号信息';
                $this->ajaxReturn($data);
            }
            if ($status == 4) {
                $data['status']=5;
        	    $data['info']='您已提交申请，无需重复提交';
                $this->ajaxReturn($data);
            }
            if (trim(I('post.name')) == '') {
                $data['status']=6;
        	    $data['info']='请输入姓名';
                $this->ajaxReturn($data);
            }
            if (trim(I('post.idcard_pic1')) == '') {
                $data['status']=7;
        	    $data['info']='请上传身份证正面照';
                $this->ajaxReturn($data);
            }
            if (trim(I('post.idcard_pic2')) == '') {
                $data['status']=8;
        	    $data['info']='请上传身份证反面照';
                $this->ajaxReturn($data);
            }
            $phone = trim(I('phone'));
            if($phone != ''){
                $code = trim(I('code'));
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
    			$mcode = M('mobile_code')->where('phone="'.$phone.'" AND type="shiming"')->order('add_time desc')->find();
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
    			$user_data['phone'] = $phone;
            }

            
            $member_id = $user['member_id'];
            $user_data['member_id'] = $member_id;
            $user_data['name'] = trim(I('post.name')); 
            $user_data['idcard'] = trim(I('post.idcard')); 
            $user_data['idcard_pic1'] = trim(I('post.idcard_pic1'));
            $user_data['idcard_pic2'] = trim(I('post.idcard_pic2'));
            

			if ($this->validation_filter_id_card($user_data['idcard']) ===false ) {
			    $data['status'] = -1;
                $data['info'] = '身份证号输入有误！';
                $this->ajaxReturn($data);
			}
			
			//开启身份证号唯一性
			if($this->config['is_idcard_on']==2){
				$chong = M('member')->where('idcard="'.$user_data['idcard'].'"')->count();
				if($chong>0){
					$data['status'] = 2;
					$data['info'] = '该身份证号已使用！';
					$this->ajaxReturn($data);
				}
			}

            $user_data['status'] = 4;
            $r = M('member')->save($user_data);
            
            

            $data['status']=1;
            $data['info']='提交成功，请等待审核';
            $this->ajaxReturn($data);
        }
    }
    /**
     * 获取用户信息
     */
    public function user_info() {
        $info = $this->get_user_info($this->user);
        
        $user['member_id'] = $info['member_id'];
        $user['code_id'] = $info['code_id'];
        $user['pid'] = $info['pid'];
        $user['openid'] = $info['openid'];
        $user['phone'] = $info['phone'];
        $user['name'] = $info['name'];
        $user['nickname'] = $info['nickname'];
        $user['idcard'] = $info['idcard'];
        $user['idcard_pic1_path'] = $info['idcard_pic1'];
        $user['idcard_pic2_path'] = $info['idcard_pic2'];
        $user['idcard_pic1'] = empty($info['idcard_pic1']) ? '' : $info['url'].$info['idcard_pic1'];
        $user['idcard_pic2'] = empty($info['idcard_pic2']) ? '' : $info['url'].$info['idcard_pic2'];
        
        $user['alipay'] = $info['alipay']['alipay'];
        $user['alipay_name'] = $info['alipay']['alipay_name'];
        $user['alipay_phone'] = $info['alipay']['alipay_phone'];
        $user['alipay_pic_path'] = $info['alipay']['alipay_pic'];
        $user['alipay_pic'] = empty($info['alipay']['alipay_pic']) ? '' : $info['url'].$info['alipay']['alipay_pic'];
        $user['alipay_beizhu'] = $info['alipay']['beizhu'];
        
        $user['weixin'] = $info['weixin']['weixin'];
        $user['weixin_name'] = $info['weixin']['weixin_name'];
        $user['weixin_phone'] = $info['weixin']['weixin_phone'];
        $user['weixin_pic_path'] = $info['weixin']['weixin_pic'];
        $user['weixin_pic'] = empty($info['weixin']['weixin_pic']) ? '' : $info['url'].$info['weixin']['weixin_pic'];
        $user['weixin_beizhu'] = $info['weixin']['beizhu'];
        
        $user['bankcard'] = $info['bankcard']['bankcard'];
        $user['bankcard_name'] = $info['bankcard']['bankcard_name'];
        $user['bank'] = $info['bankcard']['bank'];
        $user['bank_address'] = $info['bankcard']['bank_address'];
        $user['bankcard_beizhu'] = $info['bankcard']['beizhu'];
        
        $user['vip_level'] = $info['vip_level'];
        $user['vip_name'] = level_name($info['vip_level']);

		if(strpos($info['head'],'http') !== false){ 
			$user['head'] =  $info['head'];
		}else{
			$user['head'] =  $info['url'].$info['head'];
		}
		
        $user['status'] = $info['status'];
        $user['status_name'] = user_status($info['status']);
        $user['shiming_reason'] = $info['shiming_reason'];
        $user['heo'] = $info['heo'];
        $user['heo_bind'] = $info['heo_bind'];
        $user['hex'] = $info['hex'];
        $user['rmb'] = $info['rmb'];
        $user['rmb_forzen'] = $info['rmb_forzen'];
        $user['bzj'] = $info['bzj'];
        $user['dai_sort'] = $info['dai_sort'];
        $user['is_hy'] = $info['is_hy'];
        $user['hyd'] = $info['hyd'];
        $user['haibao_pic'] = $info['haibao_pic'] == '' ? '' : $this->config['oss_url'].$info['haibao_pic'];
        $user['reg_type'] = $info['reg_type'];
        $user['businessman_id'] = $info['businessman_id'];

        $data['status']=1;
        //$data['url'] = $info['url'];
        $data['user']=$user;
        $this->ajaxReturn($data);
    }
    /**
     * 提交支付宝信息
     */
    public function alipay() {
        if (IS_POST) {
            $member_id = $this->user['member_id'];
            $pwd_trade = md5(md5(I('pwd')));
            if($pwd_trade != $this->user['pwd_trade']){
                $data['status']=2;
                $data['info']='支付密码错误';
                $this->ajaxReturn($data);
            }
			
			if(trim(I('alipay')) == ''){
				$data['status']=3;
                $data['info']='请输入支付宝账号';
                $this->ajaxReturn($data);
			}
			if(trim(I('pic'))==''){
				$data['status']=4;
                $data['info']='请上传收款二维码';
                $this->ajaxReturn($data);
			}
			
            $pos_data['member_id'] = $member_id;
            $pos_data['alipay'] = trim(I('alipay'));
            //$pos_data['alipay_name'] = trim(I('name'));
            $pos_data['alipay_name'] = $this->user['name'];
			$pos_data['alipay_phone'] = $this->user['phone'];
            $pos_data['alipay_pic'] = trim(I('pic'));
            //$pos_data['beizhu'] = trim(I('beizhu'));
            $pos_data['beizhu'] = null;
            
            $ailpay = M('member_alipay')->where('member_id='.$member_id.' AND alipay<>"" AND alipay IS NOT NULL')->find();
            if($ailpay){
                $chong_name = M('member_alipay')->where('member_id<>'.$member_id.' AND alipay="'.$pos_data['alipay'].'"')->count();
                if($chong_name){
                    $data['status']=4;
                    $data['info']='该支付宝账号已被绑定';
                    $this->ajaxReturn($data);
                }
                $pos_data['id'] = $ailpay['id'];
                $res = M('member_alipay')->save($pos_data);
            }else{
                $pos_data['add_time'] = time();
                $res = M('member_alipay')->add($pos_data);
            }
            if($res !== false){
                $data['status']=1;
                $data['info']='操作成功';
                $this->ajaxReturn($data);
            }else{
                $data['status']=3;
                $data['info']='操作失败';
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 提交微信支付信息
     */
    public function weixin() {
        if (IS_POST) {
            $member_id = $this->user['member_id'];
            $pwd_trade = md5(md5(I('pwd')));
            if($pwd_trade != $this->user['pwd_trade']){
                $data['status']=2;
                $data['info']='支付密码错误';
                $this->ajaxReturn($data);
            }
			if(trim(I('weixin')) == ''){
				$data['status']=3;
                $data['info']='请输入微信号';
                $this->ajaxReturn($data);
			}
			if(trim(I('pic'))==''){
				$data['status']=4;
                $data['info']='请上传收款二维码';
                $this->ajaxReturn($data);
			}
			
			
            $pos_data['member_id'] = $member_id;
            $pos_data['weixin'] = trim(I('weixin'));
            //$pos_data['weixin_name'] = trim(I('name'));
            $pos_data['weixin_name'] = $this->user['name'];
			$pos_data['weixin_phone'] = $this->user['phone'];
            $pos_data['weixin_pic'] = trim(I('pic'));
            //$pos_data['beizhu'] = trim(I('beizhu'));
            $pos_data['beizhu'] = null;
            
            $weixin = M('member_weixin')->where('member_id='.$member_id.' AND weixin<>"" AND weixin IS NOT NULL')->find();
            if($weixin){
                $chong_name = M('member_weixin')->where('member_id<>'.$member_id.' AND weixin="'.$pos_data['weixin'].'"')->count();
                if($chong_name){
                    $data['status']=4;
                    $data['info']='该微信号已被绑定';
                    $this->ajaxReturn($data);
                }
                
                $pos_data['id'] = $weixin['id'];
                $res = M('member_weixin')->save($pos_data);
            }else{
                $pos_data['add_time'] = time();
                $res = M('member_weixin')->add($pos_data);
            }
            if($res !== false){
                $data['status']=1;
                $data['info']='操作成功';
                $this->ajaxReturn($data);
            }else{
                $data['status']=3;
                $data['info']='操作失败';
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 提交银行卡信息
     */
    public function bankcard() {
        if (IS_POST) {
            $member_id = $this->user['member_id'];
            $pwd_trade = md5(md5(I('pwd')));
            if($pwd_trade != $this->user['pwd_trade']){
                $data['status']=2;
                $data['info']='支付密码错误';
                $this->ajaxReturn($data);
            }
            $pos_data['member_id'] = $member_id;
            $pos_data['bankcard'] = trim(I('bankcard'));
            //$pos_data['bankcard_name'] = trim(I('name'));
            $pos_data['bankcard_name'] = $this->user['name'];
            $pos_data['bank'] = trim(I('bank'));
            $pos_data['bank_address'] = trim(I('bank_address'));
            //$pos_data['beizhu'] = trim(I('beizhu'));
            $pos_data['beizhu'] = null;
            
            $bank = M('member_bankcard')->where('member_id='.$member_id.' AND bankcard<>"" AND bankcard IS NOT NULL')->find();
            if($bank){
                $chong_name = M('member_bankcard')->where('member_id<>'.$member_id.' AND bankcard="'.$pos_data['bankcard'].'"')->count();
                if($chong_name){
                    $data['status']=4;
                    $data['info']='该银行卡号已被绑定';
                    $this->ajaxReturn($data);
                }
                
                $pos_data['id'] = $bank['id'];
                $res = M('member_bankcard')->save($pos_data);
            }else{
                $pos_data['add_time'] = time();
                $res = M('member_bankcard')->add($pos_data);
            }
            if($res !== false){
                $data['status']=1;
                $data['info']='操作成功';
                $this->ajaxReturn($data);
            }else{
                $data['status']=3;
                $data['info']='操作失败';
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 修改登录密码
     */
    public function uppwd(){
        if(IS_POST){
            $member_id = $this->user['member_id'];
            if(empty($_POST['pwd'])){
                $data['status']=2;
                $data['info']="请输入新密码！";
                $this->ajaxReturn($data);
            }
            if(empty($_POST['code'])){
                $data['status']=2;
                $data['info']="请填写验证码";
                $this->ajaxReturn($data);
            }
			
			$mcode = M('mobile_code')->where('phone="'.$this->user['phone'].'" AND type="password"')->order('add_time desc')->find();
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
			
            if (strlen($_POST['pwd']) < 6 || strlen($_POST['pwd']) > 10 ){
                $data['status'] = 0;
                $data['info'] = '密码长度6~10位！';
                $this->ajaxReturn($data);
            }
            if(preg_match("/^[a-zA-Z0-9_]{1,}$/", $_POST['pwd']) == false){
                $data['status'] = 0;
                $data['info'] = '密码只允许数字字母！';
                $this->ajaxReturn($data);
            }
            $member_newPwd = md5(md5($_POST['pwd']));
            $r = M('member')->where(array('member_id'=>$member_id))->setField('pwd',$member_newPwd);
            if($r===false){
                $data['status']=2;
                $data['info']='服务器繁忙请稍后重试';
                $this->ajaxReturn($data);
            }
            $data['status']=1;
            $data['info']='修改成功';
            $this->ajaxReturn($data);
        }
    }
    /**
     * 修改交易密码
     */
    public function uppwdtrade(){
        if(IS_POST){
            $member_id = $this->user['member_id'];
            if(empty($_POST['pwd'])){
                $data['status']=2;
                $data['info']="请输入新密码！";
                $this->ajaxReturn($data);
            }
            if(empty($_POST['code'])){
                $data['status']=2;
                $data['info']="请填写验证码";
                $this->ajaxReturn($data);
            }
			
			$mcode = M('mobile_code')->where('phone="'.$this->user['phone'].'" AND type="tradeword"')->order('add_time desc')->find();
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
			
			if (strlen($_POST['pwd']) != 6) {
                $data['status'] = 0;
                $data['info'] = '密码长度6位！';
                $this->ajaxReturn($data);
            }
            if (preg_match("/^[0-9]{1,}$/", $_POST['pwd']) == false) {
                $data['status'] = 0;
                $data['info'] = '密码只能是数字！';
                $this->ajaxReturn($data);
            }

            $member_newPwd = md5(md5($_POST['pwd']));
            $r = M('member')->where(array('member_id'=>$member_id))->setField('pwd_trade',$member_newPwd);
            if($r===false){
                $data['status']=2;
                $data['info']='服务器繁忙请稍后重试';
                $this->ajaxReturn($data);
            }
            $data['status']=1;
            $data['info']='修改成功';
            $this->ajaxReturn($data);
        }
    }
    /**
     * 根据邀请码获得用户昵称
     */
    public function get_nickname_new(){
        if(IS_POST){
            
            $id = trim(I('id'));
            $id_type = intval(I('id_type'));
            if($id=='' || $id_type<=0){
                $data['status']=3;
                $data['info']='参数错误';
                $this->ajaxReturn($data);
            }
            if($id_type==1){
                $where = 'code_id='.$id.' OR phone="'.$id.'"';
            }elseif ($id_type==2) {
                $where = 'member_id='.$id.' OR phone="'.$id.'"';
            }
            
            
            $nickname = M('member')->where($where)->getField('nickname');
            
            if($nickname){
                $data['status']=1;
                $data['nickname']=$nickname;
                $this->ajaxReturn($data);
            }else{
                $data['status']=2;
                $data['info']='无';
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 根据邀请码获得用户昵称
     */
    public function get_nickname(){
        if(IS_POST){
            $code_id = trim(I('code_id'));
            if($code_id){
                $nickname = M('member')->where('code_id="'.$code_id.'" OR phone="'.$code_id.'"')->getField('nickname');
            }
            $id = trim(I('id'));
            if($id){
                $nickname = M('member')->where('member_id='.$id.' OR phone="'.$id.'"')->getField('nickname');
            }
            if($nickname){
                $data['status']=1;
                $data['nickname']=$nickname;
                $this->ajaxReturn($data);
            }else{
                $data['status']=2;
                $data['info']='无';
                $this->ajaxReturn($data);
            }
        }
    }
    /**
     * 填写邀请码
     */
    public function set_code_id(){
        if(IS_POST){
            $code_id = trim(I('code_id'));
            if(!$code_id){
                $data['status']=2;
                $data['info']='请输入邀请码';
                $this->ajaxReturn($data);
            }
            $parent = M('member')->where('code_id="'.$code_id.'"')->find();
            if(!$parent){
                $data['status']=3;
                $data['info']='邀请码输入有误';
                $this->ajaxReturn($data);
            }
            if($this->user['code_id']){
                $data['status']=4;
                $data['info']='您已填写邀请码，请勿重复填写';
                $this->ajaxReturn($data);
            }

            $parent_path = explode(",",$parent['dai_path']);
	        $parent_path_count = count($parent_path)-1;
	        if($parent_path_count>91){
                $dai_sort = "Z";
            }else{
                $dai_sort= strtoupper(chr($parent_path_count+65));//输出大写字母
            }
            
			$mem['pid'] = $parent['member_id'];
            $mem['member_id'] = $this->user['member_id'];
            $mem['dai_sort'] = $dai_sort;
            $mem['dai_path'] = $parent['dai_path'].",".$mem['member_id'];
			$mem['code_id'] = $this->yq();
            M('Member')->save($mem);
            
            $data['status']=1;
            $data['info']='操作成功';
            $this->ajaxReturn($data);
            
        }
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
     * 编辑昵称
     */
    public function set_nickname(){
        if(IS_POST){
            $nickname = trim(I('nickname'));
            if(!$nickname){
                $data['status']=2;
                $data['info']='请输入昵称';
                $this->ajaxReturn($data);
            }
            M('member')->where('member_id='.$this->user['member_id'])->setField('nickname',$nickname);

            $data['status']=1;
            $data['info']='操作成功';
            $this->ajaxReturn($data);
            
        }
    }
    /**
     * 更换头像
     */
    public function set_head(){
        if(IS_POST){
            $head = trim(I('head'));
            if(!$head){
                $data['status']=2;
                $data['info']='请上传头像';
                $this->ajaxReturn($data);
            }
            M('member')->where('member_id='.$this->user['member_id'])->setField('head',$head);

            $data['status']=1;
            $data['info']='操作成功';
            $this->ajaxReturn($data);
            
        }
    }
    /**
     * 获取已完善的支付方式
     */
    public function get_paytype_num(){
        if(IS_POST){
            $member_id = $this->user['member_id'];
            $count = $this->get_pay_type_num($member_id);
            $data['status']=1;
            $data['data']=$count;
            $this->ajaxReturn($data);
        }
    }
    
    
}
