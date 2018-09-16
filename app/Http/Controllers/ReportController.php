<?php

namespace App\Http\Controllers;

use App\Model\ViewReport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function reportView(Request $request)
    {
        $userInfo = $request->user();
        $user = $userInfo ? $userInfo->userId : null;
        $operate = $request->input('operate');
        self::saveViewReport($user, $operate);
    }

    public static function saveViewReport($user, $operate)
    {
        $view = new ViewReport();
        $view->operate = $operate;
        $view->user_id = $user;
        $view->save();
    }
}
