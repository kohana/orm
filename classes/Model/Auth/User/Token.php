<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Default auth user token.
 *
 * @package    Kohana/Auth
 * @author     Kohana Team
 * @copyright  (c) 2007-2014 Kohana Team
 * @license    http://kohanaframework.org/license
 */
abstract class Model_Auth_User_Token extends ORM {

	/**
	 * @var array "Belongs to" relationships
	 */
	protected $_belongs_to = array(
		'user' => array('model' => 'User'),
	);

	/**
	 * @var array Auto-updated column at create
	 * @see ORM::$_created_column
	 */
	protected $_created_column = array(
		'column' => 'created',
		'format' => TRUE,
	);

	/**
	 * Handles garbage collection and deleting of expired objects.
	 *
	 * @param  mixed $id
	 * @return void
	 */
	public function __construct($id = NULL)
	{
		parent::__construct($id);

		if (mt_rand(1, 50) == 1)
		{
			// Do garbage collection
			$this->delete_expired();
		}

		if ($this->expires < time() AND $this->_loaded)
		{
			// This object has expired
			$this->delete();
		}
	}

	/**
	 * Deletes all expired tokens.
	 *
	 * @return ORM
	 */
	public function delete_expired()
	{
		DB::delete($this->_table_name)
			->where('expires', '<', time())
			->execute($this->_db);

		return $this;
	}

	/**
	 * Create new token object.
	 *
	 * @param  Validation $validation
	 * @return ORM
	 */
	public function create(Validation $validation = NULL)
	{
		$this->token = $this->_create_token();

		return parent::create($validation);
	}

	/**
	 * Create new token string.
	 *
	 * @return string
	 */
	protected function _create_token()
	{
		do
		{
			$token = sha1(uniqid(Text::random('alnum', 32), TRUE));
		}
		while (ORM::factory('User_Token', array('token' => $token))->loaded());

		return $token;
	}

}
