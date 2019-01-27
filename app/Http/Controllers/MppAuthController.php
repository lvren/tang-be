<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Http\Components\ImComponent;
use App\Http\Components\WXBizDataCrypt;
use App\Model\ImUser;
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
        // 这个接口不一定能拿到 unionid
        // if (!isset($resJson['unionid'])) {
        //     Log::error('小程序登录失败:没有拿到unionid');
        //     throw new Exception('没有请求到小程序的unionid');
        // }
        $sessionKey = Str::orderedUuid();
        Cache::forever($sessionKey, json_encode($resJson));

        // $user = User::with('imUser')->where('unionid', $resJson['unionid'])->first();
        // $hasLogin = true;
        // if (!$user) {
        //     $user = new User();
        //     $user->uuid = $resJson['openid'];
        //     $user->unionid = isset($resJson['unionid']) ? $resJson['unionid'] : null;
        //     $user->save();
        //     $hasLogin = false;
        // }
        // if ($user->imUser) {
        //     $user->imUser->app_id = env('IM_ID');
        // }

        return $this->successResponse([
            'sessionKey' => $sessionKey,
            'hasLogin' => false,
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
        $imUser = $user->imUser;
        if (!$imUser) {
            $ImComponent = new ImComponent();
            $api = $ImComponent->createRestAPI();
            $res = $api->account_import($user->unionid, $user->nickName, $user->avatarUrl);
            if ($res['ActionStatus'] === 'OK') {
                $sig = $api->generateUserSig($user->unionid);
                $imUser = new ImUser();
                $imUser->user_id = $user->id;
                $imUser->account = $user->unionid;
                $imUser->sig = $sig;
                $imUser->save();
            }
        }
        $imUser->app_id = env('IM_ID');
        $userArr = $user->toArray();
        $userArr['imUser'] = $imUser;
        return $this->successResponse($userArr);
    }
}
