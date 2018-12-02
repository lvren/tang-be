<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Http\Controllers\PayConfig;
use App\Model\Order;
use App\Model\Product;
use App\Model\User;
use Cache;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;

require_once dirname(__FILE__) . "/../Lib/WxPay/WxPay.Api.php";

class MppAuthController extends Controller
{
    // 微信小程序登录，通过code换取openID
    public function mAppCode2Session(Request $request)
    {
        $code = $request->input('code');
        $client = new Client([
            'base_uri' => 'https://api.weixin.qq.com',
        ]);
        $response = $client->request(
            'GET',
            '/sns/jscode2session',
            [
                'query' => [
                    'appid' => env('MAPP_ID'),
                    'secret' => env('MAPP_SECRET'),
                    'js_code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );

        $resJson = json_decode((string) $response->getBody(), true);
        Log::info('小程序登录', $resJson);
        if (isset($resJson['errcode']) && $resJson['errcode'] !== 0) {
            throw new Exception('错误码' . $resJson['errcode'] . ';错误信息' . $resJson['errmsg']);
        }

        $sessionKey = Str::orderedUuid();
        Cache::forever($sessionKey, json_encode($resJson));

        $user = User::where('uuid', $resJson['openid'])->first();
        $hasLogin = true;
        if (!$user) {
            $user = new User();
            $user->uuid = $resJson['openid'];
            $user->unionid = isset($resJson['unionid']) ? $resJson['unionid'] : null;
            $user->save();

            $hasLogin = false;
        }

        return [
            'status' => true,
            'message' => 'success',
            'data' => ['sessionKey' => $sessionKey, 'hasLogin' => $hasLogin],
        ];
    }

    public function mAppSaveUserInfo(Request $request)
    {
        $userInfo = $request->input('userInfo');
        $sessionKey = $request->input('sessionKey');
        $encryptedData = $request->input('encryptedData');
        $iv = $request->input('iv');
        $signature = $request->input('signature');

        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            return ['status' => false, 'message' => '用户登录信息失效'];
        }
        $sessionInfo = json_decode($sessionInfo, true);

        $pc = new WXBizDataCrypt(env('MAPP_ID'), $sessionInfo['session_key']);
        $errCode = $pc->decryptData($encryptedData, $iv, $data);

        $data = json_decode($data, true);

        $user = User::where('uuid', $data['openId'])->first();
        if ($user) {
            $user->unionid = isset($data['unionid']) ? $data['unionid'] : null;
            $user->nickName = $data['nickName'];
            $user->avatarUrl = $data['avatarUrl'];
            $user->save();
        } else {
            $user = new User();
            $user->uuid = $data['openId'];
            $user->unionid = isset($data['unionid']) ? $data['unionid'] : null;
            $user->nickName = $data['nickName'];
            $user->avatarUrl = $data['avatarUrl'];
            $user->save();
        }

        return ['stauts' => true, 'data' => $user];
    }

    public function getPayParam(Request $request)
    {
        $sessionKey = $request->input('sessionKey');
        $product = $request->input('product');
        $number = $request->input('number', 1);
        // 拿到自己生成的sessionKey，获取登录信息
        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            return ['status' => false, 'message' => '用户登录信息失效'];
        }
        $sessionInfo = json_decode($sessionInfo, true);
        // 用登录信息换取用户信息
        $openId = $sessionInfo['openid'];
        $user = User::where('uuid', $openId)->first();
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

        $order = Order::where('user_id', $userId)
            ->where('product_id', $productMod->id)
            ->first();
        if ($order) {
            if ($order->isPay && $productMod->is_once) {
                throw new Exception('当前商品同一个用户只能购买一次');
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

            $orderStatus = $user->weixin && $user->weixin !== '' ? 1 : 0;
            $order = new Order();
            $order->user_id = $userId;
            $order->product_id = $productId;
            $order->order_id = $orderId;
            $order->pre_param = $jsApiParameters;
            $order->status = $orderStatus;
            $order->save();
        }

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
    private function getJsApiParameters($UnifiedOrderResult)
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
}
