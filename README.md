# Add extra search and order functionality to Laravel Eloquent models.

[![Latest Version](https://img.shields.io/github/tag/blasttech/eloquent-related-plus.svg?style=flat-square)](https://github.com/blasttech/eloquent-related-plus/releases)
[![Build Status](https://img.shields.io/travis/blasttech/eloquent-related-plus.svg?style=flat-square)](https://travis-ci.org/blasttech/eloquent-related-plus)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![StyleCI](https://styleci.io/repos/117756196/shield?branch=master)](https://styleci.io/repos/117756196)
[![Quality Score](https://img.shields.io/scrutinizer/g/blasttech/eloquent-related-plus.svg?style=flat-square)](https://scrutinizer-ci.com/g/blasttech/eloquent-related-plus)
[![Total Downloads](https://img.shields.io/packagist/dt/blasttech/eloquent-related-plus.svg?style=flat-square)](https://packagist.org/packages/blasttech/eloquent-related-plus)

This package provides a trait that adds extra ways to order and search complex models. With Eloquent, when relations are used (eg. HasOne, HasMany, BelongsTo) the primary model can't be sorted using a field in a related table. 

For example, you might have a list of Customers with a one-to-one relation (HasOne) to a Contacts model for the Customer contact.
```php
class Customer extends Eloquent
{
    public function sales_rep()
    {
        return $this->hasOne(Contact::class, 'id', 'sales_rep_id');
    }
}
```

If you had a table of customers and wanted to sort it by Contact name, you nomrally wouldn't be able to in Laravel using the relation above, but you could with this package.   

## Installation

This package can be installed through Composer.
```
$ composer require blasttech/eloquent-related-plus
```

## Usage

To add complex order and search behaviour to your model you must:
1. specify that the model will conform to ```Blasttech\EloquentRelatedPlus\RelatedPlusInterface```<br />
2. use the trait ```Blasttech\EloquentRelatedPlus\RelatedPlusTrait```

Using the earlier example:
```php
use App\Contact;
use Blasttech\EloquentRelatedPlus\RelatedPlusInterface;
use Blasttech\EloquentRelatedPlus\RelatedPlusTrait;

class Customer extends Eloquent implements RelatedPlusInterface
{
    use RelatedPlusTrait;
    
    public function sales_rep()
    {
        return $this->hasOne(Contact::class, 'id', 'sales_rep_id');
    }
}
```

## modelJoin

Use this scope to add a join for a related model.
 * modelJoin($relation_name, $operator = '=', $type = 'left', $where = false, $related_select = true)

#### Example

```php
namespace App\Http\Controllers;

use App\Customer;

class CustomerController extends Controller
{
    public function getCustomers()
    {
        return Customer::select('*')
            ->modelJoin('Contacts', '=', 'left', false, false)
            ->get(); 
    }
    
    ...
}
```
This will add a left join to the *Contacts* model using the Contacts() relation, using 'where' instead of 'on' and include all the fields from the model in the select.   


##### $relation_name
The name of the relation. Only BelongsTo, HasOne, HasMany or HasOneOrMany relations will work. 

##### $operator
The operator used to join the related table. This will default to '=' but any of the standard Laravel join operators can be used.

##### $type
The type of join. This will default to 'left' but any of the standard Laravel join types are allowed.

##### $where
The method of joining the table. The default (false) uses an 'on' statement but if true, a where statement is used.

##### $related_select
The $related_select option determines if the fields in the joined table will be included in the query. If true, the field names will be in the format '_table_name.column_name_', for example, '_customer.contact_name_' with the table name and period (.) **included** in the field name. This is to allow fields from joined tables to be used when they have the same column names. 

## orderByCustom
 * orderByCustom($orderField, $dir, $orderFields = null, $orderDefaults = null)

#### Example

```php
use App\Customer;
use Blasttech\EloquentRelatedPlus\RelatedPlusInterface;
use Blasttech\EloquentRelatedPlus\RelatedPlusTrait;

class CustomerController extends Controller
{
    public function getCustomers()
    {
        return Customer::select('*')
            ->modelJoin('Contacts', '=', 'left', false, false)
            ->orderByCustom('contact_name'); 
    }
    
    ...
}
```
This will add a left join to the *Contacts* model using the Contacts() relation and then sort it by the contact_name.   

## search

#### Example

```php
use Blasttech\EloquentRelatedPlus\RelatedPlusInterface;
use Blasttech\EloquentRelatedPlus\RelatedPlusTrait;

class MyModel extends Eloquent implements RelatedPlusInterface
{
    use RelatedPlusTrait;
    
    public function getContact($contact_name)
    {
        return Customer::select('*')
            ->modelJoin('Contacts', '=', 'left', false, false)
            ->search($contact_name); 
    }
    
    ...
}
```
This will add a left join to the *Contacts* model using the Contacts() relation, and search for $contact_name.   


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


## Credits

The example shown in http://laravel-tricks.com/tricks/automatic-join-on-eloquent-models-with-relations-setup was used as a basis for the modelJoin and relationJoin scopes.
