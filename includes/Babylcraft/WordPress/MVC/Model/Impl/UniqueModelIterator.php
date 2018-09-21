<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;


/**
 * Adds awareness of the IBabylonModel hierarchy to the built-in ArrayIterator. Also refuses to 
 * overwrite keys once they have been set -- throws FieldException in that case.
 * 
 * Attempting to add values that are not {@link IBabylonModel}s to this iterator via the constructor,
 * standard square-bracket array access operators or via append() will throw FieldException.
 * 
 * This iterator takes a "first-come-first-served" approach to type-checking, meaning that attempts
 * to add values that are of a different type to the type of the first value added will throw 
 * FieldException (whether that attempt is made via standard square-bracket array access or via append()).
 */
class UniqueModelIterator extends \ArrayIterator implements IUniqueModelIterator
{
	private $type;
	public function __construct(array $models = [])
	{
		parent::__construct($models);
		if (count($models)) {
			$this->setType(reset($models));
		}
	}
	
	/**
	 * @see IUniqueModelIterator::validValue()
	 */
	public function validValue(IBabylonModel $value) : bool
	{
		return $this->validateValue(
			$value,
			//validate all except NOT_FOUND because any value is valid if iterator is empty
			FieldException::ALL_VALIDATABLE & ~FieldException::ERR_NOT_FOUND,
			false //don't throw exceptions
		) === FieldException::NONE;
	}

	/**
	 * @see IUniqueModelIterator::validKey()
	 */
	public function validKey($key) : bool
	{
		return $this->validateKey($key, false); //don't throw exceptions
	}

	/**
	 * @see IUniqueModelIterator::validKVP()
	 */
	public function validKVP($key, IBabylonModel $value) : bool
	{
		return $this->validateKVP(
			$index,
			$value,
			//validate all except NOT_FOUND because any value is valid if iterator is empty
			FieldException::ALL_VALIDATABLE & ~FieldException::ERR_NOT_FOUND,
			false //don't throw exceptions
		) === FieldException::NONE;
	}
	
	public function offsetSet($index, $value)
	{
		if (!$this->type) {
			$this->setType($value);
		}

		$this->validateKVP($index, $value); //throws exception on error

		parent::offsetSet($index, $value);
	}

	public function append($value)
	{
		if (!$this->type) {
			$this->setType($value);
		}

		$this->validateValue($value); //throws exception on error

		parent::append($value);
	}
	
	private function setType(IBabylonModel $model)
	{
		$this->type = new \ReflectionClass($model);
	}

	private function validateKVP($index, IBabylonModel $value, int $forErrors = FieldException::ALL_VALIDATABLE, bool $throw = true) : int
	{
		return $this->validateKey($index, $throw) | $this->validateValue($value, $forErrors, $throw);
	}

	private function validateKey($key, bool $throw = true) : int
	{
		$errors = $this->offsetExists($key)
			? FieldException::ERR_UNIQUE_VIOLATION 
			: FieldException::NONE;
		
		if ($errors !== FieldException::NONE && $throw) {
			throw new FieldException($errors, "Validating index $index. ");
		}

		return $errors;
	}

	private function validateValue(IBabylonModel $value, int $forErrors = FieldException::ALL_VALIDATABLE, bool $throw = true) : int
	{
		$errors = FieldException::NONE;
		if ($forErrors & FieldException::ERR_NOT_FOUND !== 0) {
			$errors = !$this->type ? FieldException::ERR_NOT_FOUND : FieldException::NONE;
		}

		if ($errors === FieldException::NONE) {
			if ($forErrors & FieldException::ERR_WRONG_TYPE !== 0) {
				$errors |= ($this->type->getName() !== get_class($value)
					? FieldException::ERR_WRONG_TYPE
					: FieldException::NONE);
			}
		}
		
		if ($errors !== FieldException::NONE && $throw) {
			$message = "Validating value with type ". gettype($value) ." having class ". get_class($value);

			if ($this->type) {
				$message .= " in iterator with type ". $this->type->getName();
			} else {
				$message .= " in empty iterator";
			}

			throw new FieldException($errors, "$message. ");
		}

		return $errors;
	}

	//TODO Test - put this in a unit test
	//expect no error with empty array in constructor
	// $iter = new UniqueModelIterator([]);

	// //expect no error with appending Babylon object to empty array
	// $iter["one"] = new BabylonModel("Hello");

	// var_dump($iter->getArrayCopy());

	// //expect no error with array of Babylon objects in constructor
	// $iter = new UniqueModelIterator([
	// 	"one" => new BabylonModel("Hello1"),
	// 	"two" => new BabylonModel("Hello2")
	// ]);

	// //expect no error with appending Babylon object to array of Babylon objects
	// $iter["three"] = new BabylonModel("Hello3");

	// var_dump($iter->getArrayCopy());

	// //expect exception appending non-Babylon object to array of BabylonObjects
	// try {
	// 	echo "appending non-Babylon to array of Babylon: ";
	// 	$iter["four"] = "hello4";
	// } catch( FieldException $ex ) {
	// 	echo "exception: ";
	// 	echo $ex->getMessage();
	// }

	// //expect exception when array of non-Babylon objects in constructor
	// try {
	// 	echo "passing array of non-Babylon to constructor: ";
	// 	$iter = new UniqueModelIterator([
	// 		"one"   => "hello1",
	// 		"two"   => "hello2",
	// 		"three" => "hello3"
	// 	]);
	// } catch( FieldException $ex ) {
	// 	echo "exception: ";
	// 	echo $ex->getMessage();
	// }

	// //expect exception when appending non-Babylon object after empty array
	// try {
	// 	echo "appending non-Babylon after empty array: ";
	// 	$iter = new UniqueModelIterator([]);
	// 	$iter["one"] = "hello1";
	// } catch( FieldException $ex ) {
	// 	echo "exception: ";
	// 	echo $ex->getMessage();
	// }
}
