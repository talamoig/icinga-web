<?php

class IcingaServiceStateInfo extends IcingaStateInfo {
	
	/**
	 * List of status id's with corresponding
	 * status names
	 * 
	 * @var array
	 */
	protected $state_list = array (
		IcingaConstants::STATE_OK		=> 'OK',
		IcingaConstants::STATE_WARNING	=> 'WARNING',
		IcingaConstants::STATE_CRITICAL	=> 'CRITICAL',
		IcingaConstants::STATE_UNKNOWN	=> 'UNKNOWN',
	);

	protected $colors = array (
		IcingaConstants::STATE_OK		=> '00cc00',
		IcingaConstants::STATE_WARNING	=> 'ffff00',
		IcingaConstants::STATE_CRITICAL	=> 'ff0000',
		IcingaConstants::STATE_UNKNOWN	=> 'ff8000',
	);
	
	/**
	 * Shortcut to create an object instance on the fly
	 * 
	 * @param mixed $type
	 * @return IcingaHostStateInfo
	 */
	public function Create($type=99) {
		$class = __CLASS__;
		return new $class($type);
	}
	
}

?>