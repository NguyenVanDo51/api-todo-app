<?php

namespace App;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Jenssegers\Mongodb\Eloquent\SoftDeletes;

class Category extends Eloquent {

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
    protected $collection = 'todo_category';

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = ['uid', 'name', 'color'];

    public function todos()
    {
        return $this->hasMany(Todo::class);
    }
}
