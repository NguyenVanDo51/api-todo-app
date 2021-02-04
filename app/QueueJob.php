<?php

namespace App;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class QueueJob extends Eloquent {
    /**
     * @var string
     */
    protected $primaryKey = '_id';
    /**
     * @var string
     */
    protected $connection = 'mongodb';
    /**
     * @var string
     */
    protected $collection = 'queue_jobs';
}
