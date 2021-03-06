<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Http\Controllers\PayConfig;
use App\Model\Order;
use App\Model\Product;
use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

require_once dirname(__FILE__) . "/../Lib/WxPay/WxPay.Api.php";

/**
 * PayController 订单(需要用户登录部分) / 支付相关操作
 */
class PayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getJsConfig(Request $request)
    {
        $userInfo = $request->user();
        $href = $request->input('href');
        $accessToken = $userInfo->clientAccess;
        $client = new Client([
            'base_uri' => 'https://api.weixin.qq.com',
        ]);
        $response = $client->request(
            'GET',
            '/cgi-bin/ticket/getticket',
            [
                'query' => [
                    'access_token' => $accessToken,
                    'type' => 'jsapi',
                ],
            ]
        );

        $body = $response->getBody();
        $constentJson = json_decode((string) $body);
        if ($constentJson->errcode !== 0) {
            throw new Exception($constentJson->errmsg);
        }
        $jsTickt = $constentJson->ticket;
        $noncestr = $this->getNonceStr();
        $timestamp = $this->getMillisecond();
        $signStr = "jsapi_ticket={$jsTickt}&noncestr={$noncestr}&timestamp={$timestamp}&url={$href}";
        return [
            "status" => true,
            "data" => [
                "signature" => sha1($signStr),
                "nonceStr" => $noncestr,
                "timestamp" => $timestamp,
                "appId" => env('WEIXIN_ID'),
            ],
        ];
    }

    /**
     * 获取毫秒级别的时间戳
     */
    private function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode(" ", microtime());
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode(".", $time);
        $time = $time2[0];
        return $time;
    }

    private function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function payOrder(Request $request)
    {
        $userInfo = $request->user();
        $orderId = $request->input('order');

        $order = Order::where('user_id', $userInfo->userId)
            ->where('order_id', $orderId)
            ->where('is_pay', 0)
            ->first();
        if ($order) {
            $order->is_pay = 1;
            $order->save();
            return ['status' => true, 'message' => '删除无效订单成功'];
        }
        return ['stauts' => false, 'message' => '修改订单状态失败'];
    }

    public function closeOrder(Request $request)
    {
        $userInfo = $request->user();
        $orderId = $request->input('order');

        $order = Order::where('user_id', $userInfo->userId)
            ->where('order_id', $orderId)
            ->where('is_pay', 0)
            ->first();
        if ($order) {
            $order->delete();
        }
        return ['status' => true, 'message' => '删除无效订单成功'];
    }

    public function getPayParam(Request $request)
    {
        $userInfo = $request->user();
        $product = $request->input('product');
        $number = $request->input('number', 1);
        if (!$product) {
            throw new Exception('没有指定购买的产品');
        }
        $productMod = Product::where('id', $product)->orWhere('name', $product)->first();
        if (!$productMod) {
            throw new Exception('没有指定的产品');
        }
        $productId = $productMod->id;

        $openId = $userInfo->openid;
        $userId = $userInfo->userId;

        $userAr = User::where('id', $userId)->first();
        $order = Order::where('user_id', $userId)
            ->where('product_id', $productMod->id)
            ->first();
        if ($order) {
            if ($order->isPay) {
                throw new Exception('同一个用户只能购买一次同种商品');
            }
            $jsApiParameters = $order->pre_param;
        } else {
            $orderId = md5(Str::orderedUuid());
            //②、统一下单
            $input = new \WxPayUnifiedOrder();
            $input->SetBody("校友说分享");
            $input->SetAttach($productMod->title);
            $input->SetDetail($productMod->desc);
            $input->SetOut_trade_no($orderId);
            $input->SetTotal_fee($productMod->price * $number);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            // $input->SetGoods_tag("测试商品");
            $input->SetNotify_url("http://talktoalumni.com/api/orderNotify");
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openId);

            $config = new PayConfig();
            $unifiedOrder = \WxPayApi::unifiedOrder($config, $input);
            $jsApiParameters = $this->getJsApiParameters($unifiedOrder);

            $orderStatus = $userAr->weixin && $userAr->weixin !== '' ? 1 : 0;
            $order = new Order();
            $order->user_id = $userId;
            $order->product_id = $productId;
            $order->order_id = $orderId;
            $order->pre_param = $jsApiParameters;
            $order->status = $orderStatus;
            $order->save();
        }
        ReportController::saveViewReport($userId, 'payOrder');
        $jsonParam = json_decode($jsApiParameters, true);
        return [
            'status' => true,
            'param' => $jsonParam,
            'order' => ['id' => $order->id, 'orderId' => $order->order_id],
        ];
    }

    /**
     *
     * 获取jsapi支付的参数
     * @param array $UnifiedOrderResult 统一支付接口返回的数据
     * @throws WxPayException
     *
     * @return json数据，可直接填入js函数作为参数
     */
    public function getJsApiParameters($UnifiedOrderResult)
    {
        if (!array_key_exists("appid", $UnifiedOrderResult)
            || !array_key_exists("prepay_id", $UnifiedOrderResult)
            || $UnifiedOrderResult['prepay_id'] == "") {
            throw new \WxPayException("参数错误");
        }

        $jsapi = new \WxPayJsApiPay();
        $jsapi->SetAppid($UnifiedOrderResult["appid"]);
        $timeStamp = time();
        $jsapi->SetTimeStamp("$timeStamp");
        $jsapi->SetNonceStr(\WxPayApi::getNonceStr());
        $jsapi->SetPackage("prepay_id=" . $UnifiedOrderResult['prepay_id']);

        $config = new PayConfig();
        $jsapi->SetPaySign($jsapi->MakeSign($config));
        $parameters = json_encode($jsapi->GetValues());
        return $parameters;
    }

    /**
     * saveOrder
     * 保存用户给订单信息
     * @param Request $request
     * @return void
     */
    public function saveOrder(Request $request)
    {
        $mobile = $request->input('mobile');
        $code = $request->input('code');
        $weixin = $request->input('weixin');
        $product = $request->input('product');

        $userInfo = $request->user();
        $userId = $userInfo->userId;
        if (!$mobile) {
            throw new Exception('没有指定手机号码');
        }
        if (!$code) {
            throw new Exception('没有填写验证码');
        }
        if (!$product) {
            throw new Exception('没有商品信息');
        }

        SmsController::validateSmsCode($code, $mobile);
        ReportController::saveViewReport($userId, 'saveInfo');

        $productMod = Product::where('id', $product)->orWhere('name', $product)->first();
        if (!$productMod) {
            throw new Exception('没有指定的产品');
        }
        $productId = $productMod->id;

        $order = Order::where('user_id', $userInfo->userId)
            ->where('product_id', $productId)
            ->where('is_pay', 1)
            ->first();

        if (!$order) {
            throw new Exception('当前用户没有已支付的订单');
        }
        $order->status = 1;
        $order->save();

        $user = User::where('id', $userInfo->userId)->first();
        if (!$user) {
            throw new Exception(['status' => false, 'message' => '用户登录信息错误']);
        }

        $user->mobile = $mobile;
        $user->weixin = $weixin;
        $user->save();

        return ['status' => true, 'message' => '验证成功'];
    }

    /**
     * getUserOrder
     * 获取当前用户的所有订单信息
     * @param Request $request
     * @return void
     */
    public function getUserOrder(Request $request)
    {
        $userInfo = $request->user();
        $userId = $userInfo->userId;

        $orderList = Order::where('user_id', $userInfo->userId)->get();

        return ['status' => true, 'message' => 'success', 'data' => $orderList];
    }
}
