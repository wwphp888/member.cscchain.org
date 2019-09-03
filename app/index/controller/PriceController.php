<?php

namespace app\index\controller;

use api\common\model\LockedModel;
use Eos\Client;
use Eos\Ecc;
use service\Block;
use think\Controller;
use think\Db;

/**
 * Created by PhpStorm.
 * Date: 2019/7/2
 * Time: 19:08
 */
class PriceController extends Controller
{
    /**
     * 接入0CX 的价格
     */
    public function reptileMarker_ocx()
    {
        $url = 'https://openapi.ocx.app/api/v2/tickers';
        $res = curl_get($url);
        if (empty($res)) {
            die();
        }
        $data = json_decode($res, true);
        $data = $data['data'];
        $market_list = [];
        foreach ($data as $key => $item){
            if ($item['market_code'] == 'zingusdt' || $item['market_code'] == 'btcusdt' || $item['market_code'] == 'eosusdt' || $item['market_code'] == 'ethusdt') {
                $market_list[] = $item;
            }
        }
        $time = time();
        $usdt_price=$this->get_usdt_price();
        if(empty($usdt_price)){
            die();
        }
        if($usdt_price>0){
            foreach ($market_list as $market){
                $price = round($market['last']*$usdt_price,2);
                //$changePercentage = round($market['changePercentage']*100,2)."%";
                $da = [
                    'time'=>$time,
                    'type'=>$market['market_code'],
                    'buy'=>$market['last'],
                    'sell'=>$price,
                    //'last'=>$changePercentage,
                    'high'=>$market['high'],
                    'low'=>$market['low'],
                    'vol'=>round($market['volume'],2),
                ];
                Db::name('marker_list')->insert($da);
            }
        }
        exit;
    }

    /**
     * 接入币虎的价格
     */
    public function reptileMarker(){
        set_time_limit(0);
        $url = 'https://api.bihuex.com/api-web/markets/tickers';
        $res = curl_get($url);
        $data = json_decode($res,true);
        $market_list = array();
        if($data['result'] && $data['message'] == 'success'){
            foreach ($data['rows'] as $k=>$item){
                $item['symbol'] = strtolower($item['symbol']);
                if($item['symbol']=='eth_usdt' || $item['symbol']=='btc_usdt' || $item['symbol']=='eos_usdt'|| $item['symbol'] == 'zing_usdt'){
                    $market_list[] = $item;
                }
            }
        }
        $time = time();
        $usdt_price=$this->get_usdt_price();
        if($usdt_price>0){
            foreach ($market_list as $market){
                $price = round($market['last']*$usdt_price,2);
                $changePercentage = round($market['changePercentage']*100,2)."%";
                $da = [
                    'time'=>$time,
                    'type'=>$market['symbol'],
                    'buy'=>$market['last'],
                    'sell'=>$price,
                    'last'=>$changePercentage,
                    'high'=>$market['high'],
                    'low'=>$market['low'],
                    'vol'=>round($market['volume'],2),
                ];
                Db::name('marker_list')->insert($da);
            }
        }
    }

    /**
     * @return int
     * 接入币虎usdt的价格
     */
    public function get_usdt_price_bh(){
        $url = 'https://bihuex.com/home-web/marketCenter/getSymbolPrice?symbol=USDT';
        $res = curl_get($url);
        $data = json_decode($res,true);
        if($data['result']){
            if($data['resultMap']['data']['priceCNY']>0){
                $da = [
                    'time'=>time(),
                    'type'=>"usdt",
                    'buy'=>0,
                    'sell'=>$data['resultMap']['data']['priceCNY'],
                    //'last'=>$changePercentage,
                    'high'=>0,
                    'low'=>0,
                    'vol'=>0,
                ];
                Db::name('marker_list')->insert($da);
                return $data['resultMap']['data']['priceCNY'];
            }else{
                return -1;
            }
        }else{
            return -1;
        }
    }

    public function get_usdt_price(){
        $url = 'https://www.ocx.app/api/v3/otc/markets';
        $res = curl_get($url);
        $data = json_decode($res,true);
        if($data['data']){
            if($data['data']['markets'][0]['buy_price']>0){
                $da = [
                    'time'=>time(),
                    'type'=>"usdt",
                    'buy'=>0,
                    'sell'=>$data['data']['markets'][0]['buy_price'],
                    //'last'=>$changePercentage,
                    'high'=>0,
                    'low'=>0,
                    'vol'=>0,
                ];
                Db::name('marker_list')->insert($da);
                return $data['data']['markets'][0]['buy_price'];
            }else{
                return -1;
            }
        }else{
            return -1;
        }
    }
}