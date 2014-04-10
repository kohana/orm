# Creating your Model

To create a model for the table `members` in your database, create the file `application/classes/model/member.php` with the following syntax:

	class Model_Member extends ORM
	{
		...
	}

(this should provide more examples)

## Overriding the Table name

If you wish to change the database table that a model uses, just override the `$_table_name` variable like this:

	protected $_table_name = 'strange_tablename';

## Changing the primary key

ORM assumes each model (and database table) has an `id` column that is indexed and unique. If your primary key column isn't named `id`, that's fine - just override the `$_primary_key` variable like this:

	protected $_primary_key = 'strange_pkey';

## Use a non-default database

For each model, you can define which database configuration ORM will run queries on. If you override the `$_db_group` variable in your model, ORM will connect to that database. Example:

	protected $_db_group = 'alternate';

## Auto-update columns

If you'd like date/time columns automatically updated whenever your model is 
changed or created you can define the `$_created_column` and/or 
`$_updated_column` properties in your model. Use an `array` with 'column' 
and 'format' keys & values, where 'column' is the column name to be updated 
and 'format' is the date format to use. You can set 'format' to TRUE 
for a Unix timestamp, or specify a string format like that expected for the 
php `date()` function.
 
The following example will automatically update the 'last_changed' column 
with the current Unix timestamp whenever the model is updated:

	class Model_Member extends ORM
	{
		protected $_updated_column = array('column' => 'last_changed',
		                                   'format' => TRUE);		 
	}
