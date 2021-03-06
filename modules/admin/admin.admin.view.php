<?php

/**
 * adminAdminView class
 * Admin view class of admin module
 *
 * @author  NAVER (developers@xpressengine.com)
 * @Adaptor DAOL Project (developer@daolcms.org)
 * @package /modules/admin
 * @version 0.1
 */
class adminAdminView extends admin {
	/**
	 * layout list
	 * @var array
	 */
	var $layout_list;
	/**
	 * easy install check file
	 * @var array
	 */
	var $easyinstallCheckFile = './files/env/easyinstall_last';

	/**
	 * Initilization
	 * @return void
	 */
	function init() {
		// forbit access if the user is not an administrator
		$oMemberModel = &getModel('member');
		$logged_info = $oMemberModel->getLoggedInfo();
		if($logged_info->is_admin != 'Y') return $this->stop("msg_is_not_administrator");

		// change into administration layout
		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setLayoutPath($this->getTemplatePath());
		$this->setLayoutFile('layout.html');

		$this->makeGnbUrl();

		// Retrieve the list of installed modules

		$db_info = Context::getDBInfo();

		Context::set('time_zone_list', $GLOBALS['time_zone']);
		Context::set('time_zone', $GLOBALS['_time_zone']);
		Context::set('use_rewrite', $db_info->use_rewrite == 'Y' ? 'Y' : 'N');
		Context::set('use_sso', $db_info->use_sso == 'Y' ? 'Y' : 'N');
		Context::set('use_html5', $db_info->use_html5 == 'Y' ? 'Y' : 'N');
		Context::set('use_spaceremover', $db_info->use_spaceremover ? $db_info->use_spaceremover : 'Y');//not use
		Context::set('qmail_compatibility', $db_info->qmail_compatibility == 'Y' ? 'Y' : 'N');
		Context::set('use_db_session', $db_info->use_db_session == 'N' ? 'N' : 'Y');
		Context::set('use_mobile_view', $db_info->use_mobile_view == 'Y' ? 'Y' : 'N');
		Context::set('use_ssl', $db_info->use_ssl ? $db_info->use_ssl : "none");
		Context::set('use_nofollow', $db_info->use_nofollow == 'Y' ? 'Y' : 'N');
		if($db_info->http_port) Context::set('http_port', $db_info->http_port);
		if($db_info->https_port) Context::set('https_port', $db_info->https_port);

		$this->checkEasyinstall();
	}

	/**
	 * check easy install
	 * @return void
	 */
	function checkEasyinstall() {
		$lastTime = (int)FileHandler::readFile($this->easyinstallCheckFile);
		if($lastTime > time() - 60 * 60 * 24 * 30) return;

		$oAutoinstallModel = &getModel('autoinstall');
		$params = array();
		$params["act"] = "getResourceapiLastupdate";
		$body = XmlGenerater::generate($params);
		$buff = FileHandler::getRemoteResource(_XE_DOWNLOAD_SERVER_, $body, 3, "POST", "application/xml");
		$xml_lUpdate = new XmlParser();
		$lUpdateDoc = $xml_lUpdate->parse($buff);
		$updateDate = $lUpdateDoc->response->updatedate->body;

		if(!$updateDate) {
			$this->_markingCheckEasyinstall();
			return;
		}

		$item = $oAutoinstallModel->getLatestPackage();
		if(!$item || $item->updatedate < $updateDate) {
			$oController = &getAdminController('autoinstall');
			$oController->_updateinfo();
		}
		$this->_markingCheckEasyinstall();
	}

	/**
	 * update easy install file content
	 * @return void
	 */
	function _markingCheckEasyinstall() {
		$currentTime = time();
		FileHandler::writeFile($this->easyinstallCheckFile, $currentTime);
	}

	/**
	 * Include admin menu php file and make menu url
	 * Setting admin logo, newest news setting
	 * @return void
	 */
	function makeGnbUrl($module = 'admin') {
		global $lang;

		$oAdminAdminModel = &getAdminModel('admin');
		$lang->menu_gnb_sub = $oAdminAdminModel->getAdminMenuLang();

		$oMenuAdminModel = &getAdminModel('menu');
		$menu_info = $oMenuAdminModel->getMenuByTitle('__XE_ADMIN__');
		Context::set('admin_menu_srl', $menu_info->menu_srl);

		if(!is_readable($menu_info->php_file)) return;

		include $menu_info->php_file;

		$oModuleModel = &getModel('module');
		$moduleActionInfo = $oModuleModel->getModuleActionXml($module);

		$currentAct = Context::get('act');
		$subMenuTitle = '';
		foreach((array)$moduleActionInfo->menu as $key => $value) {
			if(isset($value->acts) && is_array($value->acts) && in_array($currentAct, $value->acts)) {
				$subMenuTitle = $value->title;
				break;
			}
		}

		$parentSrl = 0;
		foreach((array)$menu->list as $parentKey => $parentMenu) {
			if(!is_array($parentMenu['list']) || !count($parentMenu['list'])) continue;
			if($parentMenu['href'] == '#' && count($parentMenu['list'])) {
				$firstChild = current($parentMenu['list']);
				$menu->list[$parentKey]['href'] = $firstChild['href'];
			}

			foreach($parentMenu['list'] as $childKey => $childMenu) {
				if($subMenuTitle == $childMenu['text']) {
					$parentSrl = $childMenu['parent_srl'];
					break;
				}
			}
		}

		// Admin logo, title setup
		$objConfig = $oModuleModel->getModuleConfig('admin');
		$gnbTitleInfo = new stdClass();
		$gnbTitleInfo->adminTitle = $objConfig->adminTitle ? $objConfig->adminTitle : 'DAOL CMS Admin';
		$gnbTitleInfo->adminLogo = $objConfig->adminLogo ? $objConfig->adminLogo : 'modules/admin/tpl/img/xe.h1.png';

		$browserTitle = ($subMenuTitle ? $subMenuTitle : 'Dashboard') . ' - ' . $gnbTitleInfo->adminTitle;

		// Get list of favorite
		$oAdminAdminModel = &getAdminModel('admin');
		$output = $oAdminAdminModel->getFavoriteList(0, true);
		Context::set('favorite_list', $output->get('favoriteList'));

		Context::set('subMenuTitle', $subMenuTitle);
		Context::set('gnbUrlList', $menu->list);
		Context::set('parentSrl', $parentSrl);
		Context::set('gnb_title_info', $gnbTitleInfo);
		Context::setBrowserTitle($browserTitle);
	}

	/**
	 * Display Super Admin Dashboard
	 * @return void
	 */
	function dispAdminIndex() {
		// Get statistics
		$args = new stdClass();
		$args->date = date("Ymd000000", time() - 60 * 60 * 24);
		$today = date("Ymd");

		// Member Status
		$oMemberAdminModel = &getAdminModel('member');
		$status = new stdClass();
		$status->member->todayCount = $oMemberAdminModel->getMemberCountByDate($today);
		$status->member->totalCount = $oMemberAdminModel->getMemberCountByDate();

		// Document Status
		$oDocumentAdminModel = &getAdminModel('document');
		$statusList = array('PUBLIC', 'SECRET');
		$status->document = new stdClass();
		$status->document->todayCount = $oDocumentAdminModel->getDocumentCountByDate($today, array(), $statusList);
		$status->document->totalCount = $oDocumentAdminModel->getDocumentCountByDate('', array(), $statusList);

		// Comment Status
		$oCommentModel = &getModel('comment');
		$status->comment = new stdClass();
		$status->comment->todayCount = $oCommentModel->getCommentCountByDate($today);
		$status->comment->totalCount = $oCommentModel->getCommentCountByDate();

		// Attached files Status
		$oFileAdminModel = &getAdminModel('file');
		$status->file = new stdClass();
		$status->file->todayCount = $oFileAdminModel->getFilesCountByDate($today);
		$status->file->totalCount = $oFileAdminModel->getFilesCountByDate();

		Context::set('status', $status);

		// Latest Document
		$oDocumentModel = &getModel('document');
		$columnList = array('document_srl', 'module_srl', 'category_srl', 'title', 'nick_name', 'member_srl');
		$args = new stdClass();
		$args->list_count = 5;;
		$output = $oDocumentModel->getDocumentList($args, false, false, $columnList);
		Context::set('latestDocumentList', $output->data);
		unset($args, $output, $columnList);

		// Latest Comment
		$oCommentModel = &getModel('comment');
		$columnList = array('comment_srl', 'module_srl', 'document_srl', 'content', 'nick_name', 'member_srl');
		$args->list_count = 5;
		$output = $oCommentModel->getNewestCommentList($args, $columnList);
		if(is_array($output)) {
			foreach($output AS $key => $value) {
				$value->content = strip_tags($value->content);
			}
		}
		Context::set('latestCommentList', $output);
		unset($args, $output, $columnList);

		// Get list of modules
		$oModuleModel = &getModel('module');
		$module_list = $oModuleModel->getModuleList();
		if(is_array($module_list)) {
			$needUpdate = false;
			$addTables = false;
			foreach($module_list AS $key => $value) {
				if($value->need_install) {
					$addTables = true;
				}
				if($value->need_update) {
					$needUpdate = true;
				}
			}
		}
		Context::set('module_list', $module_list);
		Context::set('needUpdate', $isUpdated);
		Context::set('addTables', $addTables);
		Context::set('needUpdate', $needUpdate);

		$oSecurity = new Security();
		$oSecurity->encodeHTML('module_list..', 'module_list..author..', 'newVersionList..');

		// license agreement check
		$isLicenseAgreement = FALSE;
		$path = FileHandler::getRealPath('./files/env/license_agreement');
		$isLicenseAgreement = FALSE;
		if(file_exists($path)) $isLicenseAgreement = TRUE;
		Context::set('isLicenseAgreement', $isLicenseAgreement);

		Context::set('layout', 'none');

		$this->setTemplateFile('index');
	}

	/**
	 * Display Configuration(settings) page
	 * @return void
	 */
	function dispAdminConfigGeneral() {
		Context::loadLang('modules/install/lang');

		$db_info = Context::getDBInfo();

		Context::set('selected_lang', $db_info->lang_type);

		Context::set('default_url', $db_info->default_url);
		Context::set('langs', Context::loadLangSupported());

		Context::set('lang_selected', Context::loadLangSelected());

		$admin_ip_list = implode("\r\n", $db_info->admin_ip_list);
		Context::set('admin_ip_list', $admin_ip_list);

		$oAdminModel = getAdminModel('admin');
		$favicon_url = $oAdminModel->getFaviconUrl();
		$mobicon_url = $oAdminModel->getMobileIconUrl();
		Context::set('favicon_url', $favicon_url);
		Context::set('mobicon_url', $mobicon_url);

		$oDocumentModel = getModel('document');
		$config = $oDocumentModel->getDocumentConfig();
		Context::set('thumbnail_type', $config->thumbnail_type);

		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('module');
		Context::set('siteTitle', $config->siteTitle);
		Context::set('htmlFooter', htmlspecialchars($config->htmlFooter));

		Context::set('IP', $_SERVER['REMOTE_ADDR']);

		$columnList = array('modules.mid', 'modules.browser_title', 'sites.index_module_srl');
		$start_module = $oModuleModel->getSiteInfo(0, $columnList);
		Context::set('start_module', $start_module);

		Context::set('pwd', $pwd);
		$this->setTemplateFile('config_general');

		$security = new Security();
		$security->encodeHTML('news..', 'released_version', 'download_link', 'selected_lang', 'module_list..', 'module_list..author..', 'addon_list..', 'addon_list..author..', 'start_module.');
	}

	/**
	 * Display CDN Configuration(settings) page
	 * @return void
	 */
	function dispAdminConfigCDN() {
		Context::loadLang('modules/install/lang');

		$cdn_info = Context::getCDNInfo();
		Context::set('cdn_info', $cdn_info);

		$this->setTemplateFile('config_cdn');
	}

	/**
	 * Display FTP Configuration(settings) page
	 * @return void
	 */
	function dispAdminConfigFtp() {
		Context::loadLang('modules/install/lang');

		$ftp_info = Context::getFTPInfo();
		Context::set('ftp_info', $ftp_info);
		Context::set('sftp_support', function_exists(ssh2_sftp));

		$this->setTemplateFile('config_ftp');

		//$security = new Security();
		//$security->encodeHTML('ftp_info..');

	}

	/**
	 * Display SMTP Configuration(settings) page
	 * @return void
	 */
	function dispAdminConfigSMTP() {
		Context::loadLang('modules/install/lang');

		$smtp_info = Context::getSMTPInfo();
		Context::set('smtp_info', $smtp_info);

		$this->setTemplateFile('config_smtp');
	}

	/**
	 * Display Admin Menu Configuration(settings) page
	 * @return void
	 */
	function dispAdminSetup() {
		$oModuleModel = &getModel('module');
		$configObject = $oModuleModel->getModuleConfig('admin');

		$oMenuAdminModel = &getAdminModel('menu');
		$output = $oMenuAdminModel->getMenuByTitle('__XE_ADMIN__');

		Context::set('menu_srl', $output->menu_srl);
		Context::set('menu_title', $output->title);
		Context::set('config_object', $configObject);
		$this->setTemplateFile('admin_setup');
	}

	/**
	 * Display Admin theme Configuration(settings) page
	 * @return void
	 */
	function dispAdminTheme() {
		// choice theme file
		$theme_file = _DAOL_PATH_ . 'files/theme/theme_info.php';
		if(is_readable($theme_file)) {
			@include($theme_file);
			Context::set('current_layout', $theme_info->layout);
			Context::set('theme_info', $theme_info);
		} else {
			$oModuleModel = &getModel('module');
			$default_mid = $oModuleModel->getDefaultMid();
			Context::set('current_layout', $default_mid->layout_srl);
		}

		// layout list
		$oLayoutModel = &getModel('layout');
		// theme 정보 읽기

		$oAdminModel = &getAdminModel('admin');
		$theme_list = $oAdminModel->getThemeList();
		$layouts = $oLayoutModel->getLayoutList(0);
		$layout_list = array();
		if(is_array($layouts)) {
			foreach($layouts as $val) {
				unset($layout_info);
				$layout_info = $oLayoutModel->getLayout($val->layout_srl);
				if(!$layout_info) continue;
				$layout_parse = explode('|@|', $layout_info->layout);
				if(count($layout_parse) == 2) {
					$thumb_path = sprintf('./themes/%s/layouts/%s/thumbnail.png', $layout_parse[0], $layout_parse[1]);
				} else {
					$thumb_path = './layouts/' . $layout_info->layout . '/thumbnail.png';
				}
				$layout_info->thumbnail = (is_readable($thumb_path)) ? $thumb_path : null;
				$layout_list[] = $layout_info;
			}
		}
		Context::set('theme_list', $theme_list);
		Context::set('layout_list', $layout_list);

		// 설치된module 정보 가져오기
		$module_list = $oAdminModel->getModulesSkinList();
		Context::set('module_list', $module_list);

		$this->setTemplateFile('theme');
	}

	/**
	 * Retrun server environment to XML string
	 * @return object
	 */
	function dispAdminViewServerEnv() {
		$info = array();

		$oAdminModel = getAdminModel('admin');
		$envInfo = $oAdminModel->getEnv();
		$tmp = explode("&", $envInfo);
		$arrInfo = array();
		$xe_check_env = array();
		foreach($tmp as $value) {
			$arr = explode("=", $value);
			if($arr[0] == "type") {
				continue;
			} elseif($arr[0] == "phpext") {
				$str = urldecode($arr[1]);
				$xe_check_env[$arr[0]] = str_replace("|", ", ", $str);
			} elseif($arr[0] == "module") {
				$str = urldecode($arr[1]);
				$arrModuleName = explode("|", $str);
				$oModuleModel = getModel("module");
				$mInfo = array();
				foreach($arrModuleName as $moduleName) {
					$moduleInfo = $oModuleModel->getModuleInfoXml($moduleName);
					$mInfo[] = "{$moduleName}({$moduleInfo->version})";
				}
				$xe_check_env[$arr[0]] = join(", ", $mInfo);
			} elseif($arr[0] == "addon") {
				$str = urldecode($arr[1]);
				$arrAddonName = explode("|", $str);
				$oAddonModel = getAdminModel("addon");
				$mInfo = array();
				foreach($arrAddonName as $addonName) {
					$addonInfo = $oAddonModel->getAddonInfoXml($addonName);
					$mInfo[] = "{$addonName}({$addonInfo->version})";
				}
				$xe_check_env[$arr[0]] = join(", ", $mInfo);
			} elseif($arr[0] == "widget") {
				$str = urldecode($arr[1]);
				$arrWidgetName = explode("|", $str);
				$oWidgetModel = getModel("widget");
				$mInfo = array();
				foreach($arrWidgetName as $widgetName) {
					$widgetInfo = $oWidgetModel->getWidgetInfo($widgetName);
					$mInfo[] = "{$widgetName}({$widgetInfo->version})";
				}
				$xe_check_env[$arr[0]] = join(", ", $mInfo);
			} elseif($arr[0] == "widgetstyle") {
				$str = urldecode($arr[1]);
				$arrWidgetstyleName = explode("|", $str);
				$oWidgetModel = getModel("widget");
				$mInfo = array();
				foreach($arrWidgetstyleName as $widgetstyleName) {
					$widgetstyleInfo = $oWidgetModel->getWidgetStyleInfo($widgetstyleName);
					$mInfo[] = "{$widgetstyleName}({$widgetstyleInfo->version})";
				}
				$xe_check_env[$arr[0]] = join(", ", $mInfo);

			} elseif($arr[0] == "layout") {
				$str = urldecode($arr[1]);
				$arrLayoutName = explode("|", $str);
				$oLayoutModel = getModel("layout");
				$mInfo = array();
				foreach($arrLayoutName as $layoutName) {
					$layoutInfo = $oLayoutModel->getLayoutInfo($layoutName);
					$mInfo[] = "{$layoutName}({$layoutInfo->version})";
				}
				$xe_check_env[$arr[0]] = join(", ", $mInfo);
			} else {
				$xe_check_env[$arr[0]] = urldecode($arr[1]);
			}
		}
		$info['XE_Check_Evn'] = $xe_check_env;

		$ini_info = ini_get_all();
		$php_core = array();
		$php_core['max_file_uploads'] = "{$ini_info['max_file_uploads']['local_value']}";
		$php_core['post_max_size'] = "{$ini_info['post_max_size']['local_value']}";
		$php_core['memory_limit'] = "{$ini_info['memory_limit']['local_value']}";
		$info['PHP_Core'] = $php_core;

		$str_info = "[DAOL Server Environment " . date("Y-m-d") . "]\n\n";
		$str_info .= "realpath : " . realpath('./') . "\n";
		foreach($info as $key => $value) {
			if(is_array($value) == false) {
				$str_info .= "{$key} : {$value}\n";
			} else {
				//$str_info .= "\n{$key} \n";
				foreach($value as $key2 => $value2)
					$str_info .= "{$key2} : {$value2}\n";
			}
		}

		Context::set('str_info', $str_info);
		$this->setTemplateFile('server_env.html');
	}
}
