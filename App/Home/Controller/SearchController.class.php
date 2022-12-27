<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use Think\Page;
class SearchController extends CommonController {
    public function _initialize(){
        parent::_initialize();
    }
    public function test() {
        
        $res = $this->compressedImage('./Public/haibao/952408.jpg','./Public/haibao/952408.jpg');
        var_dump($res);exit;
    }
    /**
     * 竞价区商品
     */
    public function jingjia_classify(){
        $where = 'class_id>0';
        $class_id = intval(I('class_id'));
        if($class_id>0){
			$where.= ' AND class_id='.$class_id;
		}
		$list =  M('jingjia_classify')->where($where)->order('class_id')->select();
		foreach ($list as $k=>$v){
		    $list[$k]['pic'] = $this->config['oss_url'].$v['pic'];
		}
		
        $data['status'] = 1;
        $data['data'] = $list;
        $this->ajaxReturn($data);
    }
    /**
     * 竞价区商品
     */
    public function jingjia_list(){
        $page = intval(I('page'));
        $page = $page == 0 ? 1 : $page;
        $num = intval(I('num'));
        $num = $num == 0 ? 10 : $num;
        
        $today = strtotime('today');
        
        $title = I('title');
		$status = intval(I('status'));
		$class_id = intval(I('class_id'));
		$where='id>0 AND start_time>'.$today;
		if(!empty($title)){
			$where.=' AND (title like "%'.$title.'%")';
        }
		if($status>0){
		    if($status==1){
		        $where.= ' AND status=1 AND start_time>'.time();
		    }
		    elseif($status==2){
		        $where.= ' AND status IN (1,2) AND start_time>'.$today.' AND start_time<='.time();
		    }
		    elseif($status==3){
		        $where.= ' AND status=2';
		    }
		}else{
		    $where.= ' AND status IN (1,2)';
		}
		if($class_id>0){
			$where.= ' AND class_id='.$class_id;
		}

        $count      =  M('jingjia')->where($where)->count();

        $list =  M('jingjia')
            ->where($where)
            ->order("status,start_time,id desc")
            ->limit($num)->page($page)
            ->select();
		foreach($list as $k=>$v){
			$aaa[$k] = $this->get_jingjia_product($v['id']);
			if($aaa[$k]['status']==1){
			    $list[$k] = $aaa[$k]['info'];
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
     * 轮播图、广告图
     */
    public function pic_list(){
        $list['banner1'] = $this->config['oss_url'].$this->config['banner1'];
        $list['banner1_link'] = $this->config['banner1_link'];
        $list['banner2'] = $this->config['oss_url'].$this->config['banner2'];
        $list['banner2_link'] = $this->config['banner2_link'];
        $list['banner3'] = $this->config['oss_url'].$this->config['banner3'];
        $list['banner3_link'] = $this->config['banner3_link'];
        
        $list['jingpai_pic1'] = $this->config['oss_url'].$this->config['jingpai_pic1'];
        $list['jingpai_pic1_link'] = $this->config['jingpai_pic1_link'];
        $list['jingpai_pic2'] = $this->config['oss_url'].$this->config['jingpai_pic2'];
        $list['jingpai_pic2_link'] = $this->config['jingpai_pic2_link'];
        $list['jingpai_pic3'] = $this->config['oss_url'].$this->config['jingpai_pic3'];
        $list['jingpai_pic3_link'] = $this->config['jingpai_pic3_link'];
        
        $list['zichan_pic'] = $this->config['oss_url'].$this->config['zichan_pic'];
        $list['zichan_pic_link'] = $this->config['zichan_pic_link'];
        $list['user_pic'] = $this->config['oss_url'].$this->config['user_pic'];
        $list['user_pic_link'] = $this->config['user_pic_link'];
        
        $data['status'] = 1;
        $data['data'] = $list;
        $this->ajaxReturn($data);
    }
    
    /**
     * 获取服务器时间戳
     */
    public function get_server_time(){
        list($msec, $sec) = explode(' ', microtime());
        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
		$this->ajaxReturn($msectime);
		exit;
    }
}
