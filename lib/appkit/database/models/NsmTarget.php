<?php

/**
 * NsmTarget
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 6401 2009-09-24 16:12:04Z guilhermeblanco $
 */
class NsmTarget extends BaseNsmTarget
{

	const TYPE_DUMMY	= 'dummy';
	
	private $target_object = null;
	
	public function getTargetObject() {
		
		if ($this->target_class && class_exists($this->target_class)) {
			
			if ($this->target_object === null) {
				$this->target_object = AppKit::getInstance($this->target_class);
			}
			
			return $this->target_object;
			
		}
		
		throw new AppKitDoctrineException('Class %s for target not found!', $this->target_class);
	}
	
}