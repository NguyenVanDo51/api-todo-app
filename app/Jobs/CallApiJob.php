<?php

namespace App\Jobs;

use App\Todo;

class CallApiJob extends Job
{
    public $timeout = 60;
    protected $id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Todo::create([
                'uid' => 223,
                'title' => 'Call API ' . $this->id,
                'note' => [],
                'remind' => null,
                'repeat' => array(
                    'type' => '',
                    'option' => [],
                    'option_time' => [],
                ),
                'files' => [],
                'is_complete' => 0,
                'is_important' => 0,
                'time_out' => null,
        ]);
    }
}
