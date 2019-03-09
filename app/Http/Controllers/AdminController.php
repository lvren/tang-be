<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorMsgException as Exception;
use App\Model\BannerList;
use App\Model\Country;
use App\Model\Product;
use App\Model\ProductType;
use App\Model\School;
use App\Model\Sharer;
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

    public function uploadBanner(Request $request)
    {
        $image_id = $request->input('imageId');
        $title = $request->input('title');

        $bannerList = new BannerList();
        $bannerList->active = true;
        $bannerList->title = $title;
        $bannerList->image_id = $image_id;

        $bannerList->save();

        return $this->successResponse($bannerList);
    }

    public function updateBanner(Request $request, string $id)
    {
        $active = $request->input('active');
        $bannerList = BannerList::where('id', $id)->first();
        if (!$bannerList) {
            throw new Exception('不存在需要修改的图片');
        }
        $bannerList->active = $active;
        $bannerList->save();

        return $this->successResponse($bannerList);
    }

    public function createSharer(Request $request)
    {
        $avatar = $request->input('avatar');
        $background = $request->input('background');
        $desc = $request->input('desc');
        $intro = $request->input('intro');
        $name = $request->input('name');
        $school_id = $request->input('school_id');

        $sharer = new Sharer();
        $sharer->avatar_id = $avatar;
        $sharer->background_id = $background;
        $sharer->desc = $desc;
        $sharer->intro = $intro;
        $sharer->name = $name;
        $sharer->school_id = $school_id;
        $sharer->save();

        $this->updateSharerProdcut($sharer, $request->input('productType'));
        return $this->successResponse($sharer);
    }

    public function updateSharer(Request $request, string $id)
    {
        $avatar = $request->input('avatar');
        $background = $request->input('background');
        $desc = $request->input('desc');
        $intro = $request->input('intro');
        $name = $request->input('name');
        $school_id = $request->input('school_id');

        $sharer = Sharer::where('id', $id)->first();
        $sharer->avatar_id = $avatar;
        $sharer->background_id = $background;
        $sharer->desc = $desc;
        $sharer->intro = $intro;
        $sharer->name = $name;
        $sharer->school_id = $school_id;
        $sharer->save();

        $this->updateSharerProdcut($sharer, $request->input('productType'));

        return $this->successResponse($sharer);
    }

    private function updateSharerProdcut($sharer, $productType)
    {
        if (isset($productType) && is_array($productType)) {
            $product = Product::where('sharer_id', $sharer->id)->get();
            $oldProductType = [];

            foreach ($product as $p) {
                if (in_array($p->type, $productType)) {
                    array_push($oldProductType, $p->type);
                } else {
                    $s = $p->delete();
                }
            }
            foreach ($productType as $newType) {
                if (!in_array($newType, $oldProductType)) {
                    $product = new Product;
                    $product->price = 12000;
                    $product->name = $newType . $sharer->id;
                    $product->type = $newType;
                    $product->sharer_id = $sharer->id;
                    $product->is_once = 0;

                    $product->save();
                }
            }
        }
    }

    // 创建产品类型
    public function createProductType(Request $request)
    {
        $productType = new ProductType;
        foreach (['name', 'label', 'desc'] as $key) {
            if ($request->has($key)) {
                $productType->$key = $request->input($key);
            }
        }
        $productType->save();
        return $this->successResponse($productType);
    }

    // 更新产品类型
    public function updateProductType(Request $request, string $id)
    {
        if (!$id) {
            throw new Exception('没有获取到ID信息');
        }

        $productType = ProductType::where('id', $id)->first();
        if (!$productType) {
            throw new Exception('不存在需要修改的产品类型');
        }
        $updateArr = $request->input();
        foreach (['name', 'label', 'desc'] as $v) {
            if ($request->has($v)) {
                $productType->$v = $request->input($v);
            }
        }
        $productType->save();
        return $this->successResponse($productType);
    }

    // 获取产品类型列表
    public function getProductTypeList(Request $request)
    {
        $productTypeList = ProductType::get();
        return $this->successResponse($productTypeList);
    }

    // 创建产品类型
    public function createProduct(Request $request)
    {
        $product = new Product;
        foreach (['name', 'label', 'desc'] as $key) {
            if ($request->has($key)) {
                $product->$key = $request->input($key);
            }
        }
        $product->save();
        return $this->successResponse($product);
    }

    // 更新产品类型
    public function updateProduct(Request $request, string $id)
    {
        if (!$id) {
            throw new Exception('没有获取到ID信息');
        }

        $product = Product::where('id', $id)->first();
        if (!$product) {
            throw new Exception('不存在需要修改的产品类型');
        }
        $updateArr = $request->input();
        foreach (['name', 'price'] as $v) {
            if ($request->has($v)) {
                $product->$v = $request->input($v);
            }
        }
        $product->save();
        return $this->successResponse($product);
    }

    // 获取产品类型列表
    public function getProductList(Request $request)
    {
        $productList = Product::with('sharer')->get();
        return $this->successResponse($productList);
    }
}
