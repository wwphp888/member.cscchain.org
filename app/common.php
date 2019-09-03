<?php
/**
 * Created by PhpStorm.
 * User: pengjiang
 * Date: 2019/5/13
 * Time: 12:35
 */
use system\Redis;

if (!function_exists('redis')) {
    /**
     * @annotate redis操作
     * @author 江枫
     * @email 635449961@qq.com
     * @url:www.cloudcmf.com
     * @time
     */
    function redis($options = [])
    {
        return Redis::instance($options);
    }
}

if (!function_exists('webConfig')) {
    /**
     * @annotate 网站配置项
     * @author 江枫
     * @email 635449961@qq.com
     * @url:www.cloudcmf.com
     * @time 2019-5-13
     */
    function webConfig()
    {
        $config = \redis()->get("config");
        if ($config) {
            return $config;
        } else {
            $data = \think\Db::name('config')->column("varname,value");
            redis()->set("config", $data);
        }
    }
}
if (!function_exists('dbMember')) {
    /**
     * @annotate  用户中心数据库操作类
     * @author 江枫
     * @email 635449961@qq.com
     * @url:www.cloudcmf.com
     * @time 2019-5-13
     */
    function dbMember($name)
    {
        $config = config("member.");
        return \think\Db::connect($config)->name($name);
    }
}

function curl_get($url, &$httpCode = 0)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    //不做证书校验,部署在linux环境下请改为true
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $file_contents = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $file_contents;
}


/**
 * @param string $url post请求地址
 * @param array $params
 * @return mixed
 */
function curl_post($url, array $params = array())
{
    $data_string = json_encode($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt(
        $ch, CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json'
        )
    );
    $data = curl_exec($ch);
    curl_close($ch);
    return ($data);
}

/**
 * rsa加密
 */
 function rsa_encrypt($data)
{
	 
	$privateKeyFilePath = CMF_ROOT.'/app/common/pem/rsa_private_key.pem';
	if(!extension_loaded('openssl')) return false;

	if(!(file_exists($privateKeyFilePath))){
		return false;
	}
	 
	$privateKey = openssl_pkey_get_private(file_get_contents($privateKeyFilePath));
	 
	if(!$privateKey){
		return false;
	}
	$encryptData = '';
	///////////////////////////////用私钥加密////////////////////////
	if (openssl_private_encrypt($data, $encryptData, $privateKey)) {
		// 加密后 可以base64_encode后方便在网址中传输

		return  base64_encode($encryptData);

	} else {

		return false;

	}


}

/**
 * rsa解密
 */
 function rsa_decrypt($data)
{

	$publicKeyFilePath = CMF_ROOT.'/app/common/pem/rsa_public_key.pem';
	if(!extension_loaded('openssl')) return false;

	if(!file_exists($publicKeyFilePath)){
		return false;
	}
	$publicKey = openssl_pkey_get_public(file_get_contents($publicKeyFilePath));
	 
	if(!$publicKey){
		return false;
	}
	 
	$decryptData = '';
	///////////////////////////////用私钥加密////////////////////////
	if (openssl_public_decrypt( base64_decode($data), $decryptData, $publicKey)) {
		// 加密后 可以base64_encode后方便在网址中传输

		return $decryptData;

	} else {

		return false;

	}


}

function help_p($val = null)
{
    if (empty($val)) {
        echo '<pre>';
        var_dump($val);
        echo '</pre>';
    } else {
        echo '<pre>';
//        echo json_encode($val, JSON_UNESCAPED_UNICODE);
        print_r($val);
        echo '</pre>';
    }
}//help_p() end

function help_test_logs($var = [])
{
    \think\Db::name('test_logs')->insert([
        'content' => json_encode($var, JSON_UNESCAPED_UNICODE)
    ]);
}// help_test_logs