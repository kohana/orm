# Simple example

This is a simple example of a single ORM model, that has no relationships, but uses validation on the fields. 

## SQL schema

	CREATE TABLE IF NOT EXISTS `members` (
	  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `username` varchar(32) NOT NULL,
	  `firstname` varchar(32) NOT NULL,
	  `lastname` varchar(32) NOT NULL,
	  `email` varchar(127) DEFAULT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

## Model
	
	<?php defined('SYSPATH') or die('No direct access allowed.');

	class Model_Member extends ORM {

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

[!!] The `$_rules` array will be passed to a [Validate] object and tested when you call `check()`. 

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