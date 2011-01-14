<?php defined('SYSPATH') or die('No direct script access.');
/**
 * ORM Validation exceptions.
 *
 * @package    Kohana/ORM
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_ORM_Validation_Exception extends Kohana_Exception {

	/**
   * Array of validation objects
   * @var array
   */
	protected $_objects = array();

	/**
   * The _object_name property of the main ORM model this exception was created for
   * @var string
   */
	protected $_object_name = NULL;

	/**
	 * Constructs a new exception for the specified model
	 *
	 * @param  string     $object_name The _object_name of the model this exception is for
	 * @param  Validation $object      The Validation object of the model
	 * @param  string     $message     The error message
	 * @param  array      $values      The array of values for the error message
	 * @param  integer    $code        The error code for the exception
	 * @return void
	 */
	public function __construct($object_name, Validation $object, $message = 'Failed to validate array', array $values = NULL, $code = 0)
	{
		$this->_object_name = $object_name;
		$this->_objects['_object'] = $object;

		parent::__construct($message, $values, $code);
	}

	/**
	 * Adds a Validation object to this exception
	 *
	 *     // The following will add a validation object for a profile model
	 *     // inside the exception for a user model.
	 *     $e->add_object('profile', $validation);
	 *     // The errors array will now look something like this
	 *     // array
	 *     // (
	 *     //   'username' => 'This field is required',
	 *     //   'profile'  => array
	 *     //   (
	 *     //     'first_name' => 'This field is required',
	 *     //   ),
	 *     // );
	 *
	 * @param  string     $alias    The relationship alias from the model
	 * @param  Validation $object   The Validation object to merge
	 * @param  mixed      $has_many The array key to use if this exception can be merged multiple times
	 * @return ORM_Validation_Exception
	 */
	public function add_object($alias, Validation $object, $has_many = FALSE)
	{
		if ($has_many === TRUE)
		{
			// This is most likely a has_many relationship
			$this->_objects[$alias][]['_object'] = $object;
		}
		elseif ($has_many)
		{
			// This is most likely a has_many relationship
			$this->_objects[$alias][$has_many]['_object'] = $object;
		}
		else
		{
			$this->_objects[$alias]['_object'] = $object;
		}

		return $this;
	}

	/**
	 * Merges an ORM_Validation_Exception object into the current exception
	 * Useful when you want to combine errors into one array
	 *
	 * @param  string                   $alias    The relationship alias from the model
	 * @param  ORM_Validation_Exception $object   The exception to merge
	 * @param  mixed                    $has_many The array key to use if this exception can be merged multiple times
	 * @return ORM_Validation_Exception
	 */
	public function merge($alias, ORM_Validation_Exception $object, $has_many = FALSE)
	{
		if ($has_many === TRUE)
		{
			// This is most likely a has_many relationship
			$this->_objects[$alias][] = $object->objects();
		}
		elseif ($has_many)
		{
			// This is most likely a has_many relationship
			$this->_objects[$alias][$has_many] = $object->objects();
		}
		else
		{
			$this->_objects[$alias] = $object->objects();
		}

		return $this;
	}

	/**
	 * Returns a merged array of the errors from all the Validation objects in this exception
	 *
	 *     // Will load Model_User errors from messages/orm-validation/user.php
	 *     $e->errors('orm-validation');
	 *
	 * @param   string  $directory Directory to load error messages from
	 * @param   mixed   $translate Translate the message
	 * @return  array
	 * @see generate_errors()
	 */
	public function errors($directory = NULL, $translate = TRUE)
	{
		if ($directory !== NULL)
		{
			// Everything starts at $directory/$object_name
			$directory .= '/'.$this->_object_name;
		}

		return $this->generate_errors($this->_objects, $directory, $translate);
	}

	/**
	 * Recursive method to fetch all the errors in this exception
	 *
	 * @param  array  $array     Array of Validation objects to get errors from
	 * @param  string $directory Directory to load error messages from
	 * @param  mixed  $translate Translate the message
	 * @return array
	 */
	protected function generate_errors(array $array, $directory, $translate)
	{
		$errors = array();

		foreach ($array as $alias => $object)
		{
			if ($directory === NULL)
			{
				// Return the raw errors
				$file = NULL;
			}
			else
			{
				$file = trim($directory.'/'.$alias, '/');
			}

			if (is_array($object))
			{
				// Recursively fill the errors array
				$errors[$alias] = $this->generate_errors($object, $file, $translate);
			}
			else
			{
				// Merge in this array of errors
				$errors += $object->errors($directory, $translate);
			}
		}

		return $errors;
	}

	/**
	 * Returns the protected _objects property from this exception
	 *
	 * @return array
	 */
	public function objects()
	{
		return $this->_objects;
	}
} // End Kohana_ORM_Validation_Exception
