<?php defined('SYSPATH') or die('No direct script access.');
/**
 * [Object Relational Mapping][ref-orm] (ORM) is a method of abstracting database
 * access to standard PHP calls. All table rows are represented as model objects,
 * with object properties representing row data. ORM in Kohana generally follows
 * the [Active Record][ref-act] pattern.
 *
 * [ref-orm]: http://wikipedia.org/wiki/Object-relational_mapping
 * [ref-act]: http://wikipedia.org/wiki/Active_record
 *
 * $Id: ORM.php 4427 2009-06-19 23:31:36Z jheathco $
 *
 * @package    Kohana/ORM
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_ORM {

	// Current relationships
	protected $_has_one    = array();

	/**
	 *@var  array  Array of belongs to relationships. See [Relationships](orm/relationships) for usage.
	 */
	protected $_belongs_to = array();
	protected $_has_many   = array();

	// Relationships that should always be joined
	protected $_load_with = array();

	// Validation members
	protected $_validate  = NULL;
	protected $_rules     = array();
	protected $_callbacks = array();
	protected $_filters   = array();
	protected $_labels    = array();

	// Current object
	protected $_object  = array();
	protected $_changed = array();
	protected $_related = array();
	protected $_loaded  = FALSE;
	protected $_saved   = FALSE;
	protected $_sorting;

	// Foreign key suffix
	protected $_foreign_key_suffix = '_id';

	// Model table information
	protected $_object_name;
	protected $_object_plural;
	protected $_table_name;
	protected $_table_columns;
	protected $_ignored_columns = array();

	// Auto-update columns for creation and updates
	protected $_updated_column = NULL;
	protected $_created_column = NULL;

	// Table primary key and value
	protected $_primary_key  = 'id';
	protected $_primary_val  = 'name';

	// Model configuration
	protected $_table_names_plural = TRUE;
	protected $_reload_on_wakeup   = TRUE;

	// Database configuration
	protected $_db         = NULL;
	protected $_db_applied = array();
	protected $_db_pending = array();
	protected $_db_reset   = TRUE;
	protected $_db_builder;

	// With calls already applied
	protected $_with_applied = array();

	// Data to be loaded into the model from a database call cast
	protected $_preload_data = array();

	// Stores column information for ORM models
	protected static $_column_cache = array();

	// Callable database methods
	protected static $_db_methods = array
	(
		'where', 'and_where', 'or_where', 'where_open', 'and_where_open', 'or_where_open', 'where_close',
		'and_where_close', 'or_where_close', 'distinct', 'select', 'from', 'join', 'on', 'group_by',
		'having', 'and_having', 'or_having', 'having_open', 'and_having_open', 'or_having_open',
		'having_close', 'and_having_close', 'or_having_close', 'order_by', 'limit', 'offset', 'cached',
		'count_last_query'
	);

	// Members that have access methods
	protected static $_properties = array
	(
		'object_name', 'object_plural', 'loaded', 'saved', // Object
		'primary_key', 'primary_val', 'table_name', 'table_columns', // Table
		'has_one', 'belongs_to', 'has_many', 'has_many_through', 'load_with', // Relationships
		'validate', 'rules', 'callbacks', 'filters', 'labels' // Validation
	);

	/**
	 * Creates and returns a new model.
	 *
	 * @chainable
	 * @param   string  model name
	 * @param   mixed   parameter for find()
	 * @return  ORM
	 */
	public static function factory($model, $id = NULL)
	{
		// Set class name
		$model = 'Model_'.ucfirst($model);

		return new $model($id);
	}

	/**
	 * Prepares the model database connection and loads the object.
	 *
	 * @param   mixed  parameter for find or object to load
	 * @return  void
	 */
	public function __construct($id = NULL)
	{
		// Set the object name and plural name
		$this->_object_name   = strtolower(substr(get_class($this), 6));
		$this->_object_plural = Inflector::plural($this->_object_name);

		if ( ! isset($this->_sorting))
		{
			// Default sorting
			$this->_sorting = array($this->_primary_key => 'ASC');
		}

		if ( ! empty($this->_ignored_columns))
		{
			// Optimize for performance
			$this->_ignored_columns = array_combine($this->_ignored_columns, $this->_ignored_columns);
		}

		// Initialize database
		$this->_initialize();

		// Clear the object
		$this->clear();

		if ($id !== NULL)
		{
			if (is_array($id))
			{
				foreach ($id as $column => $value)
				{
					// Passing an array of column => values
					$this->where($column, '=', $value);
				}

				$this->find();
			}
			else
			{
				// Passing the primary key

				// Set the object's primary key, but don't load it until needed
				$this->_object[$this->_primary_key] = $id;

				// Object is considered saved until something is set
				$this->_saved = TRUE;
			}
		}
		elseif ( ! empty($this->_preload_data))
		{
			// Load preloaded data from a database call cast
			$this->_load_values($this->_preload_data);

			$this->_preload_data = array();
		}
	}

	/**
	 * Checks if object data is set.
	 *
	 * @param   string  column name
	 * @return  boolean
	 */
	public function __isset($column)
	{
		$this->_load();

		return
		(
			isset($this->_object[$column]) OR
			isset($this->_related[$column]) OR
			isset($this->_has_one[$column]) OR
			isset($this->_belongs_to[$column]) OR
			isset($this->_has_many[$column])
		);
	}

	/**
	 * Unsets object data.
	 *
	 * @param   string  column name
	 * @return  void
	 */
	public function __unset($column)
	{
		$this->_load();

		unset($this->_object[$column], $this->_changed[$column], $this->_related[$column]);
	}

	/**
	 * Displays the primary key of a model when it is converted to a string.
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return (string) $this->pk();
	}

	/**
	 * Allows serialization of only the object data and state, to prevent
	 * "stale" objects being unserialized, which also requires less memory.
	 *
	 * @return  array
	 */
	public function __sleep()
	{
		// Store only information about the object
		return array('_object_name', '_object', '_changed', '_loaded', '_saved', '_sorting', '_ignored_columns');
	}

	/**
	 * Prepares the database connection and reloads the object.
	 *
	 * @return  void
	 */
	public function __wakeup()
	{
		// Initialize database
		$this->_initialize();

		if ($this->_reload_on_wakeup === TRUE)
		{
			// Reload the object
			$this->reload();
		}
	}

	/**
	 * Handles pass-through to database methods. Calls to query methods
	 * (query, get, insert, update) are not allowed. Query builder methods
	 * are chainable.
	 *
	 * @param   string  method name
	 * @param   array   method arguments
	 * @return  mixed
	 */
	public function __call($method, array $args)
	{
		if (in_array($method, ORM::$_properties))
		{
			if ($method === 'loaded')
			{
				if ( ! isset($this->_object_name))
				{
					// Calling loaded method prior to the object being fully initialized
					return FALSE;
				}

				$this->_load();
			}
			elseif ($method === 'validate')
			{
				if ( ! isset($this->_validate))
				{
					// Initialize the validation object
					$this->_validate();
				}
			}

			// Return the property
			return $this->{'_'.$method};
		}
		elseif (in_array($method, ORM::$_db_methods))
		{
			// Add pending database call which is executed after query type is determined
			$this->_db_pending[] = array('name' => $method, 'args' => $args);

			return $this;
		}
		else
		{
			throw new Kohana_Exception('Invalid method :method called in :class',
				array(':method' => $method, ':class' => get_class($this)));
		}
	}

	/**
	 * Handles retrieval of all model values, relationships, and metadata.
	 *
	 * @param   string  column name
	 * @return  mixed
	 */
	public function __get($column)
	{
		if (array_key_exists($column, $this->_object))
		{
			$this->_load();

			return $this->_object[$column];
		}
		elseif (isset($this->_related[$column]) AND $this->_related[$column]->_loaded)
		{
			// Return related model that has already been loaded
			return $this->_related[$column];
		}
		elseif (isset($this->_belongs_to[$column]))
		{
			$this->_load();

			$model = $this->_related($column);

			// Use this model's column and foreign model's primary key
			$col = $model->_table_name.'.'.$model->_primary_key;
			$val = $this->_object[$this->_belongs_to[$column]['foreign_key']];

			$model->where($col, '=', $val)->find();

			return $this->_related[$column] = $model;
		}
		elseif (isset($this->_has_one[$column]))
		{
			$model = $this->_related($column);

			// Use this model's primary key value and foreign model's column
			$col = $model->_table_name.'.'.$this->_has_one[$column]['foreign_key'];
			$val = $this->pk();

			$model->where($col, '=', $val)->find();

			return $this->_related[$column] = $model;
		}
		elseif (isset($this->_has_many[$column]))
		{
			$model = ORM::factory($this->_has_many[$column]['model']);

			if (isset($this->_has_many[$column]['through']))
			{
				// Grab has_many "through" relationship table
				$through = $this->_has_many[$column]['through'];

				// Join on through model's target foreign key (far_key) and target model's primary key
				$join_col1 = $through.'.'.$this->_has_many[$column]['far_key'];
				$join_col2 = $model->_table_name.'.'.$model->_primary_key;

				$model->join($through)->on($join_col1, '=', $join_col2);

				// Through table's source foreign key (foreign_key) should be this model's primary key
				$col = $through.'.'.$this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			}
			else
			{
				// Simple has_many relationship, search where target model's foreign key is this model's primary key
				$col = $model->_table_name.'.'.$this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			}

			return $model->where($col, '=', $val);
		}
		else
		{
			throw new Kohana_Exception('The :property property does not exist in the :class class',
				array(':property' => $column, ':class' => get_class($this)));
		}
	}

	/**
	 * Handles setting of all model values, and tracks changes between values.
	 *
	 * @param   string  column name
	 * @param   mixed   column value
	 * @return  void
	 */
	public function __set($column, $value)
	{
		if ( ! isset($this->_object_name))
		{
			// Object not yet constructed, so we're loading data from a database call cast
			$this->_preload_data[$column] = $value;

			return;
		}

		if (array_key_exists($column, $this->_ignored_columns))
		{
			// No processing for ignored columns, just store it
			$this->_object[$column] = $value;
		}
		elseif (array_key_exists($column, $this->_object))
		{
			$this->_object[$column] = $value;

			if (isset($this->_table_columns[$column]))
			{
				// Data has changed
				$this->_changed[$column] = $column;

				// Object is no longer saved
				$this->_saved = FALSE;
			}
		}
		elseif (isset($this->_belongs_to[$column]))
		{
			// Update related object itself
			$this->_related[$column] = $value;

			// Update the foreign key of this model
			$this->_object[$this->_belongs_to[$column]['foreign_key']] = $value->pk();

			$this->_changed[$column] = $this->_belongs_to[$column]['foreign_key'];
		}
		else
		{
			throw new Kohana_Exception('The :property: property does not exist in the :class: class',
				array(':property:' => $column, ':class:' => get_class($this)));
		}
	}

	/**
	 * Set values from an array with support for one-one relationships.  This method should be used
	 * for loading in post data, etc.
	 *
	 * @param   array  array of key => val
	 * @return  ORM
	 */
	public function values($values)
	{
		foreach ($values as $key => $value)
		{
			if (array_key_exists($key, $this->_object) OR array_key_exists($key, $this->_ignored_columns))
			{
				// Property of this model
				$this->__set($key, $value);
			}
			elseif (isset($this->_belongs_to[$key]) OR isset($this->_has_one[$key]))
			{
				// Value is an array of properties for the related model
				$this->_related[$key] = $value;
			}
		}

		return $this;
	}

	/**
	 * Prepares the model database connection, determines the table name,
	 * and loads column information.
	 *
	 * @return  void
	 */
	protected function _initialize()
	{
		if ( ! is_object($this->_db))
		{
			// Get database instance
			$this->_db = Database::instance($this->_db);
		}

		if (empty($this->_table_name))
		{
			// Table name is the same as the object name
			$this->_table_name = $this->_object_name;

			if ($this->_table_names_plural === TRUE)
			{
				// Make the table name plural
				$this->_table_name = Inflector::plural($this->_table_name);
			}
		}

		if ( ! empty($this->_ignored_columns))
		{
			// Optimize for performance
			$this->_ignored_columns = array_combine($this->_ignored_columns, $this->_ignored_columns);
		}

		foreach ($this->_belongs_to as $alias => $details)
		{
			$defaults['model']       = $alias;
			$defaults['foreign_key'] = $alias.$this->_foreign_key_suffix;

			$this->_belongs_to[$alias] = array_merge($defaults, $details);
		}

		foreach ($this->_has_one as $alias => $details)
		{
			$defaults['model']       = $alias;
			$defaults['foreign_key'] = $this->_object_name.$this->_foreign_key_suffix;

			$this->_has_one[$alias] = array_merge($defaults, $details);
		}

		foreach ($this->_has_many as $alias => $details)
		{
			$defaults['model']       = Inflector::singular($alias);
			$defaults['foreign_key'] = $this->_object_name.$this->_foreign_key_suffix;
			$defaults['through']     = NULL;
			$defaults['far_key']     = Inflector::singular($alias).$this->_foreign_key_suffix;

			$this->_has_many[$alias] = array_merge($defaults, $details);
		}

		// Load column information
		$this->reload_columns();
	}

	/**
	 * Initializes validation rules, callbacks, filters, and labels
	 *
	 * @return void
	 */
	protected function _validate()
	{
		$this->_validate = Validate::factory($this->_object);

		foreach ($this->_rules as $field => $rules)
		{
			// PHP converts TRUE to int 1, so we have to fix that
			$field = ($field === 1) ? TRUE : $field;

			$this->_validate->rules($field, $rules);
		}

		foreach ($this->_filters as $field => $filters)
		{
			// PHP converts TRUE to int 1, so we have to fix that
			$field = ($field === 1) ? TRUE : $field;

			$this->_validate->filters($field, $filters);
		}

		// Use column names by default for labels
		$columns = array_keys($this->_table_columns);

		// Merge user-defined labels
		$labels = array_merge(array_combine($columns, $columns), $this->_labels);

		foreach ($labels as $field => $label)
		{
			$this->_validate->label($field, $label);
		}

		foreach ($this->_callbacks as $field => $callbacks)
		{
			// PHP converts TRUE to int 1, so we have to fix that
			$field = ($field === 1) ? TRUE : $field;
			
			foreach ($callbacks as $callback)
			{
				if (is_string($callback) AND method_exists($this, $callback))
				{
					// Callback method exists in current ORM model
					$this->_validate->callback($field, array($this, $callback));
				}
				else
				{
					// Try global function
					$this->_validate->callback($field, $callback);
				}
			}
		}
	}

	/**
	 * Returns the values of this object as an array, including any related one-one
	 * models that have already been loaded using with()
	 *
	 * @return  array
	 */
	public function as_array()
	{
		$object = array();

		foreach ($this->_object as $key => $val)
		{
			// Call __get for any user processing
			$object[$key] = $this->__get($key);
		}

		foreach ($this->_related as $key => $model)
		{
			// Include any related objects that are already loaded
			$object[$key] = $model->as_array();
		}

		return $object;
	}

	/**
	 * Binds another one-to-one object to this model.  One-to-one objects
	 * can be nested using 'object1:object2' syntax
	 *
	 * @param   string  target model to bind to
	 * @return  void
	 */
	public function with($target_path)
	{
		if (isset($this->_with_applied[$target_path]))
		{
			// Don't join anything already joined
			return $this;
		}

		// Split object parts
		$aliases = explode(':', $target_path);
		$target	 = $this;
		foreach ($aliases as $alias)
		{
			// Go down the line of objects to find the given target
			$parent = $target;
			$target = $parent->_related($alias);

			if ( ! $target)
			{
				// Can't find related object
				return $this;
			}
		}

		// Target alias is at the end
		$target_alias = $alias;

		// Pop-off top alias to get the parent path (user:photo:tag becomes user:photo - the parent table prefix)
		array_pop($aliases);
		$parent_path = implode(':', $aliases);

		if (empty($parent_path))
		{
			// Use this table name itself for the parent path
			$parent_path = $this->_table_name;
		}
		else
		{
			if ( ! isset($this->_with_applied[$parent_path]))
			{
				// If the parent path hasn't been joined yet, do it first (otherwise LEFT JOINs fail)
				$this->with($parent_path);
			}
		}

		// Add to with_applied to prevent duplicate joins
		$this->_with_applied[$target_path] = TRUE;

		// Use the keys of the empty object to determine the columns
		foreach (array_keys($target->_object) as $column)
		{
			// Skip over ignored columns
			if( ! in_array($column, $target->_ignored_columns))
			{
				$name   = $target_path.'.'.$column;
				$alias  = $target_path.':'.$column;

				// Add the prefix so that load_result can determine the relationship
				$this->select(array($name, $alias));
			}
		}

		if (isset($parent->_belongs_to[$target_alias]))
		{
			// Parent belongs_to target, use target's primary key and parent's foreign key
			$join_col1 = $target_path.'.'.$target->_primary_key;
			$join_col2 = $parent_path.'.'.$parent->_belongs_to[$target_alias]['foreign_key'];
		}
		else
		{
			// Parent has_one target, use parent's primary key as target's foreign key
			$join_col1 = $parent_path.'.'.$parent->_primary_key;
			$join_col2 = $target_path.'.'.$parent->_has_one[$target_alias]['foreign_key'];
		}

		// Join the related object into the result
		$this->join(array($target->_table_name, $target_path), 'LEFT')->on($join_col1, '=', $join_col2);

		return $this;
	}

	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param   int  Type of Database query
	 * @return  ORM
	 */
	protected function _build($type)
	{
		// Construct new builder object based on query type
		switch ($type)
		{
			case Database::SELECT:
				$this->_db_builder = DB::select();
			break;
			case Database::UPDATE:
				$this->_db_builder = DB::update($this->_table_name);
			break;
			case Database::DELETE:
				$this->_db_builder = DB::delete($this->_table_name);
		}

		// Process pending database method calls
		foreach ($this->_db_pending as $method)
		{
			$name = $method['name'];
			$args = $method['args'];

			$this->_db_applied[$name] = $name;

			call_user_func_array(array($this->_db_builder, $name), $args);
		}

		return $this;
	}

	/**
	 * Loads the given model
	 *
	 * @return  ORM
	 */
	protected function _load()
	{
		if ( ! $this->_loaded AND ! $this->empty_pk() AND ! isset($this->_changed[$this->_primary_key]))
		{
			// Only load if it hasn't been loaded, and a primary key is specified and hasn't been modified
			return $this->find($this->pk());
		}
	}

	/**
	 * Finds and loads a single database row into the object.
	 *
	 * @chainable
	 * @param   mixed  primary key
	 * @return  ORM
	 */
	public function find($id = NULL)
	{
		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind relationship
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		if ($id !== NULL)
		{
			// Search for a specific column
			$this->_db_builder->where($this->_table_name.'.'.$this->_primary_key, '=', $id);
		}

		return $this->_load_result(FALSE);
	}

	/**
	 * Finds multiple database rows and returns an iterator of the rows found.
	 *
	 * @chainable
	 * @return  Database_Result
	 */
	public function find_all()
	{
		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind relationship
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		return $this->_load_result(TRUE);
	}

	/**
	 * Validates the current model's data
	 *
	 * @return  boolean
	 */
	public function check()
	{
		if ( ! isset($this->_validate))
		{
			// Initialize the validation object
			$this->_validate();
		}
		else
		{
			// Validation object has been created, just exchange the data array
			$this->_validate->exchangeArray($this->_object);
		}

		if ($this->_validate->check())
		{
			// Fields may have been modified by filters
			$this->_object = array_merge($this->_object, $this->_validate->getArrayCopy());

			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Saves the current object.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function save()
	{
		if (empty($this->_changed))
			return $this;

		$data = array();
		foreach ($this->_changed as $column)
		{
			// Compile changed data
			$data[$column] = $this->_object[$column];
		}

		if ( ! $this->empty_pk() AND ! isset($this->_changed[$this->_primary_key]))
		{
			// Primary key isn't empty and hasn't been changed so do an update

			if (is_array($this->_updated_column))
			{
				// Fill the updated column
				$column = $this->_updated_column['column'];
				$format = $this->_updated_column['format'];

				$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
			}

			$query = DB::update($this->_table_name)
				->set($data)
				->where($this->_primary_key, '=', $this->pk())
				->execute($this->_db);

			// Object has been saved
			$this->_saved = TRUE;
		}
		else
		{
			if (is_array($this->_created_column))
			{
				// Fill the created column
				$column = $this->_created_column['column'];
				$format = $this->_created_column['format'];

				$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
			}

			$result = DB::insert($this->_table_name)
				->columns(array_keys($data))
				->values(array_values($data))
				->execute($this->_db);

			if ($result)
			{
				if ($this->empty_pk())
				{
					// Load the insert id as the primary key
					// $result is array(insert_id, total_rows)
					$this->_object[$this->_primary_key] = $result[0];
				}

				// Object is now loaded and saved
				$this->_loaded = $this->_saved = TRUE;
			}
		}

		if ($this->_saved === TRUE)
		{
			// All changes have been saved
			$this->_changed = array();
		}

		return $this;
	}

	/**
	 * Updates all existing records
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function save_all()
	{
		$this->_build(Database::UPDATE);

		if (empty($this->_changed))
			return $this;

		$data = array();
		foreach ($this->_changed as $column)
		{
			// Compile changed data omitting ignored columns
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_updated_column))
		{
			// Fill the updated column
			$column = $this->_updated_column['column'];
			$format = $this->_updated_column['format'];

			$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
		}

		$this->_db_builder->set($data)->execute($this->_db);

		return $this;
	}

	/**
	 * Deletes the current object from the database. This does NOT destroy
	 * relationships that have been created with other objects.
	 *
	 * @chainable
	 * @param   mixed  id to delete
	 * @return  ORM
	 */
	public function delete($id = NULL)
	{
		if ($id === NULL)
		{
			// Use the the primary key value
			$id = $this->pk();
		}

		if ( ! empty($id) OR $id === '0')
		{
			// Delete the object
			DB::delete($this->_table_name)
				->where($this->_primary_key, '=', $id)
				->execute($this->_db);
		}

		return $this;
	}

	/**
	 * Delete all objects in the associated table. This does NOT destroy
	 * relationships that have been created with other objects.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function delete_all()
	{
		$this->_build(Database::DELETE);

		$this->_db_builder->execute($this->_db);

		return $this->clear();
	}

	/**
	 * Unloads the current object and clears the status.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function clear()
	{
		// Create an array with all the columns set to NULL
		$values = array_combine(array_keys($this->_table_columns), array_fill(0, count($this->_table_columns), NULL));

		// Replace the object and reset the object status
		$this->_object = $this->_changed = $this->_related = array();

		// Replace the current object with an empty one
		$this->_load_values($values);

		$this->reset();

		return $this;
	}

	/**
	 * Reloads the current object from the database.
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function reload()
	{
		$primary_key = $this->pk();

		// Replace the object and reset the object status
		$this->_object = $this->_changed = $this->_related = array();

		// Only reload the object if we have one to reload
		if ($this->_loaded)
			return $this->find($primary_key);
		else
			return $this->clear();
	}

	/**
	 * Reload column definitions.
	 *
	 * @chainable
	 * @param   boolean  force reloading
	 * @return  ORM
	 */
	public function reload_columns($force = FALSE)
	{
		if ($force === TRUE OR empty($this->_table_columns))
		{
			if (isset(ORM::$_column_cache[$this->_object_name]))
			{
				// Use cached column information
				$this->_table_columns = ORM::$_column_cache[$this->_object_name];
			}
			else
			{
				// Grab column information from database
				$this->_table_columns = $this->list_columns(TRUE);

				// Load column cache
				ORM::$_column_cache[$this->_object_name] = $this->_table_columns;
			}
		}

		return $this;
	}

	/**
	 * Tests if this object has a relationship to a different model.
	 *
	 * @param   string   alias of the has_many "through" relationship
	 * @param   ORM      related ORM model
	 * @return  boolean
	 */
	public function has($alias, $model)
	{
		// Return count of matches as boolean
		return (bool) DB::select(array('COUNT("*")', 'records_found'))
			->from($this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
			->where($this->_has_many[$alias]['far_key'], '=', $model->pk())
			->execute($this->_db)
			->get('records_found');
	}

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 * @param   string   alias of the has_many "through" relationship
	 * @param   ORM      related ORM model
	 * @param   array    additional data to store in "through"/pivot table
	 * @return  ORM
	 */
	public function add($alias, ORM $model, $data = NULL)
	{
		$columns = array($this->_has_many[$alias]['foreign_key'], $this->_has_many[$alias]['far_key']);
		$values  = array($this->pk(), $model->pk());

		if ($data !== NULL)
		{
			// Additional data stored in pivot table
			$columns = array_merge($columns, array_keys($data));
			$values  = array_merge($values, array_values($data));
		}

		DB::insert($this->_has_many[$alias]['through'])
			->columns($columns)
			->values($values)
			->execute($this->_db);

		return $this;
	}

	/**
	 * Removes a relationship between this model and another.
	 *
	 * @param   string   alias of the has_many "through" relationship
	 * @param   ORM      related ORM model
	 * @return  ORM
	 */
	public function remove($alias, ORM $model)
	{
		DB::delete($this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
			->where($this->_has_many[$alias]['far_key'], '=', $model->pk())
			->execute($this->_db);

		return $this;
	}

	/**
	 * Count the number of records in the table.
	 *
	 * @return  integer
	 */
	public function count_all()
	{
		$selects = array();

		foreach ($this->_db_pending as $key => $method)
		{
			if ($method['name'] == 'select')
			{
				// Ignore any selected columns for now
				$selects[] = $method;
				unset($this->_db_pending[$key]);
			}
		}

		$this->_build(Database::SELECT);

		$records = (int) $this->_db_builder->from($this->_table_name)
			->select(array('COUNT("*")', 'records_found'))
			->execute($this->_db)
			->get('records_found');

		// Add back in selected columns
		$this->_db_pending += $selects;

		$this->reset();

		// Return the total number of records in a table
		return $records;
	}

	/**
	 * Proxy method to Database list_columns.
	 *
	 * @return  array
	 */
	public function list_columns()
	{
		// Proxy to database
		return $this->_db->list_columns($this->_table_name);
	}

	/**
	 * Proxy method to Database field_data.
	 *
	 * @chainable
	 * @param   string  SQL query to clear
	 * @return  ORM
	 */
	public function clear_cache($sql = NULL)
	{
		// Proxy to database
		$this->_db->clear_cache($sql);

		ORM::$_column_cache = array();

		return $this;
	}

	/**
	 * Returns an ORM model for the given one-one related alias
	 *
	 * @param   string  alias name
	 * @return  ORM
	 */
	protected function _related($alias)
	{
		if (isset($this->_related[$alias]))
		{
			return $this->_related[$alias];
		}
		elseif (isset($this->_has_one[$alias]))
		{
			return $this->_related[$alias] = ORM::factory($this->_has_one[$alias]['model']);
		}
		elseif (isset($this->_belongs_to[$alias]))
		{
			return $this->_related[$alias] = ORM::factory($this->_belongs_to[$alias]['model']);
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Loads an array of values into into the current object.
	 *
	 * @chainable
	 * @param   array  values to load
	 * @return  ORM
	 */
	protected function _load_values(array $values)
	{
		if (array_key_exists($this->_primary_key, $values))
		{
			// Set the loaded and saved object status based on the primary key
			$this->_loaded = $this->_saved = ($values[$this->_primary_key] !== NULL);
		}

		// Related objects
		$related = array();

		foreach ($values as $column => $value)
		{
			if (strpos($column, ':') === FALSE)
			{
				if ( ! isset($this->_changed[$column]))
				{
					$this->_object[$column] = $value;
				}
			}
			else
			{
				list ($prefix, $column) = explode(':', $column, 2);

				$related[$prefix][$column] = $value;
			}
		}

		if ( ! empty($related))
		{
			foreach ($related as $object => $values)
			{
				// Load the related objects with the values in the result
				$this->_related($object)->_load_values($values);
			}
		}

		return $this;
	}

	/**
	 * Loads a database result, either as a new object for this model, or as
	 * an iterator for multiple rows.
	 *
	 * @chainable
	 * @param   boolean       return an iterator or load a single row
	 * @return  ORM           for single rows
	 * @return  ORM_Iterator  for multiple rows
	 */
	protected function _load_result($multiple = FALSE)
	{
		$this->_db_builder->from($this->_table_name);

		if ($multiple === FALSE)
		{
			// Only fetch 1 record
			$this->_db_builder->limit(1);
		}

		// Select all columns by default
		$this->_db_builder->select($this->_table_name.'.*');

		if ( ! isset($this->_db_applied['order_by']) AND ! empty($this->_sorting))
		{
			foreach ($this->_sorting as $column => $direction)
			{
				if (strpos($column, '.') === FALSE)
				{
					// Sorting column for use in JOINs
					$column = $this->_table_name.'.'.$column;
				}

				$this->_db_builder->order_by($column, $direction);
			}
		}

		if ($multiple === TRUE)
		{
			// Return database iterator casting to this object type
			$result = $this->_db_builder->as_object(get_class($this))->execute($this->_db);

			$this->reset();

			return $result;
		}
		else
		{
			// Load the result as an associative array
			$result = $this->_db_builder->as_assoc()->execute($this->_db);

			$this->reset();

			if ($result->count() === 1)
			{
				// Load object values
				$this->_load_values($result->current());
			}
			else
			{
				// Clear the object, nothing was found
				$this->clear();
			}

			return $this;
		}
	}

	/**
	 * Returns the value of the primary key
	 *
	 * @return  mixed  primary key
	 */
	public function pk()
	{
		return $this->_object[$this->_primary_key];
	}

	/**
	 * Returns whether or not primary key is empty
	 *
	 * @return  bool
	 */
	protected function empty_pk()
	{
		return (empty($this->_object[$this->_primary_key]) AND $this->_object[$this->_primary_key] !== '0');
	}

	/**
	 * Returns last executed query
	 *
	 * @return  string
	 */
	public function last_query()
	{
		return $this->_db->last_query;
	}

	/**
	 * Clears query builder.  Passing FALSE is useful to keep the existing
	 * query conditions for another query.
	 *
	 * @param  bool  Pass FALSE to avoid resetting on the next call
	 */
	public function reset($next = TRUE)
	{
		if ($next AND $this->_db_reset)
		{
			$this->_db_pending   = array();
			$this->_db_applied   = array();
			$this->_db_builder   = NULL;
			$this->_with_applied = array();
		}

		// Reset on the next call?
		$this->_db_reset = $next;

		return $this;
	}

} // End ORM
