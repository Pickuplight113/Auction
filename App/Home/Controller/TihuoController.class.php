<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class TihuoController extends CommonController {
    protected $user;
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
        $this->user = $this->get_token_user($token);
    }
    /*
     * 收货地址
     */
    public function address(){
        if(IS_POST){
    		$member_id = $this->user['member_id'];
    		$where='member_id='.$member_id;
    		$is_default = intval(I('is_default'));
    		if($is_default>0){
    		    $where.=' AND is_default='.$is_default;
    		}
            $list =  M('address')->where($where)->order("add_time desc")->select();
            foreach ($list as $k=>$v){
                $list[$k]['add_time_1000'] = $v['add_time']*1000;
            }
		    $data['status'] = 1;
			$data['data'] = $list;
			$this->ajaxReturn($data);
        }
	}
	/*
     * 添加收货地址
     */
    public function address_add(){
		if(IS_POST){
		    $member_id = $this->user['member_id'];
			$address = M('address')->where('member_id='.$member_id)->find();
			if(!$address){
				$_POST['is_default'] = 1;
			}
			if($_POST['is_default'] == 1){
			    M('address')->where('member_id='.$member_id)->setField('is_default',0);
			}
			
			$_POST['member_id'] = $member_id;
			$_POST['add_time'] = time();
			$r = M('address')->add($_POST);
			if($r){
				$data['status'] = 1;
				$data['info'] = '操作成功';
				$this->ajaxReturn($data);
			}else{
				$data['status'] = 2;
				$data['info'] = '操作失败';
				$this->ajaxReturn($data);
			}
		}
	}
	/*
     * 设置默认地址
     */
    public function set_default(){
		if(IS_POST){
			$member_id = $this->user['member_id'];
			$id = intval(I('id'));
			if($id<=0){
				$data['status'] = 0;
				$data['info'] = '参数错误';
				$this->ajaxReturn($data);
			}
			$address = M('address')->where('member_id='.$member_id.' AND id='.$id)->find();
			if(!$address){
				$data['status'] = 0;
				$data['info'] = '地址错误';
				$this->ajaxReturn($data);
			}
			M('address')->where('member_id='.$member_id)->setField('is_default',0);
			M('address')->where('id='.$id)->setField('is_default',1);
			
			$data['status'] = 1;
			$data['info'] = '操作成功';
			$this->ajaxReturn($data);
		}
	}
	/*
     * 删除地址
     */
    public function address_del(){
		if(IS_POST){
			$member_id = $this->user['member_id'];
			$id = intval(I('id'));
			if($id<=0){
				$data['status'] = 0;
				$data['info'] = '参数错误';
				$this->ajaxReturn($data);
			}
			$address = M('address')->where('member_id='.$member_id.' AND id='.$id)->find();
			if(!$address){
				$data['status'] = 0;
				$data['info'] = '地址错误';
				$this->ajaxReturn($data);
			}
			M('address')->delete($id);
			
			if($address['is_default']==1){
				$address_new = M('address')->where('member_id='.$member_id)->find();
				if($address_new){
					M('address')->where('id='.$address_new['id'])->setField('is_default',1);
				}
			}
			$data['status'] = 1;
			$data['info'] = '操作成功';
			$this->ajaxReturn($data);
		}
	}
	/*
     * 编辑收货地址
     */
    public function address_edit(){
        if(IS_POST){
            $member_id = $this->user['member_id'];
    		$id = intval(I('id'));
    		if($id<=0){
    			$data['status'] = 0;
    			$data['info'] = '参数错误';
    			$this->ajaxReturn($data);
    		}
    		$address = M('address')->where('member_id='.$member_id.' AND id='.$id)->find();
    		if(!$address){
    			$data['status'] = 0;
    			$data['info'] = '地址错误';
    			$this->ajaxReturn($data);
    		}
    		if($_POST['is_default'] == 1){
			    M('address')->where('member_id='.$member_id)->setField('is_default',0);
			}
			$r = M('address')->save($_POST);
			if($r!== false){
				$data['status'] = 1;
				$data['info'] = '操作成功';
				$this->ajaxReturn($data);
			}else{
				
				$data['status'] = 4;
				$data['info'] = '操作失败';
				$this->ajaxReturn($data);
			}
		}
	}

	
}