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
use Log;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function getAdvanceUser()
    {
        $payUser = Order::select('user_id')->where('product_id', 3)->where('is_pay', 1)->get();
        $userInfo = User::whereIn('id', $payUser)->get();
        $data = [];
        foreach ($userInfo as $key => $value) {
            array_push($data, [
                '微信号' => $value->weixin,
                '手机号' => $value->mobile,
                '创建时间' => $value->updated_at,
            ]);
        }
        return $data;
    }

    public function isLogin(Request $request)
    {
        $user = $request->user();
        if ($user) {
            Log::info(json_encode($user));
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
                $orderParam['isPay'] = $order->is_pay;
            }
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
        $api .= "scope=snsapi_userinfo&state={$redirectUrl}#wechat_redirect";

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
        $accessResp = $client->request(
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
        $constentJson = json_decode((string) $accessResp->getBody(), true);

        if (!isset($constentJson['access_token'])) {
            $errorMessage = '获取用户access_token失败';
            if (isset($constentJson['errcode'])) {
                $errorMessage = '错误码' . $constentJson['errcode'] . ';错误信息' . $constentJson['errmsg'];
            }
            throw new Exception($errorMessage);
        }
        $constentJson['clientAccess'] = isset($clientAccess->access_token) ? $clientAccess->access_token : false;
        // 拉取用户详情信息
        $userInfoResponse = $client->request(
            'GET',
            'sns/userinfo',
            [
                'query' => [
                    'lang' => 'zh_CN',
                    'openid' => $constentJson['openid'],
                    'access_token' => $constentJson['access_token'],
                ],
            ]
        );

        $userInfo = json_decode((string) $userInfoResponse->getBody(), true);
        $user = User::where('uuid', $constentJson['openid'])->first();
        Log::info($userInfo);
        if (!$user) {
            $user = new User();
            $user->uuid = $constentJson['openid'];
            $user->unionid = $userInfo['unionid'];
            $user->nickname = $userInfo['nickname'];
            $user->avatarUrl = $userInfo['headimgurl'];
            $user->save();
        } else {
            $user->unionid = $userInfo['unionid'];
            $user->nickname = $userInfo['nickname'];
            $user->avatarUrl = $userInfo['headimgurl'];
            $user->save();
        }

        $constentJson['userId'] = $user->id;
        $cookieUuid = Str::orderedUuid();
        Cache::put($cookieUuid, json_encode($constentJson), (int) $constentJson['expires_in']);
        $cookie = new Cookie('talksession', $cookieUuid, time() + (int) $constentJson['expires_in']);
        if ($state) {
            return redirect($state)->cookie($cookie);
        }
        return response(['message' => '登录成功', 'status' => true])
            ->cookie($cookie);
    }

    private function getJsConfig($accessToken)
    {
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
        $jsTickt = $constentJson->ticket;
    }
}
