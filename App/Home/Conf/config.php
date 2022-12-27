<?php
return array(
	'DATA_CACHE_TYPE' => 'Redis',//缓存类型
    'REDIS_HOST' => '127.0.0.1',
    'REDIS_PORT' => 5698,//端口号
    'REDIS_TIMEOUT'=>'300',//超时时间
    'REDIS_PERSISTENT'=>false,//是否长连接
    'REDIS_AUTH'=>'huaxia20210917',
    'DATA_CACHE_TIME'=> 10800,      // 数据缓存有效期 0表示永久缓存
    'APP_FEN'=>1,
    
    //支付宝信息
    'ALIPAY_CONFIG'  => array(
        //支付宝网关
        'gatewayUrl'            => 'https://openapi.alipay.com/gateway.do',
        //appid
        'appId'                 => '2021002125677574',
        //应用私钥
        'rsaPrivateKey'         =>  'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCRPYsOshI7aBnzWE2xMuPJ4zVVU+LGEKk94XSGHcydN8sYjWlnfelNszYHw6E+ybszHXVwZ+H3fo2Vmyx52YMxG8nOvtkMb7mQx2rGyXAL0dsUk6M3sZJmBGpvEuwzbr7kAJ1vh4hePP9RhAy/Do5ejLWygOmMAqgeQUU2MheJ0CG/VwmzUQin2BTeWl07LVWzJOf4QGwhWqKQSzEJjwM08p5rkYGqenlfDYheEZf2qHhwdTYAiGi9EyPEyNfS8LZprRONuHOFvIDFw1j1N6quGgIAb9AwidU+pQAYqYtGYu5U4rWeu/YY+aLoZQz5i96rsoqYfy4eHZoc3s4CAvS7AgMBAAECggEAG35g8nk3BlPghbYzjtWpVTBTikGE7iV9RB+HpVPCxggnxBAQ4ext262PDs0zmuUpMLXk41Bm8CjeuHFVbAOG2CcAfsiV4crMf+GgDt1W/oXNSQnhnctZgUJYu4oDeIEAVbzgEJrBb8VwN4gduZUR0kgkpRPOIhjmpkOIzIeS+R1KF2LiU4otT4WG7bVy9qPwjvVnaV0k/zk2uGcD+0z7/1WmfZy7VdPYFLC0h1Fjsly3R1bwVdi4xsmtX5+oE2MWMRUldSFoBuYLMfKp4XaS65aliWZwAhi1umjk6IhxsRA2rFddXHf+DZ5sAt/LwZPamH0ux2EtsD9NhhziF16G8QKBgQDIX5Yhw9XvkVekwjlRdQOBI2fQlPa7sRpJim2MH6NBhdcI6sfVADWrXKgJR3xESD8szZW2rxpePgW2z95WndRhkQadHgL/P4gidvzFLmP/frTd6PEJh4kq8wExkYGF6qhYQ6Mkz0FM2zJ62tnWvFkJ6cFRDsomKaz8US0gToZWNwKBgQC5j7CHELFu+kv5WX2E3ASe+Vmp1yncwVfffVlS0gv2QYA++W/kU8h1g8NHX21bMpd8AQGX5iV5kJ/O59p07brxLMXbtUXV1MA7T4V/WGDDnSRSxIBGIh3DseUcSwnuIh4NnbtXcrd7mrWAGNsZKAjwJ24/bY/Ifi4NUfq5C5YTnQKBgGxkKNbDFWuu9/ZMiq0h2290M6iFrKMDvvChTXlLdAjx2c0dFFSI9H9qdAvw+6wEWQhnfeGA/4aTjovCDshUQJs21JkRrxVczMu4CiywV6/SXwzcAefhxbXSFoc16xcCRWbZz9sNsolc2gXTdZvP72qJZOXwVjImwUkMsJiVYd4pAoGAdv4Hq3Uu8wWatpmGfi5A4FjyAJGzjJaHbQ+Kuitr9ExomvmFoRuBDqqiDSDKlZLJxYE3rlqtaVkuwZW4ZwcTvBiEALIryEWXx/DkoJbh5k7cvh64S0ERaS6oxw3vnj9OmlitjBRKSP8aqLxHs8gSgz4l2pK9g0o26d/KMeOWJ5kCgYAVDXNtHKVSWOCu80ZtmyMxSyEQV1foZmqe0eSfHetn0KTyMWKxDGA+vXHHsP7iliBebTaK2VeREzkCvvTje+BOHEz2OSTlCSNR5CkG7dwxi9iS7vepPdjA6K8/4A+W5CaTxz+u8kOpmDk8mJ1Y1XecILshQhDOskAzMf9l/srGLw==',
        //应用证书路径
        'alipayrsaPublicKey'    =>  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArF96v2xFSr9opQQu0MKz/a3YI6FGpQVCELrznMmeG4ELvETYNM5hoS7Rwnic8qio3b9YdKJbz5Vv08e5zgWoMuE80blMLsM71gJUC5EcBa85sP56JhMRTpnLiLQ5MGqXfM7qrUfGQM200ADacco/xnVpdFk/SrjyQhG84Bygoo9KzL/xN1qFCOgWVY64ysspE8rq41RdYfewRD3Cy3W2Hq+uADkAdNc0LgGqBr1HmlKEi2is35Kd58hFlwshNhy20tYIUizNNFnifOyMju5xOZKnRMqK139Bd9OZ2NBALfxfA0FHDcesnO91yz+6xnpZibKEQKcVlv4Y+Wq4QzKdYQIDAQAB',
        //回调地址 异步
        'notifyUrl'       =>'https://api.huaxiaepai.com/Home/Alipay/alipay_notify',
    ),
    //微信信息
    'WEIXIN_CONFIG'  => array(
        'APPID'    => 'wxc3f58aba7f8be1a9',
        'APPSECRET'=> 'bd3fec8c0a1f0c910726bb6b4526967c',
        'TOKEN'    => 'KNRr873ddJ8D7zIMjDwIYM7m8Jy8M700',
    ),
    //物流信息
    'WULIU_CONFIG'  => array(
        'key'    => 'favUqwtk1631',//客户授权key
        'customer'=> '8D3BAB06334FEB8B08C6929C57F890C5',//查询公司编号
    ),
    
);