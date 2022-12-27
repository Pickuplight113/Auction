<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use Think\Page;
class NewsController extends CommonController {
    public function _initialize(){
        parent::_initialize();
    }

    /*
     * 公告列表
     */
    public function gonggao(){
        if(IS_POST){
            $page = intval(I('page'));
            $page = $page == 0 ? 1 : $page;
            $num = intval(I('num'));
            $num = $num == 0 ? 10 : $num;

            $where['position_id'] = 3;
            $where['show'] = 1;
    		$count= M('Article')->where($where)->count();
    		$list = M('Article')->where($where)->field('article_id,title,add_time,pic,content')->order('article_id desc')->limit($num)->page($page)->select();
            
            if($list){
                foreach ($list as $k => $v) {
                    $list[$k]['add_time_1000'] = $v['add_time'] * 1000;
                    $list[$k]['add_date'] = date("Y-m-d H:i", $v['add_time']);
                    $list[$k]['pic'] = $v['pic']=='' ? '' : $this->config['oss_url'].$v['pic'];
                    $list[$k]['desc'] = mb_substr(strip_tags($v['content']),0,50,'utf-8'); 
                    $list[$k]['desc'] = str_replace("//s*/", "", $list[$k]['desc']);
                    unset($list[$k]['content']);
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
            }else{
                $data['status'] = 3;
                $data['info'] = '暂无';
                $this->ajaxReturn($data);
            }
        }
    }
 
	/*
     * 新闻类单页
     */
    public function newsshow(){
        if(I('id')){
            $where['article_id'] = I('id');
            $news = M('Article')->where($where)->find();
            if(!$news){
                $data['status'] = 2;
                $data['info'] = '参数错误';
                $this->ajaxReturn($data);
            }
			
			$article['id'] = $news['article_id'];
			$article['title'] = $news['title'];
			$article['pic'] = $news['pic']=='' ? '' : $this->config['oss_url'].$news['pic'];
			$article['content'] = str_replace('<img src="','<img src="'.$this->config['oss_url'],$news['content']); 
			$article['add_time_1000'] = $news['add_time']*1000;
			$article['add_date'] = date("Y-m-d H:i", $news['add_time']);
            $data['status'] = 1;
            $data['data'] = $article;
            $this->ajaxReturn($data);
            
        }else{
            $data['status'] = 2;
            $data['info'] = 'ID参数未知';
            $this->ajaxReturn($data);
        }
    }
}