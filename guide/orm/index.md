# ORM

Kohana 3.X includes a powerful Object Relational Mapping (ORM) module that uses the active record pattern and database introspection to determine a model's column information. ORM is integrated tightly with the [Validate] library.

The ORM allows for manipulation and control of data within a database as though it was a PHP object. Once you define the relationships ORM allows you to pull data from your database, manipulate the data in any way you like and then save the result back to the database without the use of SQL. By creating relationships between models that follow convention over configuration, much of the repetition of writing queries to create, read, update and delete information from the database can be reduced or entirely removed. All of the relationships can be handled automatically by the ORM library and you can access related data as standard object properties.

ORM is included with the Kohana 3.X install but needs to be enabled before you can use it. In your `application/bootstrap.php` file modify the call to Kohana::modules and include the ORM modules.

## ORM at a glance

To get started with ORM is easy. Just enable the modules required and define a model. Then you can use ORM in your controller:

	Kohana::modules(array(
		...
		'database' => MODPATH.'database',
		'orm' => MODPATH.'orm',
		...
	));

[!!] The database module is requried for the ORM module to work. Of course the database module has to be configured to use a existing database.

To create a model for the table `member` in your database, create the file `application/classes/model/member.php` with the following syntax:

	class Model_Member extends ORM
	{
		...
	}

To use ORM you can create a file in `application/classes/controller/member.php` with the following syntax:

		class Controller_Member extends Controller_Template {
		
			...
			// Get all members with the First name "Peter" find_all()
			// means we get all records matching the query.
			$member = ORM::factory('table_name')->where('colum_name', '=', 'value_to_look_for')->find_all();
			...
		
		}

## Complete example

### SQL schema

	CREATE TABLE IF NOT EXISTS `members` (
	  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `username` varchar(32) NOT NULL,
	  `firstname` varchar(32) NOT NULL,
	  `lastname` varchar(32) NOT NULL,
	  `email` varchar(127) DEFAULT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

### Model
	
	<?php defined('SYSPATH') or die('No direct access allowed.');

	class Model_Auth_User extends ORM {

		protected $_rules = array(
			'username' => array(
				'not_empty'  => NULL,
				'min_length' => array(4),
				'max_length' => array(32),
				'regex'      => array('/^[-\pL\pN_.]++$/uD'),
			),
			'firstname' => array(
				'not_empty'  => NULL,
				'min_length' => array(4),
				'max_length' => array(32),
				'regex'      => array('/^[-\pL\pN_.]++$/uD'),
			),
			'lastname' => array(
				'not_empty'  => NULL,
				'min_length' => array(4),
				'max_length' => array(32),
				'regex'      => array('/^[-\pL\pN_.]++$/uD'),
			),
			'email' => array(
				'not_empty'  => NULL,
				'min_length' => array(4),
				'max_length' => array(127),
				'email'      => NULL,
			),
		);
	}

[!!] As you can see there are arrays containing values for like min_length, max_length, regex. They are there to connect ORM with the [Validate] library.

[!!] Please notice that defining the primary key "id" in the model is not necessary. Also the table name in the database is plural and the model name is singular.

## Controller

	<?php defined('SYSPATH') or die('No direct access allowed.');
	
	class Controller_Member extends Controller_Template {
		
		public function action_index()
		{
			// -------------
			// - Example 1 -
			// -------------
			
			// Create an instance of a model
			$member = ORM::factory('member');
			
			// Get all members with the First name "Peter" find_all()
			// means we get all records matching the query.
			$member->where('firstname', '=', 'Peter')->find_all();

			// Count records in the $member object
			$member->count_all();
			
			// -------------
			// - Example 2 -
			// -------------
			
			// Create an instance of a model
			$member = ORM::factory('member');
			
			// Get a member with the user name "bongo" find() means
			// we only want the first record matching the query.
			$member->where('username', '=', 'bongo')->find();
			
			// -------------
			// - Example 3 -
			// -------------
			
			// Create an instance of a model
			$member = ORM::factory('member');
			
			// Do a INSERT query
			$member->username = 'bongo';
			$member->firstname = 'Peter';
			$member->lastname = 'Smith';
			$member->save();
			
			// -------------
			// - Example 4 -
			// -------------
			
			// Create an instance of a model where the
			// table field "id" is "1"
			$member = ORM::factory('member', 1);
			
			// You can create the instance like below
			// If you do not want to use the "id" field
			$member = ORM::factory('member')->where('username', '=', 'bongo')->find();
			
			// Do a UPDATE query
			$member->username = 'bongo';
			$member->firstname = 'Peter';
			$member->lastname = 'Smith';
			$member->save();
		}
	}

[!!] $member will be a PHP object where you can access the values from the query e.g. echo $member->firstname