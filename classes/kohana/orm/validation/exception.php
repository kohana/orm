<?php defined('SYSPATH') or die('No direct script access.');
/**
 * ORM Validation exceptions.
 *
 * @package    ORM
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kohana_ORM_Validation_Exception extends Kohana_Exception {

	// Array of validation objects
	protected $_objects = array();

	// The _object_name property of the main ORM model this exception was created for
	protected $_object_name = NULL;

	public function __construct($object_name, Validate $object, $message = 'Failed to validate array', array $values = NULL, $code = 0)
	{
		$this->_object_name = $object_name;
		$this->_objects['_object'] = $object;

		parent::__construct($message, $values, $code);
	}

	public function add_object(Validate $object, $alias, $has_many = FALSE)
	{
		if ($has_many)
		{
			// This is most likely a has_many relationship
			$this->_objects[$alias][]['_object'] = $object;
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
	 * @param ORM_Validation_Exception $object
	 * @param String $alias
	 * @param Bool   $has_many
	 * @return object
	 */
	public function merge(ORM_Validation_Exception $object, $alias, $has_many = FALSE)
	{
		if ($has_many)
		{
			// This is most likely a has_many relationship
			$this->_objects[$alias][] = $object->objects();
		}
		else
		{
			$this->_objects[$alias] = $object->objects();
		}

		return $this;
	}

	public function errors($directory = 'form-errors', $translate = TRUE)
	{
		return $this->generate_errors($this->_object_name, $this->_objects, $directory, $translate);
	}

	protected function generate_errors($alias, array $array, $directory, $translate)
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
				$errors[$alias] = $this->generate_errors($alias, $object, $directory, $translate);
			}
			elseif ($alias === '_object')
			{
				// Merge in this array of errors
				$errors += $object->errors($file, $translate);
			}
			else
			{
				// Namespace everything else appropriately
				$errors[$alias] = $object->generate_errors($alias, $object, $directory, $translate);
			}
		}

		return $errors;
	}

	public function objects()
	{
		return $this->_objects;
	}
} // End Kohana_ORM_Validation_Exception
