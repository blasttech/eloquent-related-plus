<?php

namespace Blasttech\EloquentRelatedPlus\Test;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Blasttech\EloquentRelatedPlus\RelatedPlus;
use Blasttech\EloquentRelatedPlus\RelatedPlusTrait;

class DummyWithSoftDeletes extends Model implements RelatedPlus
{
    use SoftDeletes, RelatedPlusTrait;

    protected $table = 'dummies';
    protected $guarded = [];
    public $timestamps = false;
}
