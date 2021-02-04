<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Todo;
use App\Category;
use App\QueueJob;
use App\Jobs\RemindJob;
use Exception;
use Carbon\Carbon;
use DateTime;
use \MongoDB\BSON\UTCDateTime;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Cache;

class TodoController extends Controller
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

    public function add(Request $request){
        $this->validate($request, [
            'title' => 'required|string|max:255',
        ]);
        $uid = $request->attributes->get('uid');

        $response = array();
        $code = 200;

        $title = $request->input('title');
        $is_complete = $request->input('is_complete');
        $is_important = $request->input('is_important');
        $category_id = $request->input('category_id');
        $time_out = $request->input('time_out');

        // date_default_timezone_set('Asia/Ho_Chi_Minh');
        if (!empty($time_out)) {
            $time_out = str_replace('/', '-', $time_out);
            // $time_out = new UTCDateTime($time_out);
            $time_out = Carbon::parse($time_out, 'UTC');
            $response['utcDatetime'] = new UTCDateTime(new DateTime($time_out));
        } else {
            $time_out = null;
        }

        $query = array(
            'uid' => $uid,
            'title' => $title,
            'note' => [],
            'remind' => null,
            'repeat' => isset($todo['repeat']) ? $todo['repeat'] : array(
                'type' => '',
                'option' => [],
                'option_time' => [],
            ),
            'files' => [],
            'is_complete' => isset($is_complete) ? 1 : 0,
            'is_important' => isset($is_important) ? 1 : 0,
            'time_out' => $time_out,
            'queue_id' => null,
        );

        $todo = null;
        if (!empty($category_id)) { // Nếu có đẩy category_id lên
            $category = Category::find($category_id);
            if ($category) {
                $todo = $category->todos()->create($query);
                $c_name = 'todos_' . $uid . '_' .  $category_id;
                $response['remove_memcache'] = Cache::forget($c_name);
            } else {
                $response['erorr'] = 'Không tìm thấy danh sách tác vụ';
                $code = 404;
            }
        } else {
            $query['category_id'] = 'all';
            $response['params'] = $query;
            try {
                $todo = Todo::create($query);
            } catch (Exception $e) {
                $code = 500;
                $response['error'] = $e->getMessage();
            }
        }
        $response['query'] = $query;
        if ($todo) {
            $response['todo'] = $todo;
            $code = 200;
        }
        $response['status'] = $code;

        return response()->json($response, $code);
    }

    public function getList(Request $request){
        $response = array( 'status' => 200 );
        $code = 200;
        $uid = $request->attributes->get('uid');

        $search = $request->input('search');
        $content = $request->input('content');
        $sort_by = $request->input('sort_by');
        $reverse = $request->input('reverse');
        $category_id = $request->input('category_id');

        $c_name = 'todos_' . $uid . '_' . $category_id;
        if ($content === 'added' && empty($search) && empty($sort_by) && empty($reverse)) {
            $todo_cache = Cache::get($c_name);
            if (!empty($todo_cache)) {
                $response['todos'] = $todo_cache;
                $response['resource'] = 'memcache';
                $response['c_name'] = $c_name;
                return response()->json($response, 200);
            }
        }

        $todos = $this->getTodosMain($uid, $search, $content, $sort_by, $reverse, $category_id);

        if (!empty($todos)) {
            $todo_arr = array_map(function ($todo) {
                return array(
                    'id' => $todo['_id'],
                    'uid' => $todo['uid'],
                    'title' => $todo['title'],
                    'note' => isset($todo['note']) ? $todo['note'] : [],
                    'remind' => isset($todo['remind']) ? $todo['remind'] : null,
                    'repeat' => isset($todo['repeat']) ? $todo['repeat'] : array(
                        'type' => '',
                        'option' => [],
                        'option_time' => [],
                    ),
                    'files' => isset($todo['files']) ? $todo['files'] : [],
                    'is_complete' => $todo['is_complete'],
                    'is_important' => $todo['is_important'],
                    'time_out' => isset($todo['time_out']) ? $todo['time_out'] : null,
                    'created_at' => $todo['created_at'],
                    'category_id' => $todo['category_id'],
                    'queue_id' => isset($todo['queue_id']) ? $todo['queue_id'] : null,
                    'queue_before_id' => isset($todo['queue_before_id']) ? $todo['queue_before_id'] : null,
                    'queue_remind_id' => isset($todo['queue_remind_id']) ? $todo['queue_remind_id'] : null,
                );
            }, $todos);

            $response['todos'] = $todo_arr;
            if (empty($search) && empty($sort_by) && empty($reverse)) {
                Cache::put($c_name, $todo_arr, new DateTime('now +1 day'));
            }
        } else {
            $response['todos'] = [];
        }
        $response['status'] = $code;

        return response()->json($response, $code);
    }

    // Lấy danh sách công việc
    private function getTodosMain($uid, $search, $content, $sort_by, $reverse, $category_id){
        $todos = Todo::query()->where('uid', $uid);
        $category = Category::find($category_id);
        if (!empty($search)) {
            $todos = $todos->where('title', 'like', "%$search%");
        }
        if (!empty($content)) { // Nếu gửi content lên thì lấy tùy thuộc vào content
            switch ($content) {
                case 'all':
                    // $todos = $todos->where('category_id', 'all');
                    break;
                case 'important': // Lấy todo là quan trọng
                    $todos = $todos->where('is_important', 1);
                    break;
                case 'completed': // Lấy todo đã hoàn thành
                    $todos = $todos->where('is_complete', 1);
                    break;
                case 'added':
                    $todos = $todos->where('category_id', $category_id);
                    break;
                default:
                    break;
            }
        }
        if (empty($category_id) && $content !== 'important' && $content !== 'completed') { // Nếu ko gửi category_id lên thì sẽ lấy trong tất cả công việc
            // $todos = $todos->where('category_id', 'all'); // Chỉ khi chọn tất cả công việc thì mới lấy ds tất cả công việc (có category_id là all)
        }
        if (!empty($sort_by)) {
            $todos = $todos->orderBy($sort_by, $reverse);
        }else {
            $todos = $todos->orderBy('created_at', 'desc');
        }
       return $todos->get()->toArray();
    }

    public function edit(Request $request){
        $this->validate($request, [
            'id'                => 'required',
            'title'             => 'required|string|max:255',
            'note'              => 'array',
            'note.content'      => 'string',
            'note.created_at'   => 'string',
            'files'             => 'array',
            'files.size'        => 'string',
            'file.url'          => 'string',
            'file.name'         => 'string',
            'file.type'         => 'string',
            'is_complete'       => 'required|boolean',
            'is_important'      => 'required|boolean',
            'time_out'          => 'required_with:repeat.type',
            'repeat'            => 'required|array',
            'repeat.type'       => 'string',
            'repeat.option_time'=> 'array',
            'repeat.option'     => 'array'
        ]);

        $id = $request->input('id');
        $todo = Todo::where('_id', $id)->first();
        if (empty($todo)) {
            return response()->json([
                'error' => 'Không tìm thấy id của thẻ'
            ], 404);
        }

        $response = array();
        $code = 200;

        $uid = $request->attributes->get('uid');
        $title = $request->input('title');
        $note = $request->input('note');
        $files = $request->input('files');
        $remind = $request->input('remind');
        $is_complete = $request->input('is_complete');
        $is_important = $request->input('is_important');
        $time_out = $request->input('time_out');
        $repeat = $request->input('repeat');
        $category_id = $request->input('category_id');

        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $response['time_out'] = new UTCDateTime(new DateTime($time_out));
        $response['remind'] = new UTCDateTime(new DateTime($remind));

        // Xử lý lọc dữ liệu cho files
        if (!empty($files)) {
            $files_allow = array_map(function ($file) {
                return array(
                    'url' => isset($file['url']) ? $file['url'] : '',
                    'name' => isset($file['name']) ? $file['name'] : '',
                    'size' => isset($file['size']) ? $file['size'] : '',
                    'thumb90' => isset($file['thumb90']) ? $file['thumb90'] : '',
                    'type'  => isset($file['type']) ? $file['type'] : '',
                );
            }, $files);
        }
        // Lọc dữ liệu cho note
        $note_allow = [];
        if (!empty($note)) {
            $note_allow = array_map(function ($note) {
                return array(
                    'content' => isset($note['content']) ? $note['content'] : '',
                    'created_at' => isset($note['created_at']) ? $note['created_at'] : '',
                );
            }, $note);
        }

        $time_out_datetime = new DateTime($time_out);
        if (!empty($time_out)) { // Nếu time_out khác rỗng thì set time_out
            $time_out = new UTCDateTime($time_out_datetime);            
            
            $result = $this->dispatch_job_remind($time_out_datetime, $id); // Khi chỉnh sửa lời nhắc, thì chỉnh sửa luôn queue_job
            if (isset($result['queue_id'])) {
                $queue_id = $result['queue_id'];
                $queue_before_id = $result['queue_before_id'];
            } else {
                $response['msg']['queue_id_remind_old'] = $result['msg'];
            }
        } else {
            $time_out =  null;
        }

        $query = array(
            'uid' => $uid,
            'title' => $title,
            'note' => $note_allow,
            'repeat' => $repeat,
            'time_out' => $time_out,
            'files' => isset($files_allow) ? $files_allow : [],
            'is_complete' => $is_complete == true ? 1 : 0,
            'is_important' => $is_important == true ? 1 : 0,
            'queue_id'  => isset($queue_id) ? $queue_id : null,
            'queue_before_id' => isset($queue_before_id) ? $queue_before_id : null,
        );

        // Tạo mới 1 todo nếu có cài lặp lại
        if ($is_complete == 1 && isset($repeat['type']) && !empty($repeat['type']) && !empty($time_out)) { // Khi click hoàn thành 1 todo
            // Tạo 1 todo mới từ todo hiện tại với is_complete là 0 và time_out là lần kế tiếp
            $query_new = $query;
            $query_new['is_complete'] = 0;
            $query_new['time_out'] = new UTCDateTime($this->calculator_time_repeat($repeat, $time_out_datetime));
            $remind_new = $this->calculator_time_repeat($repeat, new DateTime($remind));
            $query_new['remind'] = new UTCDateTime($remind_new);
            $query_new['category_id'] = $category_id;
            if (!empty($category_id)) {
                $category_repeat = Todo::create($query_new);
                if ($category_repeat) {
                    if (!empty($category_repeat['remind'])) {
                        $result = $this->dispatch_job_remind($remind_new, $category_repeat->_id, false, 'remind');
                        if (isset($result['queue_id'])) {
                            $category_repeat['queue_remind_id'] = $result['queue_id'];
                        } else {
                            $response['msg']['category_repeat_queue'] = $result['msg'];
                        }
                    }
                    if (!empty($category_repeat['time_out'])) {
                        $result = $this->dispatch_job_remind($time_out_datetime, $id); // Khi chỉnh sửa lời nhắc, thì chỉnh sửa luôn queue_job
                        if (isset($result['queue_id'])) {
                            $category_repeat['queue_id'] = $result['queue_id'];
                            $category_repeat['queue_before_id'] = $result['queue_before_id'];
                        } else {
                            $response['msg']['queue_id_remind_old'] = $result['msg'];
                        }
                    }
                    $category_repeat->save();
                    $query['repeat'] = array( // Sau khi tạo cái mới, thì xóa repeat của cái cũ
                        'type' => '',
                        'option' => [],
                        'option_time' => [],
                    );
                    $query['is_important'] = 0;
                } else {
                    $response['error'] = 'Xảy ra lỗi khi lặp lại thẻ';
                }
            } else {
                $response['error'] = 'Không tìm thấy category';
            }
        }

        $todo = Todo::where('_id', $id);
        $queue_id_old = $todo->first()->queue_id; // Lấy ra queue_id
        $queue_before_id_old = $todo->first()->queue_before_id;
        $queue_remind_id_old = $todo->first()->queue_remind_id;

        if (!empty($queue_id_old)){
            QueueJob::destroy($queue_id_old);
        }
        if (!empty($queue_before_id_old)){ // Nếu đã được set queue job rồi thì xóa đi
            QueueJob::destroy($queue_before_id_old);
        }
        if (!empty($queue_remind_id_old)){ // Nếu đã được set queue job rồi thì xóa đi
            QueueJob::destroy($queue_remind_id_old);
        }
        // Thêm nhắc nhở vào hàng đợi
        if (!empty($remind)) {
            $remind_t = new DateTime($remind);
            $query['remind'] = new UTCDateTime($remind_t);
            $result = $this->dispatch_job_remind($remind_t, $id, false, 'remind'); // Khi chỉnh sửa lời nhắc, thì chỉnh sửa luôn queue_job
            if (isset($result['queue_id'])) {
                $query['queue_remind_id'] = $result['queue_id'];
            } else {
                $response['msg']['queue_id_remind_old'] = $result['msg'];
            }
        } else {
            $query['remind'] = null;
            $query['queue_remind_id'] = null;
        }
        if ($todo) {
            $response['mess'] = 'Cập nhật thành công';
            $c_name = 'todos_' . $uid . '_' .  $category_id;
            $response['remove_memcache'] = Cache::forget($c_name);
        } else {
            $response['error'] = 'Đã xảy ra lỗi';
        }
        $response['query'] = $query;
        $todo->update($query);
        $code = 200;
        $response['status'] = $code;

        return response()->json($response, $code);
    }

    private function dispatch_job_remind($time, $id, $is_before = true, $type = 'time_out') {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $current_time = new DateTime("NOW");
        $interval = $time->diff($current_time, true);
        $years = $interval->y; $months = $interval->m; $days = $interval->d; $hours = $interval->h; $minutes = $interval->i + 1;
        if ($current_time < $time) { // tao moi queue neu current_time < remind
            try {
                $job = (new RemindJob($id, $type))
                    ->delay(Carbon::now()
                    ->addYears($years)
                    ->addMonths($months)
                    ->addDays($days)
                    ->addHours($hours)
                    ->subHours(7)
                    ->addMinutes($minutes));
                $job_id = app(Dispatcher::class)->dispatch($job);

                if ($is_before) {
                    // Nếu thời gian remind - current time > 5 thì tạo thêm 1 job
                    if ($years < 1 && $months < 1 && $days < 1 && $hours - 7 < 1 && $minutes < 5) {
                    } else {
                        $job_before = (new RemindJob($id, 'before_time_out'))
                        ->delay(Carbon::now()
                        ->addYears($years)
                        ->addMonths($months)
                        ->addDays($days)
                        ->addHours($hours - 7)
                        ->addMinutes($minutes)
                        ->subMinutes(5)); // Giảm đi 5 phút
                        $job_before_id = app(Dispatcher::class)->dispatch($job_before);
                    }
                }

                return array(
                    'queue_id' => (string) $job_id,
                    'queue_before_id' => isset($job_before_id) ? (string) $job_before_id : null,
                    // 'msg' => 'Tạo queue thành công',
                    // 'msg' => [(string) $job_id, isset($job_before_id) ? (string) $job_before_id : null],
                    'msg' => array(
                        'current' => $current_time,
                        'remind' => $time,
                        'interval' => $interval,
                    ),
                );
            } catch(Exception $e) {
                return array(
                    'msg' => $e->getMessage(),
                );
            }
        }
    }

    private function calculator_time_repeat($repeat, $time_out_datetime) {
        switch($repeat['type']) {
            case 'daily':
                return $time_out_datetime->modify('+1 day');

            case 'weekly':
                $days = $repeat['option'];
                $next_day = '';
                do {
                    $time_out_datetime = $time_out_datetime->modify('+1 day');
                    $next_day = $time_out_datetime->format('D');
                } while(!in_array($next_day, $days));
                return $time_out_datetime;

            case 'monthly':
                return $time_out_datetime->modify('+1 month');

            case 'annual':
                return $time_out_datetime->modify('+1 year');

            case 'option':
                $times = $repeat['option_time']['times']; // số lần (VD: lặp lại mỗi 2 ngày, lặp lại mỗi 3 tháng )
                $unit = $repeat['option_time']['unit']; // đơn vị (tuần, ngày)
                if ($unit !== 'week') {
                    return $time_out_datetime->modify("+$times $unit");
                } else {
                    $days = $repeat['option'];              // Các ngày
                    // Tìm trong tuần trước, Nếu trôi qua hết những ngày trong tuần thì mới tăng thêm $time - 1 tuần
                    $stopped = false;
                    $next_day = '';
                    do {
                        $time_out_datetime = $time_out_datetime->modify('+1 day');
                        $next_day = $time_out_datetime->format('D');
                        if (in_array($next_day, $days)) { // Nếu ngày tiếp theo trùng với các ngày trong option thì tức là tìm đc
                            $stopped = true;
                            break;
                        }
                    } while($next_day !== 'Sun'); // nếu ngày tiếp theo là thứ 2 thì dừng vòng lặp

                    // Nếu trong tuần ko tìm thấy thì tăng thêm tuần
                    if(!$stopped) {
                        $times = $times - 1;
                        $time_out_datetime = $time_out_datetime->modify("+$times week"); // Tăng thêm $times - 1 lần, sau đó xét tăng từng ngày
                        do {
                            $time_out_datetime = $time_out_datetime->modify('+1 day');
                            $next_day = $time_out_datetime->format('D');
                        } while(!in_array($next_day, $days)); 
                    }
                    return $time_out_datetime;
                }
                break;
            default:
                break;
        }
    }

    public function delete(Request $request){
        $this->validate($request, [
            'id' => 'required',
        ]);
        $id = $request->input('id');
        // Tìm và Xóa trong cache

        // Tìm và xóa trong db
        $response = array();
        $code = 200;

        $todo = Todo::find($id);
        if ($todo) {
            $queue_id_old = $todo->queue_id; // Lấy ra queue_id
            $queue_before_id_old = $todo->queue_before_id;
            $queue_remind_id_old = $todo->queue_remind_id;
            $response['todo'] = $todo;
            $response['queue_id'] = array(
                'old' => $queue_id_old,
                'before' => $queue_before_id_old,
                'remind' => $queue_remind_id_old,
            );
            if (!empty($queue_id_old)){
                QueueJob::destroy($queue_id_old);
            }
            if (!empty($queue_before_id_old)){ // Nếu đã được set queue job rồi thì xóa đi
                QueueJob::destroy($queue_before_id_old);
            }
            if (!empty($queue_remind_id_old)){ // Nếu đã được set queue job rồi thì xóa đi
                QueueJob::destroy($queue_remind_id_old);
            }
            if (Todo::destroy($id)) {
                $response['mess'] = 'Xóa thành công';
            } else {
                $response['mess'] = 'Lỗi khi xóa todo';
                $code = 500;
            }
        } else {
            $response['mess'] = 'Không tìm thấy todo';
            $code = 404;
        }
        $response['status'] = $code;
        return response()->json($response, $code);
    }

    public function getOne(Request $request) {
        $this->validate($request, [
            'id' => 'required|string',
        ]);
        $response = [];
        $code = 200;
        $id = $request->input('id');
        $todo = Todo::find($id);
        if ($todo) {
            $todo_arr = array(
                'id' => $todo['_id'],
                'uid' => $todo['uid'],
                'title' => $todo['title'],
                'note' => isset($todo['note']) ? $todo['note'] : [],
                'remind' => isset($todo['remind']) ? $todo['remind'] : null,
                'repeat' => isset($todo['repeat']) ? $todo['repeat'] : array(
                    'type' => '',
                    'option' => [],
                    'option_time' => [],
                ),
                'files' => isset($todo['files']) ? $todo['files'] : [],
                'is_complete' => $todo['is_complete'],
                'is_important' => $todo['is_important'],
                'time_out' => isset($todo['time_out']) ? $todo['time_out'] : null,
                'created_at' => $todo['created_at'],
                'category_id' => $todo['category_id'],
                'queue_id' => isset($todo['queue_id']) ? $todo['queue_id'] : null,
                'queue_before_id' => isset($todo['queue_before_id']) ? $todo['queue_before_id'] : null,
                'queue_remind_id' => isset($todo['queue_remind_id']) ? $todo['queue_remind_id'] : null,
            );
            $response['todo'] = $todo_arr;
        } else {
            $response['todo'] = [];
            $code = 404;
        }
        return response()->json($response, $code);
    }

}
