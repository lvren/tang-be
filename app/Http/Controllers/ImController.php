<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Http\Components\ImComponent;
use Illuminate\Http\Request;
use Log;

/**
 * ImController IM相关处理类
 */
class ImController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function sendSmsCode(Request $request)
    {
        // 初始化API
        $ImComponent = new ImComponent();
        $api = $ImComponent->createRestAPI();

        $api->account_import();
    }

    public function accountImport(Request $request)
    {
        $user = $request->user();

        $ImComponent = new ImComponent();

        $api = $ImComponent->createRestAPI();
        $api->account_import('oTsm11PW1XFVMr3DvgRClk5P_avw', '测试账号', $user->avatarUrl);
        $res = $api->profile_portrait_get('oTsm11PW1XFVMr3DvgRClk5P_avw');

        if (!$res['ActionStatus'] === 'OK') {
            Log::error('导入用户失败:', $res);
            throw new Exception('导入用户失败');
        }
        return ['status' => true, 'message' => '导出账号成功'];
    }
}
