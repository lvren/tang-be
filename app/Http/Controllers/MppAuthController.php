<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Http\Components\WXBizDataCrypt;
use App\Model\User;
use Cache;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;

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
        Log::info('小程序登录:', $resJson);
        if (isset($resJson['errcode']) && $resJson['errcode'] !== 0) {
            throw new Exception('错误码' . $resJson['errcode'] . ';错误信息' . $resJson['errmsg']);
        }

        $sessionKey = Str::orderedUuid();
        Cache::forever($sessionKey, json_encode($resJson));

        $user = User::where('unionid', $resJson['unionid'])->first();
        $hasLogin = true;
        if (!$user) {
            $user = new User();
            $user->uuid = $resJson['openid'];
            $user->unionid = isset($resJson['unionid']) ? $resJson['unionid'] : null;
            $user->save();
            $hasLogin = false;
        }

        return $this->successResponse([
            'sessionKey' => $sessionKey,
            'hasLogin' => $hasLogin,
            'userInfo' => $user,
        ]);
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

        Log::info('用户信息回调');
        Log::info($data);
        $data = json_decode($data, true);
        $user = User::where('unionid', $data['unionId'])->first();
        if ($user) {
            $user->uuid = $data['openId'];
            $user->unionid = isset($data['unionId']) ? $data['unionId'] : null;
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
}
