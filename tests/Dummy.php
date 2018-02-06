<?php

namespace Blasttech\EloquentRelatedPlus\Test;

use Illuminate\Database\Eloquent\Model;
use Blasttech\EloquentRelatedPlus\RelatedPlusInterface;
use Blasttech\EloquentRelatedPlus\RelatedPlusTrait;

class Dummy extends Model implements RelatedPlusInterface
{
    use RelatedPlusTrait;

    public $timestamps = false;
    protected $table = 'dummies';
    protected $guarded = [];
}
