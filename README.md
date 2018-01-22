# eloquent-related-plus
Adds search and order functionality to Laravel Eloquent models.

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

 

