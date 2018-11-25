<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Model\Sharer;
use Illuminate\Http\Request;

/**
 * SharerController 校友（分享人）相关信息处理类
 */
class SharerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getSharerList(Request $request)
    {
        $sharerList = Sharer::get();
        return ['status' => true, 'message' => 'success', 'data' => $sharerList];
    }

    public function getSharerInfo(Request $request)
    {
        $id = $request->input('id');
        if (!$request->has('id')) {
            throw new Exception('没有指定校友信息');
        }
        $share = Sharer::where('id', $id)->first();
        if (!$share) {
            throw new Exception('没有查找到指定的校友信息');
        }
        return ['status' => true, 'message' => 'success', 'data' => $share];
    }
}
