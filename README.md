# Add extra search and order functionality to Laravel Eloquent models.

[![Latest Version](https://img.shields.io/github/tag/blasttech/eloquent-related-plus.svg?style=flat-square)](https://github.com/blasttech/eloquent-related-plus/releases)
[![Build Status](https://img.shields.io/travis/blasttech/eloquent-related-plus.svg?style=flat-square)](https://travis-ci.org/blasttech/eloquent-related-plus)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![StyleCI](https://styleci.io/repos/117756196/shield?branch=master)](https://styleci.io/repos/117756196)
[![Quality Score](https://img.shields.io/scrutinizer/g/blasttech/eloquent-related-plus.svg?style=flat-square)](https://scrutinizer-ci.com/g/blasttech/eloquent-related-plus)
[![Total Downloads](https://img.shields.io/packagist/dt/blasttech/eloquent-related-plus.svg?style=flat-square)](https://packagist.org/packages/blasttech/eloquent-related-plus)

This package provides a trait that adds extra ways to order and search complex models.

## Installation

This package can be installed through Composer.

```
$ composer require blasttech/eloquent-related-plus
```

## Usage

To add complex order and search behaviour to your model you must:<br />
1. specify that the model will conform to ```Blasttech\EloquentRelatedPlus\RelatePlus```<br />
2. use the trait ```Blasttech\EloquentRelatedPlus\RelatedPlusTrait```<br />

### Example

Functions: 
 * modelJoin($relation_name, $operator = '=', $type = 'left', $where = false, $related_select = true)
 * orderByCustom($order_field, $dir, $order_fields = null, $order_defaults = null)
 * orderByWith($order)
 * search($search = '')
 * setSubquery($model)


## modelJoin

Use this scope to add a join for the specified model.

Examples:
```
$query->modelJoin('Customer', '=', 'left', false, false)
```
This will add a left join to the *Customer* model.

```
$query->modelJoin('Customer', '=', 'left', true, true)
```
This will add a left join to the *Customer* model and use 'where' instead of 'on' and include all the fields from the model in the select.   

## orderByCustom

## orderByWith

## search

## setSubquery




#### Credits

The example shown in 
http://laravel-tricks.com/tricks/automatic-join-on-eloquent-models-with-relations-setup 
was used as a basis for the modelJoin and relationJoin scopes.

 

