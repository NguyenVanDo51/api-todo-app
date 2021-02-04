<?php

namespace App\Jobs;

use App\Todo;

class RemindJob extends Job
{
    public $timeout = 60;
    public $tries = 1;
    protected $id;
    protected $type;

    public function __construct($id, $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

    public function handle()
    {
        // $call_api_job = (new CallApiJob($this->id));
        // dispatch($call_api_job);
        $send_email_job = (new SendEmailJob($this->id, $this->type));
        dispatch($send_email_job);
    }
}
