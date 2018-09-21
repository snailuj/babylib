<?php
namespace Babylcraft\WordPress\MVC\Model;

use Babylcraft\WordPress\MVC\Model\IBabylonModel;


/**
 * Adds awareness of the IBabylonModel hierarchy to the built-in ArrayIterator. Also refuses to 
 * overwrite keys once they have been set.
 */
interface IUniqueModelIterator extends \ArrayAccess, \SeekableIterator, \Countable, \Serializable
{
	/**
	 * Returns true if the given value is valid according to the type of the other objects in this
	 * iterator.
	 * 
	 * @param IBabylonModel $value The model object to validate
	 * 
	 * @return bool
	 */
	function validValue(IBabylonModel $value) : bool;

	/**
	 * Returns true if the key does not already exist in this iterator, false otherwise.
	 * 
	 * @param mixed $key The key to check for existence
	 * 
	 * @return bool
	 */
	function validKey($key) : bool;

	/**
	 * Returns true if the key-value pair is valid for this iterator, or false if the value
	 * is of the wrong type or the key already exists.
	 * 
	 * @param mixed $key The key to check for existence
	 * @param IBabylonModel $value The model object to validate
	 * 
	 * @return bool
	 */
	function validKVP($key, IBabylonModel $value) : bool;
}
