# One to one relationship example

This example explains how to handle both has-one and belongs-to relations with ORM. You will create two tables; members and member_infos for this purpose. This example will not implement foreign keys, request type handling nor it will catch [Validation exceptions](orm/validation).

## SQL schema

	CREATE TABLE IF NOT EXISTS `members` (
	  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `username` varchar(32) NOT NULL,
	  `password` varchar(100) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

	
	CREATE TABLE IF NOT EXISTS `member_infos` (
	  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `member_id` int(10) unsigned NOT NULL,
	  `first_name` varchar(32) NOT NULL,
	  `last_name` varchar(32) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
	
## Models

	<?php defined('SYSPATH') or die('No direct script access.');
	
	class Model_Member extends ORM {
		
		protected $_has_one = array(
			'info'		=>	array(
				'model'			=>	'member_info',
				'foreign_key'	=>	'member_id',
			),
		);
	}
	
[!!] In this case foreign_key isn't a must because it respects conventions.
	
	<?php defined('SYSPATH') or die('No direct script access.');
	
	class Model_Member_Info extends ORM {
		
		protected $_belongs_to = array(
			'member'	=>	array(),
		);
		
		public function rules()
		{
			return array(
				'member_id'		=>	array(),
			);
		}
	}
	
[!!] None of the relation parameters need to be defined because this relation fully respects conventions.

## Usage
	
	class Controller_Member extends Controller_Template {
	
		public function before()
		{
			parent::before();
		
			$member_id 		= $this->request->param('id');
			
			// One-to-one relations are loaded instantly
			$this->member 	= ORM::factory('member', $member_id);
		}

		public function action_create()
		{
			$member = ORM::factory('member')
				->values(array(
					'username'	=>	'Foo',
					'password'	=>	'Bar',
				));
				
			$info = ORM::factory('member_info')
				->values(array(
					'first_name'=>	'Foobr',
					'last_name' =>	'Barr',
				));
				
			try
			{
				$member->create($info->validation());
				
				// Assign $info to the member (belongs_to only)
				$info->member = $member;
				
				$info->create();
			}
			catch (ORM_Validation_Exception $e)
			{
				// ...
			}
		}
		
		public function action_update()
		{
			// Update member's info
			$info = $this->member->info->values(
					array(
						'first_name' 	=> 'Barb',
						'last_name'		=> 'Foof',
					), 
					// Allow only changing the first_name
					array(
						'first_name',
					)
				)
				->update();
		}
		
		public function action_delete()
		{
			// Delete member's info
			$this->member->info->delete();
		}
	
	}
	
