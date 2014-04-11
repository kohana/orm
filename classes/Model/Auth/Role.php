<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Default auth role.
 *
 * @package    Kohana/Auth
 * @author     Kohana Team
 * @copyright  (c) 2007-2014 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
abstract class Model_Auth_Role extends ORM {

	/**
	 * @var array "Has many" relationships
	 */
	protected $_has_many = array(
		'users' => array('model' => 'User', 'through' => 'roles_users'),
	);
	
	/**
	 * [Rule definitions](../orm/validation#defining-rules) for validation.
	 *
	 * @return array
	 */
	public function rules()
	{
		return array(
			'name' => array(
				array('not_empty'),
				array('min_length', array(':value', 4)),
				array('max_length', array(':value', 32)),
			),
			'description' => array(
				array('max_length', array(':value', 255)),
			)
		);
	}

}
