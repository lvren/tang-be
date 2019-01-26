<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Http\Components\PayConfig;
use App\Http\Lib\WxPay\WxPayApi;
use App\Http\Lib\WxPay\WxPayData\WxPayJsApiPay;
use App\Http\Lib\WxPay\WxPayData\WxPayUnifiedOrder;
use App\Http\Lib\WxPay\WxPayException;
use App\Model\Order;
use App\Model\Product;
use App\Model\User;
use Cache;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;

class MppOrderController extends Controller
{
    // 获取支付配置接口
    public function getPayParam(Request $request)
    {
        $sessionKey = $request->input('sessionKey');
        $product = $request->input('product');
        $number = $request->input('number', 1);
        $orderId = $request->input('order');
        // 拿到自己生成的sessionKey，获取登录信息
        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            return ['status' => false, 'message' => '用户登录信息失效'];
        }
        $sessionInfo = json_decode($sessionInfo, true);
        // 用登录信息换取用户信息
        $unionid = $sessionInfo['unionid'];
        $openid = $sessionInfo['openid'];
        $user = User::where('unionid', $unionid)->first();
        if (!$user) {
            return ['status' => false, 'message' => '获取用户信息失败'];
        }
        // 获取产品信息
        if (!$product) {
            throw new Exception('没有指定购买的产品');
        }
        $productMod = Product::where('id', $product)->orWhere('name', $product)->first();
        if (!$productMod) {
            throw new Exception('没有指定的产品');
        }
        $productId = $productMod->id;
        $userId = $user->id;
        $orderStatus = $user->weixin && $user->weixin !== '' ? 1 : 0;

        $order;
        if ($orderId) {
            $order = Order::where('order_id', $orderId)->first();
        } else {
            $order = Order::where('user_id', $userId)
                ->where('is_pay', 0)
                ->where('product_id', $productMod->id)
                ->first();
        }
        if ($order) {
            if ($order->is_pay && $productMod->is_once) {
                throw new Exception('当前商品同一个用户只能购买一次,可以在“我的->我的服务”中查看已购买的服务');
            }
            $jsApiParameters = $order->pre_param;
        } else {
            $orderId = md5(Str::orderedUuid());
            $price = $productMod->price * $number;
            //②、统一下单
            $input = new WxPayUnifiedOrder();
            $input->SetBody("校友说分享");
            $input->SetAttach($productMod->title);
            $input->SetDetail($productMod->desc);
            $input->SetOut_trade_no($orderId);
            $input->SetTotal_fee($price);
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            // $input->SetGoods_tag("测试商品");
            $input->SetNotify_url("http://talktoalumni.com/api/orderNotify");
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openid);

            $config = new PayConfig();
            $unifiedOrder = WxPayApi::unifiedOrder($config, $input);
            $jsApiParameters = $this->getJsApiParameters($unifiedOrder);

            $order = new Order();
            $order->user_id = $userId;
            $order->product_id = $productId;
            $order->order_id = $orderId;
            $order->pre_param = $jsApiParameters;
            $order->price = $price;
            $order->number = $number;
            $order->save();
        }

        $jsonParam = json_decode($jsApiParameters, true);
        return [
            'status' => true,
            'payargs' => $jsonParam,
            'order' => ['id' => $order->id, 'orderId' => $order->order_id],
            'user' => ['infoStatus' => $orderStatus],
        ];
    }
    /**
     * 获取jsapi支付的参数
     * @param array $UnifiedOrderResult 统一支付接口返回的数据
     * @throws WxPayException
     *
     * @return json数据，可直接填入js函数作为参数
     */
    private function getJsApiParameters($UnifiedOrderResult)
    {
        if (!array_key_exists("appid", $UnifiedOrderResult)
            || !array_key_exists("prepay_id", $UnifiedOrderResult)
            || $UnifiedOrderResult['prepay_id'] == "") {
            throw new WxPayException("参数错误");
        }

        $jsapi = new WxPayJsApiPay();
        $jsapi->SetAppid($UnifiedOrderResult["appid"]);
        $timeStamp = time();
        $jsapi->SetTimeStamp("$timeStamp");
        $jsapi->SetNonceStr(WxPayApi::getNonceStr());
        $jsapi->SetPackage("prepay_id=" . $UnifiedOrderResult['prepay_id']);

        $config = new PayConfig();
        $jsapi->SetPaySign($jsapi->MakeSign($config));
        $parameters = json_encode($jsapi->GetValues());
        return $parameters;
    }

    public function orderCallback(Request $request)
    {
        $orderId = $request->input('id');
        $orderStatus = $request->input('status');
        $transactionId = $request->input('transactionId');
        $errorMsg = $request->input('errorMsg');

        $sessionKey = $request->input('sessionKey');
        // 拿到自己生成的sessionKey，获取登录信息
        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            throw new Exception('用户登录信息失效');
        }
        // 用登录信息换取用户信息
        $sessionInfo = json_decode($sessionInfo, true);
        $unionid = $sessionInfo['unionid'];
        $user = User::where('unionid', $unionid)->first();
        if (!$user) {
            return ['status' => false, 'message' => '获取用户信息失败'];
        }

        $order = Order::where('user_id', $user->id)
            ->where('id', $orderId)
            ->first();
        if (!$order) {
            throw new Exception('无效的订单信息');
        }

        if ($orderStatus === 'success') {
            $order->is_pay = 1;
            $order->transaction_id = $transactionId;
            $order->save();

            $this->sendCustomMsg($order);
        } else {
            $order->error_msg = $errorMsg;
            $order->save();
            $order->delete();
        }

        // 购买后增加校友人气值
        $sharer = Sharer::where('id', $order->sharer_id)->first();
        $sharer->pv = $sharer->pv + 50;
        $sharer->save();

        return ['status' => true, 'message' => '保存order信息成功'];
    }

    private function sendCustomMsg($order)
    {
        $preParam = json_decode($order->pre_param);
        $package = $preParam->package;
        list($key, $prepayId) = explode("=", $package);
        $user = $order->user;
        $product = $order->product;
        $sharer = $product->sharer;

        $client = new Client([
            'base_uri' => 'https://api.weixin.qq.com',
        ]);
        Log::info($prepayId);
        $clientResp = $client->request(
            'GET',
            '/cgi-bin/token',
            [
                'query' => [
                    'appid' => env('WEIXIN_ID'),
                    'secret' => env('WEIXIN_SECRET'),
                    'grant_type' => 'client_credential',
                ],
            ]
        );

        $clientAccess = json_decode((string) $clientResp->getBody());
        $accessToken = $clientAccess->access_token;

        $toUsers = ['oTsm11KHmtYb4TtGHmaNFwmzrDsg', 'oTsm11Ae53daAoNacUst7_UZBVrY', 'oTsm11JaFiCfLw_UDyZtZNCXDoMc'];
        foreach ($toUsers as $toUser) {
            $msgRes = $client->request(
                'POST',
                '/cgi-bin/message/template/send?access_token=' . $accessToken,
                [
                    'json' => [
                        'access_token' => $accessToken,
                        'touser' => $toUser,
                        'template_id' => 'u6ui2mIn47DxRS10lRuN6oMkvYfcx2pPNVn3yJpDo6Q',
                        // 'form_id' => $prepayId,
                        'miniprogram' => [
                            'appid' => env('MAPP_ID'),
                            'pagepath' => 'pages/order-info/index?orderId=' . $order->id,
                        ],
                        "data" => [
                            "first" => [
                                'value' => '用户' . $user->nickname . '(' . $user->weixin . ')' . '于' . $order->created_at->format('Y-m-d H:i') . '下单成功',
                            ],
                            "keyword1" => [
                                "value" => '留学问题',
                            ],
                            "keyword2" => [
                                "value" => $sharer->name,
                            ],
                            "keyword3" => [
                                "value" => $order->number . '小时',
                            ],
                            "keyword4" => [
                                "value" => '无',
                            ],
                            "remark" => [
                                "value" => '点击可跳转小程序查看详细信息',
                            ],
                        ],
                    ],
                ]
            );
            Log::info((string) $msgRes->getBody());
        }
        // return (string) $msgRes->getBody();
    }
}
