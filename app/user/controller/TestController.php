<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Powerless < wzxaini9@gmail.com>
// +----------------------------------------------------------------------
namespace app\user\controller;

use cmf\controller\HomeBaseController;
use think\Db;
use api\common\controller\ApiController;
use Eos\Client;
use Eos\Ecc;
use system\Curl;
use think\facade\Env;

class TestController extends HomeBaseController
{

	/**
	 * 前台用户首页(公开)
	 */
	public function index()
	{   $curl   = new Curl();
	    $mobile="15700716190";
		$param  = ['mobile' => $mobile];
		//$url    = 'https://csa.cscchain.net/gyb_api/suntoken/create_account';
		$url    = $_SERVER['SERVER_NAME'];
		
		/*
		 * 假设，创建区块链地址的接口都是一样的。【ck】
		* */
		if($url == 'member.cscchain.org')
		{
			// //创建区块链地址
			$url = '192.168.1.110:20002/api.php/block/suntoken/create_account';
		} else {
			$url = '192.168.1.110:20002/api.php/block/suntoken/create_account';
		}
		
		$cc = $curl->post($url, $param);
		var_dump($cc);
		exit;
		$mobile="15700716190";
		$newAccount      = $this->get_account($mobile);
		
		// 新建账号
		$c_url     = config('site.transfer_url');//这个地址是币地址还是某个公司的网址
		$c_private = trim(rsa_decrypt(Env::get("block.blockchain_hash")));//密文内容
		
		if (empty($mobile))
		{
			$data   = request()->param();
			$mobile = $data['mobile'];
		}
		if (empty($mobile)) {
			return $this->error("信息错误");
		}
		
		$client = new Client($c_url);
		
		$active_private  = Ecc::randomKey();
		echo $active_private;
		$activePublicKey = Ecc::privateToPublic($active_private);
		$owner_private   = Ecc::randomKey();
		$ownerPublicKey  = Ecc::privateToPublic($owner_private);
		
		$data = [
				"address"           => $newAccount,
				"active_private"    => rsa_encrypt($active_private),
				"activePublicKey"   => $activePublicKey,
				"owner_private"     => rsa_encrypt($owner_private),
				"ownerPublicKey"    => $ownerPublicKey
		];
		
		$tx = $client->addPrivateKeys([$c_private])->transaction([
				'actions' => [
						[
								'account'       => 'eosio',
								'name'          => 'newaccount',
								'authorization' => [
										[
												'actor'      => 'eosio',
												'permission' => 'active',
										]
								],
								'data'          => [
										'creator'   => 'eosio',
										// Main net key is name
										'name'      => $newAccount,
										'owner'     => [
												'threshold' => 1,
												'keys'      => [
														['key' => $ownerPublicKey, 'weight' => 1],
												],
												'accounts'  => [],
												'waits'     => [],
										],
										'active'        => [
												'threshold' => 1,
												'keys'      => [
														['key' => $activePublicKey, 'weight' => 1],
												],
												'accounts'  => [],
												'waits'     => [],
										],
								],
						],
						[
								'account'       => 'eosio',
								'name'          => 'buyram',
								'authorization' => [
										[
												'actor'      => 'eosio',
												'permission' => 'active',
										]
								],
								'data'          => [
										'payer'     => 'eosio',
										'receiver'  => $newAccount,
										//'bytes'     => 40000,
										'quant' => '5000.0000 SYS'
								],
						],
						[
								'account'       => 'eosio',
								'name'          => 'delegatebw',
								'authorization' => [
										[
												'actor'      => 'eosio',
												'permission' => 'active',
										]
								],
								'data'          => [
										'from'               => 'eosio',
										'receiver'           => $newAccount,
										'stake_net_quantity' => '1000.0000 SYS',
										'stake_cpu_quantity' => '1000.0000 SYS',
										'transfer'           => 0,
								],
						]
				]
		]);
		
		$data['transaction_id'] = $tx->transaction_id;
		
		if ($data['transaction_id'])
		{
			dbMember('members')
			->where("mobile", $mobile)
			->update($data);
		
			echo "succ";
		}else{
			echo "shiabi";
		}
exit;
		$re=Db::name('members')
		->where("mobile", $mobile)
		->update($data);
		var_dump($re);
		echo Db::name('members')->getLastSql();
		var_dump(strlen($data['active_private']));
		exit;
		 $a="元数据liidjadiad32131222222222222222222222222222222222222";
		 $b=$this->rsa_encrypt($a);
		 echo strlen($b);
		 $c=$this->rsa_decrypt($b);
		 echo $c;
		
	
	}
    /**
     * rsa加密
     */
    public function rsa_encrypt($data)
    {
    	
       $privateKeyFilePath = CMF_ROOT.'/app/common/pem/rsa_private_key.pem'; 
       if(!extension_loaded('openssl')) return false; 

       if(!(file_exists($privateKeyFilePath))){
       	  return 1;
       }
       
       $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyFilePath)); 
       
       if(!$privateKey){
         	return 2;
       }
         $encryptData = ''; 
        ///////////////////////////////用私钥加密//////////////////////// 
        if (openssl_private_encrypt($data, $encryptData, $privateKey)) { 
        // 加密后 可以base64_encode后方便在网址中传输 

         return  base64_encode($encryptData); 

        } else { 

         return 3;

         }
        

    }
     /**
     * rsa解密
     */
    public function rsa_decrypt($data)
    {
    	 
       $publicKeyFilePath = CMF_ROOT.'/app/common/pem/rsa_public_key.pem'; 
       if(!extension_loaded('openssl')) return false; 

       if(!file_exists($publicKeyFilePath)){
       	  return 4;
       }
       $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyFilePath)); 
       
       if(!$publicKey){
         	return 5;
       }
       
         $decryptData = ''; 
        ///////////////////////////////用私钥加密//////////////////////// 
        if (openssl_public_decrypt( base64_decode($data), $decryptData, $publicKey)) { 
        // 加密后 可以base64_encode后方便在网址中传输 

        return $decryptData; 

        } else { 

         return 6;

         }
        
    
    }
    public function get_account($mobile = '')
    {
    	$random_array = [
    			"q",
    			"a",
    			"z",
    			"5",
    			"w",
    			"s",
    			"x",
    			"e",
    			"d",
    			"c",
    			"1",
    			"r",
    			"f",
    			"v",
    			"t",
    			"g",
    			"b",
    			"y",
    			"h",
    			"n",
    			"2",
    			"u",
    			"j",
    			"m",
    			"4",
    			"i",
    			"k",
    			"o",
    			"3",
    			"l",
    			"p"
    	];
    
    	$account    = "";
    	$s_length   = 12;
    	$i          = 0;
    
    	while ($i < $s_length) {
    		$ran      = mt_rand(0, 30);
    		$account .= $random_array[$ran];
    		$i++;
    	}
    
    	return $account;
    }

}
