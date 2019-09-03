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
if (!function_exists('apilang')) {
    /**
     * @annotate 获取语言包
     * @author 江枫
     * @email 635449961@qq.com
     * @url:www.cloudcmf.com
     * @time 2019-5-13
     */
    function apilang($name, $lang = '')
    {   //获取语言配置
        $config = config();
        if (!isset($config["lang" . $lang]) || empty($config["lang" . $lang])) {
            //获取默认语言配置
            $data = $config["lang"];
        } else {
            $data = $config["lang" . $lang];
        }
        if (isset($data[$name])) {
            return $data[$name];
        } else {
            return $name;
        }
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

/**
 * 正则表达式验证email格式
 *
 * @param string $str    所要验证的邮箱地址
 * @return boolean
 */
function isEmail($str) {
    if (!$str) {
        return false;
    }
    return preg_match('#[a-z0-9&\-_.]+@[\w\-_]+([\w\-.]+)?\.[\w\-]+#is', $str) ? true : false;
}

/**
 * @param $model
 * @param $models 判断是否是常用机型 1是 0否
 * @return mixed
 */
function is_model($model,$models){
    if(empty($model) || empty($models)){
        return 0;
    }
    $models = \GuzzleHttp\json_decode($models,true);
    if(in_array($model,$models)){
        return 1;
    }else{
        return 0;
    }
}

if (!function_exists('isMobile')) {
    /**
     * @annotate 判断手机号格式
     * @author 江枫
     * @email 635449961@qq.com
     * @url:www.cloudcmf.com
     * @time 2019-5-13
     */
    function isMobile($mobile)
    {
        if (empty($mobile)) {
            return false;
        }
        if (!preg_match("/^[0-9][0-9]*$/", $mobile)) {
            return false;
        }
        return true;
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

function get_price(){
    $data=cache("cas:getMarkerPrice");
    if(!empty($data)){
        return $data;
    }
    $data = ['usdt',"eos_usdt",'btc_usdt','eth_usdt',"zing_usdt"];
    $markerPrices = ['usdt'=>0,"eos_usdt"=>0,'btc_usdt'=>0,'eth_usdt'=>0,"zing_usdt"=>0];
    foreach ($data as $k=>$val){
        $rsult = \think\Db::name('marker_list')->where(['type'=>$val])->order('id desc')->find();
        if(!empty($rsult)){
            $markerPrices[$val] = $rsult['sell'];
        }
    }
    cache("cas:getMarkerPrice",$markerPrices,60);
    return $markerPrices;
}

/* 处理验证码  mobile手机号 send_info历史发送信息 */
function sendMessage($mobile = '', $num = '', $type = 'base')
{

    $verify_code    = rand(10000, 99999);
    $totleTime      = 300; //验证码过期时间
    $min_time       = 60;
    $key            = "code:" . $type . ":" . $mobile;
    $limit_key      = "code:limit:" . $mobile;
    $send_info      = \redis()->get($key);
    $send_limit     = \redis()->get($limit_key);
    $min_time_data  = cache($type . ":" . $mobile);

    if (!empty($min_time_data))
    {
        return array(
            'message' => lang('message_2'),
            'code' => 0
        );
    }
    if ($send_limit > 30)
    {
        return array(
            'message' => lang('message_3'),
            'code' => 0
        );
    }

    if (!empty($num)) {
        $h = substr($num, 0, 2) . "a";
        $c_num = $num . "a";
        if ($h != '00a' && $h != '10a') {
            if ($c_num == '86a') {
                $num = '1086';
            } else {
                $num = '00' . $num;
            }
        }
        $mobile1 = $num . $mobile;
    } else {
        $mobile1 = $mobile;
    }

    $send_info = \redis()->set($key, $verify_code, $totleTime);
    //help_test_logs(['设置验证码在 redis 里', $send_info, $key, $verify_code]);

    $res = send_sms_ali($mobile1, $verify_code);
    //$res = true;

    if (is_error($res)) {
        return array(
            'message' => $res['message'],
            'code' => 0
        );
    } else {
        cache($type . ":" . $mobile, 1, 60);
        $send_info = \redis()->set($key, $verify_code, $totleTime);
        if (!$send_info) {
            return array(
                'message' => lang('message_5'),
                'code' => 0
            );
        }
        //一天发送记录
        $tomorrow = strtotime(date("Y-m-d", strtotime("+1 day"))) - time();
        \redis()->set($limit_key, $send_limit + 1, $tomorrow);

        return array(
            'message' => lang('message_4'),
            'code' => 1
        );
    }
}

function is_error($data)
{
    if (empty($data) || !is_array($data) || !array_key_exists('errno', $data) || (array_key_exists('errno', $data) && $data['errno'] == 0)) {
        return false;
    } else {
        return true;
    }
}

function send_sms_ali($mobile, $code, $project_id = 1)
{
    $data = webConfig();
    $h = substr($mobile, 0, 2) . "a";
    $c = substr($mobile, 0, 4) . "a";
    if ($h != "00a") {
        if ($c == '1086a') {
            $mobile = substr($mobile, 4, strlen($mobile));
        }
        $res = send_sms_aliyun($mobile, $code, $data);
        return $res;
    } else {
    	$data['signName']="小蚂蚁";
    	$data['SmsTemplateCode']="SMS_171118610";
    	 return send_sms_aliyun($mobile, $code, $data);
       // return send_sms_aliyun_world($mobile, $code, $data);
    }

}

function send_sms_aliyun_world($mobile, $code, $data)
{
    $mobile = substr($mobile, 2, strlen($mobile) - 2);
    $smsID = send("u9bras",
                "LrqhZswZ",
                        $mobile,
                "[GMAX]Your verification code is " . $code . ", valid for 10 minutes, please submit the verification code on the page to complete the verification."
    );
    if (!empty($smsID)) {
        return true;
    } else {
        return array(
            'errno' => '-1',
            'message' => "SMS sending failed",
        );
    }
}

function send_sms_aliyun($mobile, $code, $data)
{
    include EXTEND_PATH . 'aliSms/aliyun-php-sdk-core/Config.php';
    include_once EXTEND_PATH . 'aliSms/Dysmsapi/Request/V20170525/SendSmsRequest.php';
    include_once EXTEND_PATH . 'aliSms/Dysmsapi/Request/V20170525/QuerySendDetailsRequest.php';

    $param = "{\"" . $data['variable'] . "\":\"" . $code . "\",\"product\":\"Dysmsapi\"}";
    //$param = "{\"" . $data['variable'] . "\":\"" . $code . "\"}";
    //此处需要替换成自己的AK信息
    $accessKeyId = $data['appKey']; 
    $accessKeySecret = $data['secretKey'];
    //短信API产品名
    $product = "Dysmsapi";
    //短信API产品域名
    $domain = "dysmsapi.aliyuncs.com";
    //暂时不支持多Region
    $region = "cn-hangzhou"; 
    //初始化访问的acsCleint
    $profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
    DefaultProfile::addEndpoint("cn-hangzhou", "cn-hangzhou", $product, $domain);
    $acsClient = new DefaultAcsClient($profile);
    $request = new Dysmsapi\Request\V20170525\SendSmsRequest;
    //必填-短信接收号码
    $request->setPhoneNumbers($mobile);
    //必填-短信签名
    $request->setSignName($data['signName']);
    //必填-短信模板Code
    $request->setTemplateCode($data['SmsTemplateCode']);
    //选填-假如模板中存在变量需要替换则为必填(JSON格式)
    // $request->setTemplateParam("{\"code\":\"12345\",\"product\":\"Dysmsapi\"}");
    $request->setTemplateParam($param);
    //选填-发送短信流水号
    $request->setOutId("1234");
    //发起访问请求
    //var_dump($request);
    $acsResponse = $acsClient->getAcsResponse($request);
    //var_dump($acsResponse);
    return smsBackCode($acsResponse);
}

/*
 * @cpid string Api 帐号
 * @cppwd string Api 密码
 * @to  number  目的地号码，国家代码+手机号码（国家号码、手机号码均不能带开头的0）
 * @content string 短信内容
 *
 * @Return string 消息ID，如果消息ID为空，或者代码抛出异常，则是发送未成功。
*/
function send($cpid, $cppwd, $to, $content)
{
    $c = urlencode($content);
    // http接口，支持 https 访问，如有安全方面需求，可以访问 https开头
    $api = "http://api2.santo.cc/submit?command=MT_REQUEST&cpid={$cpid}&cppwd={$cppwd}&da={$to}&sm={$c}";
    // 建议记录 $resp 到日志文件，$resp里有详细的出错信息
    try {
        $resp = curl_get($api);
    } catch (Exception $e) {
        echo $e->getMessage();
        return "";
    }
    return extract_msgid($resp);
}


function smsBackCode($acsResponse)
{

    $arr = array(
        // "OK" => "请求成功",
        "isv.OUT_OF_SERVICE"            => "业务停机",
        "isv.ACCOUNT_ABNORMAL"          => "账户异常",
        "isv.SMS_TEMPLATE_ILLEGAL"      => "短信模板不合法",
        "isv.SMS_SIGNATURE_ILLEGAL"     => "短信签名不合法",
        "isv.INVALID_PARAMETERS"        => "参数异常",
        "isv.SYSTEM_ERROR"              => "系统错误",
        "isv.MOBILE_NUMBER_ILLEGAL"     => "非法手机号",
        "isv.BUSINESS_LIMIT_CONTROL"    => "业务限流",
        "isv.AMOUNT_NOT_ENOUGH"         => "短信账号余额不足",
        "isv.BLACK_KEY_CONTROL_LIMIT"   => "黑名单管控",
    );
    if (array_key_exists($acsResponse->Code, $arr)) {
        return array(
            'errno' => '-1',
            'message' => $arr[$acsResponse->Code],
        );
    } else {
        return true;
    }
}

function help_p($var = null)
{
    if (empty($var)) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    } else {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }
}// help_p() end

function help_test_logs($var = [])
{
    \think\Db::name('test_logs')->insert([
        'content' => json_encode($var, JSON_UNESCAPED_UNICODE)
    ]);
}// help_test_logs

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

