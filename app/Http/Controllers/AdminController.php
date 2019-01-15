<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Model\Country;
use App\Model\School;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // 创建国家
    public function createCountry(Request $request)
    {
        $school = new Country;
        foreach (['name', 'cname'] as $key) {
            if ($request->has($key)) {
                $school->$key = $request->input($key);
            }
        }
        $school->save();
        return $this->successResponse($school);
    }
    // 修改国家
    public function updateCountry(Request $request, string $id)
    {
        if (!$id) {
            throw new Exception('没有获取到ID信息');
        }

        $school = Country::where('id', $id)->first();
        if (!$school) {
            throw new Exception('不存在需要修改的国家');
        }
        $updateArr = $request->input();
        foreach (['name', 'cname'] as $v) {
            if ($request->has($v)) {
                $school->$v = $request->input($v);
            }
        }
        $school->save();
        return $this->successResponse($school);
    }
    // 创建学校
    public function createSchool(Request $request)
    {
        $school = new School;
        foreach (['name', 'cname', 'country_id', 'desc'] as $key) {
            if ($request->has($key)) {
                $school->$key = $request->input($key);
            }
        }
        $school->save();
        return $this->successResponse($school);
    }
    // 修改学校
    public function updateSchool(Request $request, string $id)
    {
        if (!$id) {
            throw new Exception('没有获取到ID信息');
        }

        $school = School::where('id', $id)->first();
        if (!$school) {
            throw new Exception('不存在需要修改的学校');
        }
        $updateArr = $request->input();
        foreach (['name', 'cname', 'country_id', 'desc'] as $v) {
            if ($request->has($v)) {
                $school->$v = $request->input($v);
            }
        }
        $school->save();
        return $this->successResponse($school);
    }
}
