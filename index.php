<?php
header('Access-Control-Allow-Origin:*');
//允许的请求头信息
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization,token,Cache-Control,Postman-token,access-token,platform,TOKEN,PLATFORM,version-terminal");
//允许的请求类型
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS,PATCH');
header("Content-type:text/json; charset=utf-8");

// 应用入口文件
header('X-Frame-Options:SAMEORIGIN');
// 检测PHP环境
if(version_compare(PHP_VERSION,'5.4.0','<'))  die('require PHP > 5.3.0 !');
// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG',true);
define('DB_FIELD_CACHE',false);
define('HTML_CACHE_ON',false);
// 定义应用目录
define('APP_PATH','./App/');
// 引入ThinkPHP入口文件
require './ThinkPHP/ThinkPHP.php';
