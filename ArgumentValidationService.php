<?php

namespace Your\Namespace;

/**
 * Provides a method of enforcing scalar parameter types in method calls.
 * Uses the doc block "param" comments to validate the parameter types.
 *
 * Usage:
 *
 * use Your\Namespace\ArgumentValidationService as Method;
 *
 * // call this in a method that provides "param" information in the method comment
 * Method::validateArguments();
 *
 * @since 1.0.0
 * @see http://bazookian.net/2015/03/05/a-cool-way-to-validate-your-typed-php-method-arguments-without-the-hassle/
 */
class ArgumentValidationService {
	/**
	 * Holds a map of scalar types to validate, and their aliases.
	 * @var array
	 */
	private static $scalarMap = array(
		'string' => 'string',
		'double' => 'double',
		'float' => 'double',
		'integer' => 'integer',
		'int' => 'integer',
		'boolean' => 'boolean',
		'bool' => 'boolean'
	);

	/**
	 * Analyzes the calling method's parameters and throws an error if the given values are not of the same (scalar) type.
	 *
	 * Must be called from within a class method, and the method must have documented its parameters correctly.
	 * Ignores any type that isn't a scalar.
	 *
	 * @since 1.0.0
	 */
	public static function validateArguments() {
		// fetch info about the calling class and method from the trace
		$trace = debug_backtrace()[1];
		$class = $trace['class'];
		$method = $trace['function'];
		$args = $trace['args'];

		if (!$class || !$method || empty($args)) {
			return;
		}

		// parse the doc block looking for parameter comments
		$params = self::getClassDocParameters($class, $method, $args);

		if (empty($params)) {
			return;
		}

		foreach ($params as $param) {
			//TODO: strings that are not null, cant be empty
			if ($param->expectedType != $param->actualType) {
				throw new \InvalidArgumentException('$' . "{$param->name} must be {$param->expectedType}, {$param->actualType} given.");
			}
		}
	}

	/**
	 * Looks at a class method doc block for parameter documentation and returns the documented type and name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class A class name where the method resides.
	 * @param string $method The method name of the class.
	 * @param array $inputArgs An array of the actual method argument values.
	 *
	 * @return array An array of parameter types and names, if documented.
	 */
	private static function getClassDocParameters($class, $method, array $inputArgs) {
		try {
			$method = new \ReflectionMethod($class, $method);
		} catch (\ReflectionException $e) {
			return array();
		}

		$doc = $method->getDocComment();
		$params = $method->getParameters();

		if (!$doc || empty($params)) {
			return array();
		}

		$params = array_map(function($p) { return $p->getName(); }, $params);

		// extract the info we need about param names and types
		preg_match_all(self::getRegex($params), $doc, $docParams, PREG_SET_ORDER);

		$docParams = array_map(function($p) use($params, $inputArgs) {
			$type = $p[1];
			$name = $p[2];
			$index = array_search($name, $params);

			// skip the parameter if there's no value given for it
			if ($index === false || !isset($inputArgs[$index])) {
				return null;
			}

			$expectedType = self::mapType($type);
			$actualType = gettype($inputArgs[$index]);

			return (object)array(
				'name' => $name,
				'expectedType' => $expectedType,
				'actualType' => $actualType
			);
		}, $docParams);

		return array_filter($docParams);
	}

	/**
	 * Translates a type alias to its fully qualified name.
	 *
	 * @since 1.0.0
	 * @see ArgumentValidationService::$scalarMap
	 *
	 * @param string $typeName The name or alias of a type.
	 * @return string The fully qualified type name.
	 */
	private static function mapType($typeName) {
		return isset(self::$scalarMap[$typeName]) ? self::$scalarMap[$typeName] : $typeName;
	}

	/**
	 * Constructs a regex for matching doc block parameters with scalar type hints.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params An array of parameter names to look for.
	 * @return string A regex that matches scalar typed parameter comments.
	 */
	private static function getRegex(array $params) {
		$scalars = implode('|', array_keys(self::$scalarMap));
		$params = implode('|', array_map(function($param) { return '\b' . $param . '\b'; }, $params));

		return '/@param\s+(' . $scalars . ')\s+\$(' . $params . ')/';
	}
}