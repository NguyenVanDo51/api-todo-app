<?php

namespace App;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Jenssegers\Mongodb\Eloquent\SoftDeletes;

class Todo extends Eloquent {

    use SoftDeletes;
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
    protected $collection = 'todo';

    protected $dates = ['created_at', 'updated_at', 'time_out', 'remind', 'deleted_at'];

    protected $fillable = ['uid', 'title', 'note', 'remind', 'repeat', 'files', 'is_complete', 'time_out', 'is_important', 'category_id', 'queue_id', 'queue_before_id', 'queue_remind_id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
