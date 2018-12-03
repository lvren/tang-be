<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Http\Controllers\PayConfig;
use App\Model\Country;
use App\Model\Order;
use App\Model\Product;
use App\Model\Sharer;
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
    // 保存用户信息回调
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
    // 获取支付配置接口
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
            $price = $productMod->price * $number;
            //②、统一下单
            $input = new \WxPayUnifiedOrder();
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
            $order->price = $price;
            $order->save();
        }

        $jsonParam = json_decode($jsApiParameters, true);
        return [
            'status' => true,
            'param' => $jsonParam,
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
    // 获取所有国家列表
    public function getCountryList(Request $request)
    {
        $country = Country::all();
        return ['status' => true, 'data' => $country];
    }
    // 获取所有校友列表
    public function getSharerList(Request $request)
    {
        $sharer = Sharer::with('school')->get();
        return ['status' => true, 'data' => $sharer];
    }
    // 获取校友的详细信息
    public function getSharerInfo(Request $request)
    {
        $sessionKey = $request->input('sessionKey');
        $sharerId = $request->input('sharerId');
        // 拿到自己生成的sessionKey，获取登录信息
        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            throw new Exception('用户登录信息失效');
        }
        if (!$sharerId) {
            throw new Exception('没有接受到校友信息参数ID');
        }
        $sharer = Sharer::with('school')->where('id', $sharerId)->first();
        if (!$sharer) {
            throw new Exception('没有接受到校友' . $sharerId . '的信息');
        }
        return ['status' => true, 'data' => $sharer];
    }
    // 获取用户的所有订单
    public function getUserProductList(Request $request)
    {
        $sessionKey = $request->input('sessionKey');
        // 拿到自己生成的sessionKey，获取登录信息
        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            throw new Exception('用户登录信息失效');
        }
        $sessionInfo = json_decode($sessionInfo, true);
        // 用登录信息换取用户信息
        $openId = $sessionInfo['openid'];
        $user = User::where('uuid', $openId)->first();
        if (!$user) {
            throw new Exception('获取用户信息失败');
        }

        $orderList = Order::with(['product', 'user'])->select(['order_id', 'is_pay', 'user_id', 'product_id', 'price', 'created_at'])->where('user_id', $user->id)->get();
        return ['status' => true, 'data' => $orderList];
    }

    public function getUserInfo(Request $request)
    {
        $sessionKey = $request->input('sessionKey');
        // 拿到自己生成的sessionKey，获取登录信息
        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            throw new Exception('用户登录信息失效');
        }
        $sessionInfo = json_decode($sessionInfo, true);
        // 用登录信息换取用户信息
        $openId = $sessionInfo['openid'];
        $user = User::where('uuid', $openId)->first();
        if (!$user) {
            throw new Exception('获取用户信息失败');
        }

        return ['status' => true, 'data' => $user];
    }

    // 完善用户信息
    public function saveUserInfo(Request $request)
    {
        $sessionKey = $request->input('sessionKey');
        // 拿到自己生成的sessionKey，获取登录信息
        $sessionInfo = Cache::get($sessionKey);
        if (!$sessionInfo) {
            throw new Exception('用户登录信息失效');
        }
        $sessionInfo = json_decode($sessionInfo, true);
        // 用登录信息换取用户信息
        $openId = $sessionInfo['openid'];
        $user = User::where('uuid', $openId)->first();
        if (!$user) {
            throw new Exception('获取用户信息失败');
        }

        $mobile = $request->input('mobile');
        $code = $request->input('code');
        $weixin = $request->input('weixin');

        if (!$mobile) {
            throw new Exception('没有指定手机号码');
        }
        if (!$code) {
            throw new Exception('没有填写验证码');
        }
        if (!$weixin) {
            throw new Exception('没有填写验证码');
        }

        SmsController::validateSmsCode($code, $mobile);

        $user->mobile = $mobile;
        $user->weixin = $weixin;
        $user->save();

        return ['status' => true, 'message' => '验证成功'];
    }
}
