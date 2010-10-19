# Using ORM


## Load a new model instance

To create a new `Model_User` instance you can do two things:

	$user = ORM::factory('user');
	// or
	$user = new Model_User();

## Inserting

To insert a new record into the database, create a new instance of the model:

	$user = ORM::factory('user');

Then, assign values for each of the properties;

	$user->first_name = 'Trent';
	$user->last_name = 'Reznor';
	$user->city = 'Mercer';
	$user->state = 'PA';

Insert the new record into the database by running [ORM::save]:

	$user->save();

[ORM::save] checks to see if a value is set for the primary key (`id` by default). If the primary key is set, then ORM will execute an `UPDATE` otherwise it will execute an `INSERT`.


## Finding a object

To find an object you can call the [ORM::find] function or pass the id into the ORM constructor:

	//find user with ID 20
	$user = ORM::factory('user');
	$user->find(20);
	// or
	$user = ORM::factory('user', 20);

## Check that ORM loaded a record

Use the [ORM::loaded] function to check that ORM successfully loaded a record.

	if ($user->loaded())
	{
		//load was successful
	}
	else
	{
		//error
	}

## Updating and Saving

Once an ORM model has been loaded, you can modify a model's properties like this:

	$user->first_name = "Trent";
	$user->last_name = "Reznor";

And if you want to save the changes you just made back to the database, just run a `save()` call like this:

	$user->save();



## Deleting



## Ignoring Columns

Sometimes you might want to store a property for a model but won't want it to be saved in the database. If you add an column name to the `$_ignored_columns` array then ORM will not save or touch that column. Example:

	protected $_ignored_columns = array('password_confirm');


## Overriding the Table name

If you wish to change the database table that a model uses, just override the `$_table_name` variable like this:

	protected $_table_name = 'strange_tablename';

## Changing the primary key

ORM assumes each model (and database table) have an `id` column that is indexed and unique. If your primary key column isn't named `id`, that's fine - just override the `$_primary_key` variable like this:

	protected $_primary_key = 'strange_pkey';

## Use a non-default database

For each model, you can define which database configuration ORM will run queries on. If you override the `$_db` variable in your model, ORM will connect to that database. Example:

	protected $_db = 'alternate';
