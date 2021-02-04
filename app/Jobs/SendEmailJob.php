<?php

namespace App\Jobs;

use App\Todo;
use App\Category;
use App\Mail\RemindMailler;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class SendEmailJob extends Job
{
    public $timeout = 60;
    protected $id;
    protected $type;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id, $type)
    {
        $this->id = $id;        
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $todo = Todo::find($this->id);
        if ($todo) {
            if ($todo['category_id'] === 'all') {
                $todo['category'] = 'Tất cả công việc';
            } else {
                $category = Category::find($todo['_id']);
                $todo['category'] = $category['name'];
            }
            try {
                $response = Http::get('http://devdms.bota.vn:8000/api/v1/profile-private/get/' . $todo['uid']);
                Mail::to($response['profile']['email'])->send(new RemindMailler($todo, $this->type));
            } catch(Exception $e) {
                $this->fail();
            }
        }
    }
}
