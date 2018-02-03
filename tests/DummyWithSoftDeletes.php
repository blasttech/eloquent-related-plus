<?php

namespace Blasttech\EloquentRelatedPlus\Test;

use Illuminate\Database\Eloquent\Model;
use Blasttech\EloquentRelatedPlus\RelatedPlus;
use Blasttech\EloquentRelatedPlus\RelatedPlusTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class DummyWithSoftDeletes extends Model implements RelatedPlus
{
    use SoftDeletes, RelatedPlusTrait;

    protected $table = 'dummies';
    protected $guarded = [];
    public $timestamps = false;
}
