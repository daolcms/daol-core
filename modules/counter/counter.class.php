<?php

/**
 * High class of counter module
 *
 * @author NAVER (developers@xpressengine.com)
 **/
class counter extends ModuleObject {

	/**
	 * Implement if additional tasks are necessary when installing
	 * @return BaseObject
	 **/
	function moduleInstall() {
		$oCounterController = &getController('counter');
		// add a row for the total visit history 
		//$oCounterController->insertTotalStatus();
		// add a row for today's status
		//$oCounterController->insertTodayStatus();

		return new BaseObject();
	}

	/**
	 * method if successfully installed
	 *
	 * @return bool
	 **/
	function checkUpdate() {
		// Add site_srl to the counter
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('counter_log', 'site_srl')) return true;
		if(!$oDB->isIndexExists('counter_log', 'idx_site_counter_log')) return true;

		return false;
	}

	/**
	 * Module update
	 *
	 * @return BaseObject
	 **/
	function moduleUpdate() {
		// Add site_srl to the counter
		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('counter_log', 'site_srl')) $oDB->addColumn('counter_log', 'site_srl', 'number', 11, 0, true);
		if(!$oDB->isIndexExists('counter_log', 'idx_site_counter_log')) $oDB->addIndex('counter_log', 'idx_site_counter_log', array('site_srl', 'ipaddress'), false);

		return new BaseObject(0, 'success_updated');
	}

	/**
	 * re-generate the cache file
	 *
	 * @return BaseObject
	 **/
	function recompileCache() {
	}
}
