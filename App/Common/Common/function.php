<?php
function get_real_ip(){  
    $realip = '';
    //static $realip;  
    if(isset($_SERVER)){  
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){  
            $realip=$_SERVER['HTTP_X_FORWARDED_FOR'];  
        }else if(isset($_SERVER['HTTP_CLIENT_IP'])){  
            $realip=$_SERVER['HTTP_CLIENT_IP'];  
        }else{  
            $realip=$_SERVER['REMOTE_ADDR'];  
        }  
    }else{  
        if(getenv('HTTP_X_FORWARDED_FOR')){  
            $realip=getenv('HTTP_X_FORWARDED_FOR');  
        }else if(getenv('HTTP_CLIENT_IP')){  
            $realip=getenv('HTTP_CLIENT_IP');  
        }else{  
            $realip=getenv('REMOTE_ADDR');  
        }  
    }
    $ip = explode(',',$realip);
    if(count($ip)>1){
        $realip = $ip[0];
    }
    return $realip;  
}  
/**
 * 手机号码隐藏中间四位
 */
function hide_phone($phone)
{
	return substr_replace($phone, '****', 4, 34);
}
/**
 * 二维数组随机打乱
 */
function shuffle_assoc($list) {  
    if (!is_array($list)) return $list;  
    $keys = array_keys($list);  
    shuffle($keys);  
    //var_dump($keys);exit;
    $random = array();  
    foreach ($keys as $key=>$v) {
        $random[$key] = $list[$v];
    }
    return $random;  
}
/**
 * 缩减字符串
 */
function short_str($str, $length)
{
	$str = strip_tags($str);
	$count = mb_strlen($str,'utf8');
	if($count>$length){
		return mb_substr($str, 0, $length, 'utf-8').'...';
	}else{
		return $str;
	}
}
/**
 * 钱包名称
 * @param unknown $currency_id   钱包id
 * @return unknown
 */
function getCurrencynameByCurrency($currency_id) {
    if (isset($currency_id)) {
        switch ($currency_id) {
			case 3: return "现金";
                break;
			case 4: return "冻结现金";
                break;
			case 5: return "保证金";
                break;
            case 6: return "HEO";
                break;
            case 7: return "绑定-HEO";
                break;
			case 8: return "HEX";
                break;
            case 8: return "USDT";
                break;
            default: return "未知钱包";
                break;
        }
    } else {
        return "未知钱包";
    }
}
/**
 * 添加财务日志方法
 * @param unknown $member_id
 * @param unknown $type  
 * @param unknown $content
 * @param unknown $money
 * @param unknown $money_type  收入=1/支出=2
 * @param unknown $currency_id  币种id 
 * @return 
 */
function addFinance($member_id, $type, $content, $money, $money_type, $currency_id)
{
	if($currency_id == 3) {
		$wallet_type = "rmb";
	}elseif($currency_id == 4) {
		$wallet_type = "rmb_forzen";
	}elseif($currency_id == 5) {
		$wallet_type = "bzj";
	}elseif($currency_id == 6) {
		$wallet_type = "heo";
	}elseif($currency_id == 7) {
		$wallet_type = "heo_bind";
	}elseif($currency_id == 8) {
		$wallet_type = "hex";
	}elseif($currency_id == 9) {
		$wallet_type = "usdt";
	}else{
		
	}
	$money_xian = M('Member')->where('member_id='.$member_id)->getField($wallet_type);
	
	$data['member_id'] = $member_id;
	$data['type'] = $type;
	$data['content'] = $content;
	$data['money_type'] = $money_type;
	$data['money'] = $money;
	$data['money_xian'] = $money_xian;
	$data['add_time'] = time();
	$data['currency_id'] = $currency_id;
	$data['ip'] = get_client_ip();
	$list = M('Finance')->add($data);
	if ($list) {
		return $list;
	} else {
		return false;
	}
}
/**
 * 竞价商品状态
 */
function jingjia_product_status($status,$start_time) {
    if($status==1){
        if($start_time<=time()){
            $name="进行中";
        }else{
            $name="未开始";
        }
    }
    elseif($status==2){
        $name="已售罄";
    }
    elseif($status==3){
        $name="下架";
    }
    return $name;
}

/**
 * 钱包名称
 */
function wallet_name($currency_id) {
    switch ($currency_id) {
        case 3:
			$name = "现金";
            break;
		case 4:
			$name = "冻结现金";
            break;
		case 5:
			$name = "保证金";
            break;
        case 6:
			$name = "HEO";
			break;
		case 7:
			$name = "绑定-HEO";
            break;
		case 8:
			$name = "HEX";
            break;
		case 9:
			$name = "USDT";
            break;
    }
    return $name;
}
/**
 * 提币状态
 */
function tibi_status($status) {
    switch ($status) {
        case 0:
			$name = "提币中";
            break;
        case 1:
			$name = "已提交";
			break;
		case 2:
			$name = "交易取消";
            break;
		case 3:
			$name = "打包中";
            break;	
		case 4:
			$name = "交易失败";
			break;
		case 5:
			$name = "已完成";
            break;
		case 6:
			$name = "网络拥堵，交易失败";
            break;	
    }
    return $name;
}
/**
 * 级别
 */
function level_name($vip_level) {
    switch ($vip_level) {
        case 0:
			$name = "普通用户";
            break;
        case 1:
			$name = "拍客";
			break;
		case 2:
			$name = "区代理";
            break;
		case 3:
			$name = "市代理";
            break;	
		case 4:
			$name = "省代理";
			break;
		case 5:
			$name = "初级合伙人";
			break;
		case 6:
			$name = "高级合伙人";
			break;
    }
    return $name;
}
/**
 * 用户状态
 */
function user_status($status) {
    switch ($status) {
        case 0:
			$name = "未实名";
            break;
        case 1:
			$name = "正常";
			break;
		case 2:
			$name = "禁用";
            break;
		case 3:
			$name = "未完善信息";
            break;	
		case 4:
			$name = "实名审核中";
			break;
    }
    return $name;
}

/**
 * 二维数组根据某个字段排序
 * @param array $array 要排序的数组
 * @param string $keys   要排序的键字段
 * @param string $sort  排序类型  SORT_ASC     SORT_DESC 
 * @return array 排序后的数组
 */
function arraySort($array, $keys, $sort = SORT_DESC) {
	$keysValue = [];
	foreach ($array as $k => $v) {
		$keysValue[$k] = $v[$keys];
	}
	array_multisort($keysValue, $sort, $array);
	return $array;
}
/**
 *  我的团队全部会员
 */
function myallteam($member_id)
{
	$where = 'pid=' . $member_id;
	$data = array();
	$list = array();
	$list = M('Member')->where($where)->field('member_id')->select();
	if ($list) {
		foreach ($list as $k => $v) {
			$data[$k]['member_id'] = $v['member_id'];
			$data[$k]['children'] = myallteam($v['member_id']);
		}
	}
	return $data;
}
/*
 * 获取我的团队人数
 */
function get_my_team($member_id = 0, $status = 0, $flag = 'flag')
{
	$team = mytree($member_id, $status);
	$arr = reduce($team);
	$res = arr_num($arr, $flag);
	return intval($res);
}
/**
 *  我的结构树 （正序）
 */
function mytree($member_id, $status = 0)
{
	$where = 'pid=' . $member_id;
	if ($status > 0) {
		$where .= ' AND status=' . $status;
	}
	$data = array();
	$list = array();
	$list = M('Member')->where($where)->field('member_id,pid,name,phone')->select();
	if ($list) {
		foreach ($list as $k => $v) {
			$data[$k]['member_id'] = $v['member_id'];
			$data[$k]['name'] = $v['name'];
			$data[$k]['phone'] = $v['phone'];
			$data[$k]['flag'] = 'flag';
			$data[$k]['children'] = mytree($v['member_id'], $status);
		}
	}
	return $data;
}
/**
 * 二维数组转一维
 */
function reduce($array) {
	$return = [];
	array_walk_recursive($array, function ($x,$index) use (&$return) {
	  $return[] = $x;
	});
	return $return;
}
/**
 *  一维数组中某个值出现的次数
 */
function arr_num($arr, $flag = 'falg')
{
	$res = array_count_values($arr);
	return $res[$flag];
}
/**
 *  查询我的团队里是否有某个人
 */
function checkmyteam($parent_id, $child_id)
{
	$dai_path = M('Member')->where('member_id='.$child_id)->getField('dai_path');
    if(!empty($dai_path)){
        $arr = explode(',',$dai_path);
        if(!empty($arr)){
            if(in_array($parent_id, $arr)){
                return true;
            }else{
                return false; 
            }
        }else{
            return false; 
        }
    }else{
        return false;
    }
	
	/*
	$where['member_id'] = $child_id;
	$child = array();
	$child = M('Member')->where($where)->field('member_id,pid')->find();
	if ($child['pid']) {
		if ($child['pid'] == $parent_id) {
			return true;
		} else {
			return checkmyteam($parent_id, $child['pid']);
		}
	}
	return false;
	*/
}
/**
 *  查询某个人是我的第几代
 */
function checkmyteam_dai($parent_id, $child_id)
{
	$where['member_id'] = $child_id;
	$child = array();
	$child = M('Member')->where($where)->field('member_id,pid')->find();
	$i++;
	if ($child['pid']) {
		if ($child['pid'] == $parent_id) {
			return $i;
		} else {
			return checkmyteam($parent_id, $child['pid']);
		}
	}
	return false;
}
function get_runtime(){ 
	$ntime=microtime(true); 
	$total=$ntime-$GLOBALS['_beginTime']; 
	return round($total,4); 
}
/**
 * 只保留字符串首尾字符，隐藏中间用*代替（两个字符时只显示第一个）
 * @param string $user_name 姓名
 * @return string 格式化后的姓名
 */
function substr_cut($user_name){
  $strlen   = mb_strlen($user_name, 'utf-8');
  $firstStr   = mb_substr($user_name, 0, 1, 'utf-8');
  $lastStr   = mb_substr($user_name, -1, 1, 'utf-8');
  return $strlen == 2 ? $firstStr . str_repeat('*', mb_strlen($user_name, 'utf-8') - 1) : $firstStr . '***' . $lastStr;
}

/**
*  用户的团队总成交额 当日的
*/
function my_team_yeji($member_id){
    $today = strtotime('today');
    $res['money'] = $res['hyd'] = 0;
    $list=M('Member')->select();
    foreach($list as $k=>$v){
		$money = 0;
		$hyd = 0;
        $you = checkmyteam($member_id,$v['member_id']);
        if($you){
			//$money+= M('pai')->where("status = 1 AND sourse=2 AND member_id = ".$v['member_id'].' AND add_time<'.$today.' AND yuji_time<'.$today)->sum('money');
			$money+= M('pai_order')->where("status = 3 AND buy_uid = ".$v['member_id'].' AND deal_time>'.$today)->sum('yuan_money');
			if($money>0){
				$hyd = 1;
				$res['money']+= $money;
		        $res['hyd']+= $hyd;
			}
        }
		
    }
    return $res;
}

function getMemberLevel($vip_level,$is_tuike) {
    $vip = $vip_level;
    switch ($vip_level) {
        case 0 : $vip_level = "<font style='color:gray'>散客</font>";
            break;
        case 1 : $vip_level = "<font style='color:blue'>拍客</font>";
            break;
        case 2 : $vip_level = "<font style='color:orange'>区代理</font>";
            break;
		case 3 : $vip_level = "<font style='color:orange'>市代理</font>";
            break;
		case 4 : $vip_level = "<font style='color:orange'>省代理</font>";
            break;
		case 5 : $vip_level = "初级合伙人";
            break;
		case 6 : $vip_level = "高级合伙人";
            break;
        default: $vip_level = "未知状态";
    }
    if($vip<2 && $is_tuike==1){
        $vip_level="<font style='color:red'>推客</font>";
    }
    return $vip_level;
}

/**
*  格式化快递公司名称
*/
function kuaidi_com($com){
	if(strpos($com,'圆通') !== false){ 
		$name = 'yuantong';
	}elseif(strpos($com,'韵达') !== false){ 
		$name = 'yunda';
	}elseif(strpos($com,'中通') !== false){ 
		$name = 'zhongtong';
	}elseif(strpos($com,'顺丰') !== false){ 
		$name = 'shunfeng';
	}elseif(strpos($com,'申通') !== false){ 
		$name = 'youzhengguonei';
	}elseif(strpos($com,'百世') !== false){ 
		$name = 'huitongkuaidi';
	}elseif(strpos($com,'EMS') !== false){ 
		$name = 'ems';
	}elseif(strpos($com,'ems') !== false){ 
		$name = 'ems';
	}elseif(strpos($com,'邮政') !== false){ 
		$name = 'youzhengguonei';
	}else{
		$name = '';
	}
	
	return $name;
}
/**
*  格式化快递公司名称
*/
function kuaidi_name($com){
	if($com=='yuantong'){ 
		$name = '圆通速递';
	}elseif($com=='yunda'){ 
		$name = '韵达快递';
	}elseif($com=='zhongtong'){ 
		$name = '中通快递';
	}elseif($com=='shunfeng'){ 
		$name = '顺丰速运';
	}elseif($com=='shentong'){ 
		$name = '申通快递';
	}elseif($com=='youzhengguonei'){ 
		$name = '邮政快递包裹';
	}elseif($com=='huitongkuaidi'){ 
		$name = '百世快递';
	}elseif($com=='ems'){ 
		$name = 'EMS';
	}else{
		$name = '';
	}
	return $name;
}







