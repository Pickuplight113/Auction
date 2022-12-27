<?php
namespace Home\Controller;
use Common\Controller\CommonController;
use OSS\Core\OssException;
use OSS\OssClient;
class OssController extends CommonController
{
    //获取文件名
    //true 文件名  false后缀
    function retrieve($file, $type = true)
    {
        $arr = explode('.', $file);
        if ($type) {
            return $arr[0];
        } else {
            return $arr[1];
        }

    }


    function Directory($dir)
    {
        if (is_dir($dir) || @mkdir($dir, 0777)) { //查看目录是否已经存在或尝试创建，加一个@抑制符号是因为第一次创建失败，会报一个“父目录不存在”的警告。
            //echo $dir . "创建成功<br>";  //输出创建成功的目录
        } else {

            $dirArr = explode('/', $dir); //当子目录没创建成功时，试图创建父目录，用explode()函数以'/'分隔符切割成一个数组
            array_pop($dirArr); //将数组中的最后一项（即子目录）弹出来，
            $newDir = implode('/', $dirArr); //重新组合成一个文件夹字符串
            $this->Directory($newDir); //试图创建父目录
            @mkdir($dir, 0777);

        }
    }

    public function post($url)
    {
        
        $object = ltrim($url,'/');
        vendor('OSS.autoload');
        $ossConfig          = C('OSS');
        $accessKeyId        = $this->config['access_key_id'];//阿里云OSS  ID
        $accessKeySecret    = $this->config['access_key_secret'];//阿里云OSS 秘钥
        $endpoint           = $this->config['endpoint'];//阿里云OSS 地址
        $ossClient          = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $bucket             = $this->config['bucket'];; //oss中的文件上传空间

        $file       = '.'.$url;//本地文件路径
        
        try {
            $res = $ossClient->uploadFile($bucket, $object, $file);
            //上传成功
            //这里可以删除上传到本地的文件。
            //unlink($file);
        } catch (OssException $e) {
            //上传失败
            printf($e->getMessage() . "\n");
            return;
        }
    }

}