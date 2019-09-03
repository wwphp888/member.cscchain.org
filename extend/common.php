<?php
/**
 * @param $name 链接到用户中心数据库
 * @return \think\db\Query
 */
use think\facade\Env;

function dbMember($name)
{
    $config = config("member.");
    return \think\Db::connect($config)->name($name);
}

function getCacheKey($name)
{
    $prefix = Env::get("project");

    return $prefix . ":" . $name;
}

/**
 * @param $userid 获取用户数据,
 * @param string $field
 * @param int $cache
 * @return array|mixed|null|PDOStatement|string|\think\Model
 */
function getUserInfo($userid, $field = "*", $cache = 60)
{
    $key = getCacheKey("getUserInfo:" . $userid);
    $userinfo = cache($key);
    if ($userinfo && $cache) {
        if ($field != '*') {
            return $userinfo[$field];
        }
        return $userinfo;
    }
    $userinfo = dbMember("members")->where(['id' => $userid])->find();
    cache($key, $userinfo, $cache);
    if ($field != '*') {
        return $userinfo[$field];
    }
    return $userinfo;
}

/**生成订单号
 * @param $str
 * @return string
 */
function makeOrderNo($str)
{
    $orderSn = $str . strtoupper(dechex(date('m'))) . date(
            'd') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf(
            '%02d', rand(0, 99));
    return $orderSn;
}

function curl()
{
    return new \system\Curl();
}

/**获取区域
 * @return array|mixed
 */
function getRegion($id = '')
{
    $region = redis()->get("region");
    if (empty($region)) {
        $region_arr = \think\Db::name("region")->select();
        $region = [];
        foreach ($region_arr as $key => $vo) {
            $region[$vo['id']] = $vo;
        }
        redis()->set("region", $region, 3600);
    }
    if ($id != '') {
        return $region[$id]??'';
    }
    return $region;
}

/**
 * 获取用户
 */
function getFloor($uid = 0)
{
    $getFloor = cache("Wallet:getFloor:" . $uid);
    if ($getFloor) {
        return;
    }
    cache("Wallet:getFloor:" . $uid, 1, 300);
    for ($i = 1; $i <= 8; $i++) {
        redis()->rm("LVL:" . $uid . ":" . $i);
        redis()->rm("LVL:" . $uid . ":" . $i."ids");
    }
    getFloorChild($uid, 0, 1);
    cache("Wallet:getFloor:" . $uid, 1, 300);
}

function getFloorChild($uid = 0, $mid = 0, $floor = 1)
{
    if (empty($uid)) {
        return false;
    }
    if ($floor > 8) {
        return 0;
    }
    $user_count = 0;
    if ($floor == 1) {
        $user_arr = dbMember("members")->where("pid", $uid)->field("id")->select();
    } else {
        $user_arr = dbMember("members")->where("pid", $mid)->field("id")->select();
    }

    if (empty($user_arr)) {
        return 0;
    }
    $user_count = count($user_arr);
    $count = redis()->get("LVL:" . $uid . ":" . $floor);
    $user_arr2 = redis()->get("LVL:" . $uid . ":" . $floor."ids");
    if(is_array($user_arr2)){
        $user_arr1 = array_merge($user_arr2,$user_arr);
    }else{
        $user_arr1 = $user_arr;
    }
    redis() ->set("LVL:".$uid.":".$floor."ids",json_encode($user_arr1));
    //redis() ->set("LVL:".$uid.":".$floor."ids",json_encode($user_arr));
    redis()->set("LVL:" . $uid . ":" . $floor, $count + count($user_arr));
    $floor++;
    foreach ($user_arr as $v) {
        $user_countb = getFloorChild($uid, $v['id'], $floor);
        $user_count += $user_countb;
    }

    return $user_count;
}
