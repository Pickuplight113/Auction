<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class ToolsController extends CommonController {
    public function _initialize(){
        parent::_initialize();
        $token = $_SERVER['HTTP_TOKEN'];
        $this->check_token($token);
    }
    /**
     * 上传图片
     */
    public function upload_pic(){
        $type = trim(I('pre'));
        if(empty($type)){
            $data['status'] = 2;
            $data['info'] = 'pre参数缺失';
            $this->ajaxReturn($data);
        }
        if($_FILES["file"]["tmp_name"]){
            $res = $this->upload($_FILES["file"],$type);
            if($res['status']==1){
				
				$filename = ".".$res["path"];
				$this->compressedImage($filename,$filename);
				
				$oss = A('Oss');
                $info = $oss->post($res["path"]);
				
                $res['info'] = '上传成功';
                $res['url'] = $this->config['api_url'];
                $this->ajaxReturn($res);
            }else{
               $this->ajaxReturn($res); 
            }
        }else{
            $data['status'] = 3;
            $data['info'] = '未获取到图片数据';
            $this->ajaxReturn($data);
        }
    }
    /**
     * 设置围观
     */
    public function set_weiguan(){
        $token = $_SERVER['HTTP_TOKEN'];
        $this->user = $this->get_token_user($token);
        $member_id = $this->user['member_id'];
        $id = intval(I('id'));
        $type = intval(I('type'));
        if($id<=0 || $type<=0){
            $data['status'] = 3;
            $data['info'] = '参数缺失';
            $this->ajaxReturn($data);
        }
        $wg_data['member_id'] = $member_id;
        if($type==1){
            $wg_data['jingjia_id'] = $id;
            $where = 'jingjia_id='.$id;
        }elseif ($type==2) {
            $wg_data['pai_id'] = $id;
            $where = 'pai_id='.$id;
        }elseif ($type==3) {
            $wg_data['zhihuan_id'] = $id;
            $where = 'zhihuan_id='.$id;
        }
        $have = M('weiguan')->where('member_id='.$member_id.' AND '.$where)->find();
        if($have){
            $data['status'] = 4;
            $data['info'] = '已围观';
            $this->ajaxReturn($data);
        }
        
        $wg_data['add_time'] = time();
        M('weiguan')->add($wg_data);
        
        $count = M('weiguan')->where($where)->count();
        
        $data['status'] = 1;
        $data['info'] = '围观成功';
        $data['data'] = $count;
        $this->ajaxReturn($data);
    }
    
    /*
     * 推广链接
     */
    public function invite() {
        $token = $_SERVER['HTTP_TOKEN'];
        $user = $this->get_token_user($token);
        
        header("Content-type:text/html;charset=utf-8");
        include './Public/phpQrcode.class.php';
        include './Public/poster.class.php';
        if(!$user['haibao_pic']){
            $qrcode=new \QRcode();
            $qrCodeData = $qrcode->pngData($this->config['download_url']."/register.html?code_id=".$user['code_id'], 13);
            $config = array(
                'bg_url' => $this->config['oss_url'].$this->config['yaoqing_pic'],//背景图片路径
                'text' => array(
                    array(
                        'text' => "邀请人：".$user['name'],//文本内容
                        'left' => 420, //左侧字体开始的位置
                        'top' => 1680, //字体的下边框
                        'fontSize' => 40, //字号
                        'fontColor' => '255,255,255', //字体颜色
                        'angle' => 0,
                    ),
                    array(
                        'text' => "邀请码：".$user['code_id'],
                        'left' => 420,
                        'top' => 1780,
                        'fontSize' => 40, //字号
                        'fontColor' => '255,255,255', //字体颜色
                        'angle' => 0,
                    ),
                    array(
                        'text' => "扫码下载",
                        'left' => 95,
                        'top' => 1820,
                        'fontSize' => 30, //字号
                        'fontColor' => '255,255,255', //字体颜色
                        'angle' => 0,
                    ),
                ),
                'image' => array(
                    array(
                        'name' => '二维码', //图片名称，用于出错时定位
                        'url' => '',
                        'stream' => $qrCodeData,
                        'left' => 20,
                        'top' => 1450,
                        'width' => 300,
                        'height' => 300,
                        'radius' => 0,
                        'opacity' => 100
                    ),
                    
                )
            );
            $poster=new \poster();
    		//设置海报背景图
            $poster->setConfig($config);
            $names="/Public/haibao/".$user['code_id'].".jpg";
            $filename=".".$names;
            //设置保存路径
            $res = $poster->make($filename);
            M('member')->where(array('member_id' => $user['member_id']))->save(array("haibao_pic"=>$names));
            $pic = $this->config['oss_url'].$names;
            $this->compressedImage($filename,$filename);
            
            $oss = A('Oss');
            $info = $oss->post($names);
		}else{
	        $pic = $this->config['oss_url'].$user['haibao_pic'];
		    
		}
		$data['status'] = 1;
		$data['code'] = $user['code_id'];
		$data['name'] = $user['name'];
        $data['pic'] = $pic;
        $this->ajaxReturn($data);
    }
    
    
}
