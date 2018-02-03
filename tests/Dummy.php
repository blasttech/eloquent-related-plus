<?php

namespace Blasttech\EloquentRelatedPlus\Test;

use Illuminate\Database\Eloquent\Model;
use Blasttech\EloquentRelatedPlus\RelatedPlus;
use Blasttech\EloquentRelatedPlus\RelatedPlusTrait;

class Dummy extends Model implements RelatedPlus
{
    use RelatedPlusTrait;

    public $timestamps = false;
    protected $table = 'dummies';
    protected $guarded = [];
}
