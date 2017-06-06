<?php
/**
 * Created by PhpStorm.
 * User: rg12
 * Date: 02/05/2017
 * Time: 13:22
 */

namespace claremcr\clareevents\classes;


/** @class genericItem
 * @abstract Contains simply getter and setter functions
 * @description A superclass to provide getter and setter functions
 *  for subclasses
 */
class genericitem {

	protected $db;
	protected $my_pre;

	public function __construct() {
		$this->db     = new database();
		$this->my_pre = "mcrevents_";
	}

	/**
	 * getValue($val)
	 * Returns the value of the requested property.i
	 *
	 * @param string $val
	 *
	 * @return string $val
	 */
	function getValue( $val ) {
		//global $logger;
		//$logger->debug($val,get_object_vars ( $this ));
		return $this->$val;
	}

	/**
	 * setValue sets a property given a value and a varibale given in
	 * the arguments.
	 *
	 * @param string $val
	 * @param string $value
	 */
	function setValue( $val, $value ) {
		$this->$val = $value;
	}
}
