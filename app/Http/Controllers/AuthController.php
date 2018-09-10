<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Model\Order;
use App\Model\Product;
use App\Model\User;
use Cache;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function isLogin(Request $request)
    {
        $user = $request->user();
        $product = $request->input('product');
        $productMod = Product::where('id', $product)->orWhere('name', $product)->first();
        if (!$productMod) {
            throw new Exception('没有指定的产品');
        }
        $order = Order::where('user_id', $user->userId)
            ->where('product_id', $productMod->id)
            ->first();
        $orderParam = ['id' => null, 'status' => 0];
        if ($order) {
            $orderParam['id'] = $order->id;
            $orderParam['status'] = $order->status;
            $orderParam['isPay'] = $order->isPay;
        }
        if ($user) {
            return ['status' => true, 'order' => $orderParam, 'message' => '存在用户登录信息'];
        }
        return response('用户未登录', 401)->header('Content-Type', 'text/plain');
    }

    public function userLogin(Request $request)
    {
        $redirectUrl = $request->input('redirect');
        $appid = env('WEIXIN_ID');
        $redirect = 'http://talktoalumni.com/api/callback';
        $api = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
        $api .= "appid={$appid}&";
        $api .= "redirect_uri={$redirect}&";
        $api .= "response_type=code&";
        $api .= "scope=snsapi_base&state={$redirectUrl}#wechat_redirect";

        return redirect($api);
    }

    public function getUserAccessToken(Request $request)
    {
        if (!$request->has('code')) {
            throw new Exception('参数中缺少code');
        }
        $code = $request->input('code');
        $state = $request->input('state');
        $client = new Client([
            'base_uri' => 'https://api.weixin.qq.com',
        ]);
        $response = $client->request(
            'GET',
            '/sns/oauth2/access_token',
            [
                'query' => [
                    'appid' => env('WEIXIN_ID'),
                    'secret' => env('WEIXIN_SECRET'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );

        $body = $response->getBody();
        $constentJson = json_decode((string) $body, true);
        // {
        //     "access_token":"ACCESS_TOKEN",
        //     "expires_in":7200,
        //     "refresh_token":"REFRESH_TOKEN",
        //     "openid":"OPENID",
        //     "scope":"SCOPE"
        // }
        // {"errcode":40029,"errmsg":"invalid code"}
        if (!isset($constentJson['access_token'])) {
            $errorMessage = '获取用户access_token失败';
            if (isset($constentJson['errcode'])) {
                $errorMessage = '错误码' . $constentJson['errcode'] . ';错误信息' . $constentJson['errmsg'];
            }
            throw new Exception($errorMessage);
        }
        $user = User::where('uuid', $constentJson['openid'])->first();
        if (!$user) {
            $user = new User();
            $user->uuid = $constentJson['openid'];
            $user->save();
        }
        $constentJson['userId'] = $user->id;

        $cookieUuid = Str::orderedUuid();
        Cache::put($cookieUuid, json_encode($constentJson), $constentJson['expires_in']);
        $cookie = new Cookie('talksession', $cookieUuid, time() + $constentJson['expires_in']);
        if ($state) {
            return redirect($state)->cookie($cookie);
        }
        return response(['message' => '登录成功', 'status' => true])
            ->cookie($cookie);
    }
}
