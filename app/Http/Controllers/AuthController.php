<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Model\User;
use Cache;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function isLogin(Request $request)
    {
        $user = $request->user();
        if ($user) {
            if (!$user->isAdmin) {
                return response('用户权限', 403)->header('Content-Type', 'text/plain');
            }
            return ['status' => true, 'message' => '存在用户登录信息'];
        }
        return response('用户未登录', 401)->header('Content-Type', 'text/plain');
    }

    public function userLogin(Request $request)
    {
        $redirectUrl = $request->input('redirect');
        $appid = env('WEB_ID');
        $redirect = 'http://talktoalumni.com/api/callback';
        $api = 'https://open.weixin.qq.com/connect/qrconnect?';
        $api .= "appid={$appid}&";
        $api .= "redirect_uri={$redirect}&";
        $api .= "response_type=code&";
        $api .= "scope=snsapi_login&state={$redirectUrl}#wechat_redirect";
        return redirect($api);
    }

    public function getUserAccessToken(Request $request)
    {
        if (!$request->has('code')) {
            throw new Exception('参数中缺少code');
        }
        // code 微信返回的用来换取 access_token 的code
        $code = $request->input('code');
        // state 是回调跳转地址
        $state = $request->input('state', 'http://talktoalumni.com');

        $client = new Client([
            'base_uri' => 'https://api.weixin.qq.com',
        ]);
        $accessResp = $client->request(
            'GET',
            '/sns/oauth2/access_token',
            [
                'query' => [
                    'appid' => env('WEB_ID'),
                    'secret' => env('WEB_SERCET'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );
        $constentJson = json_decode((string) $accessResp->getBody(), true);
        Log::info($constentJson);

        if (!isset($constentJson['access_token'])) {
            $errorMessage = '获取用户access_token失败';
            if (isset($constentJson['errcode'])) {
                $errorMessage = '错误码' . $constentJson['errcode'] . ';错误信息' . $constentJson['errmsg'];
            }
            throw new Exception($errorMessage);
        }

        $unionid;
        $userInfo;
        if (!isset($constentJson['unionid'])) {
            $userInfoResp = $client->request(
                'GET',
                '/sns/userinfo',
                [
                    'query' => [
                        'access_token' => $constentJson['access_token'],
                        'openid' => $constentJson['openid'],
                    ],
                ]
            );

            $userInfo = json_decode((string) $userInfoResp->getBody(), true);
            $unionid = isset($userInfo['unionid']) ? $userInfo['unionid'] : null;
        } else {
            $unionid = $constentJson['unionid'];
        }

        $user = User::where('unionid', $constentJson['unionid'])->first();
        if (!$user) {
            $user = new User();
            $user->uuid = $constentJson['openid'];
            $user->unionid = $unionid;
            if (isset($userInfo)) {
                $user->nickname = $userInfo['nickname'];
                $user->avatarUrl = $userInfo['headimgurl'];
            }
            $user->save();
        }

        $constentJson['userId'] = $user->id;
        $cookieUuid = Str::orderedUuid();
        Cache::put($cookieUuid, json_encode($constentJson), (int) $constentJson['expires_in']);
        $cookie = new Cookie('talksession', $cookieUuid, time() + (int) $constentJson['expires_in']);
        return redirect($state)->cookie($cookie);
    }
}
