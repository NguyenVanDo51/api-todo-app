<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use Exception;
use Carbon\Carbon;
use DateTime;
use App\Todo;
use App\Jobs\RemindJob;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function create(Request $request){
        $this->validate($request, [
            'name'  => 'required|string|max:255',
        ]);

        $params = array(
            'name' => $request->input('name'),
            'uid'  => $request->attributes->get('uid'),
            'color'=> $request->input('color'),
        );
        $response = [];
        $code = 200;
        try {
            $category = Category::create($params);
            $response['category'] = $category;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
            $code = 500;
        }
        $response['status'] = $code;
        return response()->json($response, $code);
    }

    public function getOne(Request $request) {
        $this->validate($request, [
            'id' => 'required|string'
        ]);
        $response = [];
        $code = 200;
        try {
            $category = Category::find($request->input('id'));
            if ($category) {
                $response['data'] = $category;
            } else {
                $response['error'] = 'Không tìm thấy dữ liệu';
                $code = 404;
            }
        } catch(Exception $e) {
            $response['error'] = 'Đã xảy ra lỗi';
            $code = 500;
        }
        $response['status'] = $code;
        return response()->json($response, $code);
    }

    public function getList(Request $request){
        $uid = $request->attributes->get('uid');
        $get_amount = $request->input('get_amount');
        $response = array(
            'status' => 200,
        );
        $code = 200;
        // chỉ lấy tổng số công việc chưa hoàn thành
        $response['amount_all'] = Todo::where('uid', $uid)->where('is_complete', 0)->count();
        if ($get_amount === 1) {
            return response()->json($response, 200);
        } else {
            $categories = Category::where('uid', $uid)->orderBy('created_at')->get();
            foreach($categories as $index => $category) {
                $categories[$index]['amount_todo'] = $category->todos()->where('is_complete', 0)->count();
            }
            $categories = $categories->toArray();
            array_unshift($categories, array(
                'name' => 'Công việc quan trọng',
                'amount_todo' => Todo::where('uid', $uid)->where('is_important', 1)->count(),
            ));
            array_unshift($categories, array(
                'name' => 'Đã hoàn thành',
                'amount_todo' => Todo::where('uid', $uid)->where('is_complete', 1)->count(),
            ));
            array_unshift($categories, array(
                'name' => 'Tất cả công việc',
                'amount_todo' => Todo::where('uid', $uid)->where('is_complete', 0)->count(),
            ));
            $response['categories'] = $categories;
        }

        $response['status'] = $code;
        return response()->json($response, $code);
    }

    public function delete(Request $request){
        $this->validate($request, [
            '_id' => 'required|string',
        ]);

        $id = $request->input('_id');

        $response = [];
        $code = 200;
        $category = Category::find($id);
        if ($category) {
            $resultCategory = $category->delete($id);
            $resultTodo = Todo::where('category_id', $id)->delete();
            if ($resultCategory && $resultTodo) {
                $response['msg'] = 'Xóa thành công';
            } else if (!$resultCategory) {
                $response['msg'] = 'Xóa danh sách thành công.';
            } else if (!$resultTodo) {
                $response['msg'] += ' Xóa các công việc trong ds thành công.';
            } else {
                $response['msg'] = 'Quá trình xóa xảy ra lỗi.';
                $code = 500;
            }
        } else {
            $response['error'] = 'Không tìm thấy';
            $code = 404;
        }
        $response['status'] = $code;
        return response()->json($response, $code);
    }

    public function edit(Request $request) {
        $this->validate($request, [
            '_id' => 'required|string',
            'name' => 'required|string',
        ]);
        $response = [];
        $code = 200;
        $id = $request->input('_id');
        $name = $request->input('name');
        $color = $request->input('color');

        $query = array(
            'name' => $name,
            'color' => $color,
        );

        $category = Category::find($id);

        if ($category) {
            $result = Category::where('_id', $id)->update($query);
            if ($result) {
                $response['msg'] = 'Đã cập nhật';
            } else {
                $response['msg'] = 'Cập nhật thất bại';
                $code = 400;
            }
        } else {
            $response['error'] = 'Không tìm thấy';
            $code = 404;
        }
        $response['status'] = $code;
        return response()->json($response, $code);
    }

}
