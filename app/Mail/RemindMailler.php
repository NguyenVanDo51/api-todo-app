<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RemindMailler extends Mailable
{
    use Queueable, SerializesModels;

    protected $todo;
    protected $type;

    public function __construct($todo, $type)
    {
        $this->todo = $todo;
        $this->type = $type;
    }

    public function build()
    {
        $todo = $this->todo;
        $type = $this->type;
        return $this->view('remindEmail', compact('todo', 'type'));
    }
}