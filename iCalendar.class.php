<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
// --- iCalReader //
if (!class_exists('ICalReader')) {	include_file('3rdparty', 'class.iCalReader', 'php', 'iCalendar'); }
// --- SimpleCalDAVClient //
if (!class_exists('CalDAVCalendar')) { include_file('3rdparty', 'class.SimpleCalDAV/CalDAVCalendar', 'php', 'iCalendar'); }
if (!class_exists('AWLUtilities')) { include_file('3rdparty', 'class.SimpleCalDAV/include/AWLUtilities', 'php', 'iCalendar'); }
if (!class_exists('XMLElement')) { include_file('3rdparty', 'class.SimpleCalDAV/include/XMLElement', 'php', 'iCalendar'); }
if (!class_exists('XMLDocument')) { include_file('3rdparty', 'class.SimpleCalDAV/include/XMLDocument', 'php', 'iCalendar'); }
if (!class_exists('CalDAVClientICal')) { include_file('3rdparty', 'class.SimpleCalDAV/CalDAVClientICal', 'php', 'iCalendar'); }
if (!class_exists('CalDAVException')) {	include_file('3rdparty', 'class.SimpleCalDAV/CalDAVException', 'php', 'iCalendar'); }
if (!class_exists('CalDAVFilter')) { include_file('3rdparty', 'class.SimpleCalDAV/CalDAVFilter', 'php', 'iCalendar'); }
if (!class_exists('CalDAVObject')) { include_file('3rdparty', 'class.SimpleCalDAV/CalDAVObject', 'php', 'iCalendar'); }
if (!class_exists('SimpleCalDAVClient')) {	include_file('3rdparty', 'class.SimpleCalDAV/SimpleCalDAVClient', 'php', 'iCalendar'); }
// chargement des infos pour la class "olindoteTools" //
require_once dirname(__FILE__) . '/../../3rdparty/olindote/Tools/olindoteTools.inc.php';

// Définie le chemin des fichiers caches pour iCalendar //
if (!defined('ICALENDAR_CACHE_PATH')) { define('ICALENDAR_CACHE_PATH', '/tmp/iCalendar/'); }
// Définie le texte par défaut à afficher (cas d'erreur) //
if (!defined('ICALENDAR_TXT_DV')) { define('ICALENDAR_TXT_DV', __('Aucun', __FILE__)); }


/**
 * Class Extend eqLogic pour le plugin iCalendar
 * @author abarrau
 */
class iCalendar extends eqLogic {
	/*     * ************************* Attributs ****************************** */
	public $_log; 
	public $_logFN = '';
	public $_isSimpleSave = false;
	public $_errorNetwork = false;
	public $_aoICal = array();
	public $_whatLog = ''; 
	public $_tsRef;
	public $_aEventsActionsList = array();
	public $_aEventActionsState = array();
	public static $_widgetPossibility = array('custom' => array(
		'visibility' => array('dashboard'=>true,'plan'=>true,'view'=>true,'mobile'=>true),
		'displayName' => array('dashboard'=>true,'plan'=>true,'view'=>true,'mobile'=>true),
		'displayObjectName' => array('dashboard'=>true,'plan'=>true,'view'=>true,'mobile'=>true),
		'background-color' => array('dashboard'=>true,'plan'=>true,'view'=>true,'mobile'=>true),
		'background-opacity' => array('dashboard'=>true,'plan'=>true,'view'=>true,'mobile'=>true),
		'text-color' => array('dashboard'=>true,'plan'=>true,'view'=>true,'mobile'=>true),
		'border' => false,
		'border-radius' => array('dashboard'=>true,'plan'=>true,'view'=>true,'mobile'=>true),
		'optionalParameters' => true
	));

	/*     * *********************** Methodes JEEDOM *************************** */

	/**
	 * Fonction utilisée à l'initiation de la class
	 * @return void
	 */
	public function __construct() {
		$this->_log = new log();
		$this->_tsRef = time();
	}
	
	/**
	 * Fonction d'execution principale appelée via CRON, lance l'excution de la commande et la mise à jour du widget
	 * @return void
	 */
	public static function cron() {
		$_sTs=getmicrotime();
		$ICAL_AEQL = self::byType('iCalendar');
		log::add('iCalendar', 'debug', '[CRON START]===== cron().nb iCalendar=' . count($ICAL_AEQL));
		$_now = time();
		$_nbSec = date('s',$_now);
		foreach ($ICAL_AEQL as $iCalendar) {
			$iCalendar->tsRef = $_now;
			$iCalendar->_aEventsActionsList = array();
			$iCalendar->_whatLog = $_whatLog = 'CRON';
			//DEL//$_bDoRefresh = FALSE; // mettre TRUE pour forcer la remise du cache du widget //
			$_aExecCmd = array();
			if ($iCalendar->getIsEnable()) {
				$ICAL_ACMD = $iCalendar->getCmd('info');
				$_dt = array('s'=>intval($_now-$_nbSec),'e'=>intval($_now-$_nbSec+60));
				if ($iCalendar->getConfiguration('catchAccepted','0')=='1') {
					$_catchPeriod = $iCalendar->getConfiguration('catchPeriod','2')*60;
					$_dt['s'] = intval($_dt['s']-$_catchPeriod);
				}
				$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cron().nb cmd=' . count($ICAL_ACMD));
				foreach ($ICAL_ACMD as $_oCmd) {
					$_oCmd->_whatLog = $_whatLog;
					$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '|' . $_oCmd->getId() . '] cron(): do event() !');
					$_resExec = trim($_oCmd->execute());
					$_oCmd->event($_resExec);
					//DEL//$_bDoRefresh = true;
					/* // 1.3.0: simplifie ce traitement, car déjà fait dans le "event()" du core ... //
					$_oCmd->_sExecCmdPrevious = $_oCmd->execCmd();
					$_resExec = trim($_oCmd->execute());
					//$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cron()._resExec='.$_resExec);
					if ($_resExec != $_oCmd->_sExecCmdPrevious) {
						$iCalendar->_log->add($iCalendar->logFN(), 'info', '['.$_whatLog.'|' . $iCalendar->getId() . '|' . $_oCmd->getId() . '] cron() '.__('Données mise à jour, envoi de l\'évènement...', __FILE__));
						$_oCmd->setCollectDate(date('Y-m-d H:i:s'));
						$_oCmd->event($_resExec);
						$_bDoRefresh = true;
					}
					*/
					// lancement des actions (action, scénario, interaction) //
					if ((substr($_oCmd->getLogicalId(),-2)=='J0')
						&& ((strpos($_resExec,';doAct')!==false)||(strpos($_resExec,';doInter')!==false))) { 
						if ((($_oCmd->getConfiguration('acceptLaunchActSc','0')=='1') && ($_oCmd->getConfiguration('indicDebFin',0)==1)) 
							|| ($_oCmd->getConfiguration('acceptLaunchInteract','0')=='1')) {
							// récupère les actions en cache //
							$_aActions = $iCalendar->getCacheEventActionsList(array('idCmd'=>$_oCmd->getId()), true);
							foreach($_aActions as $_ts => $_aOneAction) {
								if (!isset($_aOneAction['tsExec']) && ($_dt['s']<=$_aOneAction['tsWhen']) && ($_aOneAction['tsWhen']<$_dt['e'])) {
									$_aOneEvent = iCalendarTools::getEventByUid($_resExec, $_aOneAction['uid']);
									$iCalendar->_log->add($iCalendar->logFN(), 'debug', '[' . $iCalendar->getId() . '|' . $_oCmd->getId() . '] cron() do action/scenario/interaction for event: "'.$_aOneEvent['t'].'" ('.$_aOneEvent['u'].')');
									if ($_aOneAction['t']=='A') {
										$iCalendar->launchActionFromEvent($_aOneAction, $_aOneEvent, $_oCmd->getId());
										//DEL//$_bDoRefresh = true;
									} elseif($_aOneAction['t']=='S') {
										$iCalendar->launchScenarioFromEvent($_aOneAction, $_aOneEvent, $_oCmd->getId());
										//DEL//$_bDoRefresh = true;
									} elseif($_aOneAction['t']=='I') {
										$iCalendar->launchInteractFromEvent($_aOneAction, $_aOneEvent, $_oCmd->getId());
										//DEL//$_bDoRefresh = true;
									} else {
										$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '|' . $_oCmd->getId() . '] cron() ERROR : type action not exist for event:'.$_aOneEvent['u'].' --- '.print_r($_aOneAction,true));
									}
								}
							}
                        }
					}
				}
				//DEL//if (cache::byKey('iCalendarWidgetdashboard'.$iCalendar->getId())->getValue()=='') {
				//DEL//	$_bDoRefresh = true;
				//DEL//}
				// mise à jour du widget //
				if ($iCalendar->getConfiguration('widgetOther') != '1'){
					if ($iCalendar->getIsVisible()) { //DEL// && (($_bDoRefresh) || (date('H:i') === '00:00'))) {
						$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cron() remove cache and refreshWidget ...');
						$iCalendar->refreshWidget();
					}
				}
			}
		}
		log::add('iCalendar', 'debug', '[CRON END]=====('.round((getmicrotime()-$_sTs),3).')');
	}
  /**
	 * Fonction d'execution appelée via CRON5, lance la récupération du fichier 
	 * @return void
	 */
	public static function cron5() {
		$_sTs=getmicrotime();
		$ICAL_AEQL = self::byType('iCalendar');
		log::add('iCalendar', 'debug', '[CRON5 START]===== cron5().nb iCalendar=' . count($ICAL_AEQL));
		foreach ($ICAL_AEQL as $iCalendar) {
			$iCalendar->_whatLog = $_whatLog = 'CRON5';
			if ($iCalendar->getIsEnable()) {
				$ICAL_ACMD = $iCalendar->getCmd('info');
				$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cron5().nb cmd=' . count($ICAL_ACMD));
				foreach ($ICAL_ACMD as $_oCmd) {
					$_oCmd->_whatLog = $_whatLog;
					if (substr($_oCmd->getLogicalId(),-2)=='J0') {
                      $_dateSyncNext = $_oCmd->getConfiguration('dateSyncNext',0);
                      log::add('iCalendar', 'debug', 'TESTCRON '.$_dateSyncNext);
						// traitement sur le flux, si la date de synchro correspond //
						//if (($_dateSyncNext = $_oCmd->getConfiguration('dateSyncNext',0))!='STOP') {
							// réalise l'action si la date est inférieure //
							//if (olindoteToolsICAL::convertDate($_dateSyncNext,'ONLYNUMBER') <= date('YmdHis')) {
								$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cron5() LogicalId='.$_oCmd->getLogicalId());
								$_oCmd->manageIcsFile();
								$_oCmd->setCacheRange();
							//}
						//}
					}
				}
			}
		}
		log::add('iCalendar', 'debug', '[CRON5 END]=====('.round((getmicrotime()-$_sTs),3).')');
	}
	/**
	 * Fonction d'execution appelée via CRON30, lance la récupération du fichier 
	 * @return void
	 */
	public static function cron30() {
		$_sTs=getmicrotime();
		$ICAL_AEQL = self::byType('iCalendar');
		log::add('iCalendar', 'debug', '[CRON30 START]===== cron30().nb iCalendar=' . count($ICAL_AEQL));
		foreach ($ICAL_AEQL as $iCalendar) {
			$iCalendar->_whatLog = $_whatLog = 'CRON30';
			if ($iCalendar->getIsEnable()) {
				$ICAL_ACMD = $iCalendar->getCmd('info');
				$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cron30().nb cmd=' . count($ICAL_ACMD));
				foreach ($ICAL_ACMD as $_oCmd) {
					$_oCmd->_whatLog = $_whatLog;
					if (substr($_oCmd->getLogicalId(),-2)=='J0') {
						// traitement sur le flux, si la date de synchro correspond //
						if (($_dateSyncNext = $_oCmd->getConfiguration('dateSyncNext',0))!='STOP') {
							// réalise l'action si la date est inférieure //
							if (olindoteToolsICAL::convertDate($_dateSyncNext,'ONLYNUMBER') <= date('YmdHis')) {
								$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cron30() LogicalId='.$_oCmd->getLogicalId());
								$_oCmd->manageIcsFile();
								$_oCmd->setCacheRange();
							}
						}
					}
				}
			}
		}
		log::add('iCalendar', 'debug', '[CRON30 END]=====('.round((getmicrotime()-$_sTs),3).')');
	}
	
	/**
	 * Utiliser pour pousser des informations entre chaque mise à jour
	 * @return void
	 */
	public static function cronDaily() {
		$_sTs=getmicrotime();
		$ICAL_AEQL = self::byType('iCalendar');
		log::add('iCalendar', 'debug', '[CRONDAILY START]===== cronDaily().nb iCalendar=' . count($ICAL_AEQL));
		// traitement temporaire (à supprimer quand il n'y a plus d'intéret) //
		self::cheackInfo();
		// traitement normal //
		foreach ($ICAL_AEQL as $iCalendar) {
			$iCalendar->_whatLog = $_whatLog = 'CRONDAILY';
			if ($iCalendar->getIsEnable()) {
				$ICAL_ACMD = $iCalendar->getCmd('info');
				$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|' . $iCalendar->getId() . '] cronDaily().nb cmd=' . count($ICAL_ACMD));
				$_aEventActionsState = array();
				foreach ($ICAL_ACMD as $_oCmd) {
					// gestion des actions (historique) //
					$_aEventActionsState = json_decode(cache::byKey('iCalendar::'.$_oCmd->getId().'::EventActionsState')->getValue(),true);
					if (count($_aEventActionsState)>0) {
						foreach($_aEventActionsState as $_ts => $_actions) {
							if (($_ts < date('Ymd',$iCalendar->_tsRef))&&(count($_actions)>0)) {
								$_day = olindoteToolsICAL::convertDate($_ts, 'DAYJEEDOM');
								$_error = false;
								if ($_oCmd->getConfiguration('actionIsHistorized','0')=='1') {
									$_actionsForAdd = array();
									foreach($_actions as $_k => $_v) {
										$_actionsForAdd[$_v['tsExec']] = $_v;
										$_actionsForAdd[$_v['tsExec']]['timeH']=date('H:i:s',$_v['tsExec']);
									}
									ksort($_actionsForAdd);
									if ($_oCmd->addHistoryAction(json_encode($_actionsForAdd), $_day.' 00:00:00')===false) {
										$iCalendar->_log->add($iCalendar->logFN(), 'info', '['.$_whatLog.'|'.$iCalendar->getId().'|'.$_oCmd->getId().'] cronDaily(): ERROR: '.__('L\'historisation des actions n\'a pas put être réalisée pour la journée',__FILE__).': '.$_day);
										$_error = true;
									} else {
										$iCalendar->_log->add($iCalendar->logFN(), 'info', '['.$_whatLog.'|'.$iCalendar->getId().'|'.$_oCmd->getId().'] cronDaily(): '.__('Statut des actions historisés pour la journée du ',__FILE__).$_day);
									}
								}
								if (!$_error) {
									unset($_aEventActionsState[$_ts]);
									$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|'.$iCalendar->getId().'|'.$_oCmd->getId().'] cronDaily(): actions was delete for day: '.$_day);
								}
							}
						}
						$_aEventActionsState = (count($_aEventActionsState)==0)?'':json_encode($_aEventActionsState);
						cache::set('iCalendar::'.$_oCmd->getId().'::EventActionsState', $_aEventActionsState, 0);
						$iCalendar->_log->add($iCalendar->logFN(), 'debug', '['.$_whatLog.'|'.$iCalendar->getId().'|'.$_oCmd->getId().'] cronDaily(): update new EventActionsState');
					}
					// gestion du cache du calendrier //
					$_oCmd->getEventsInCalendar(array('dStart'=>date('U', strtotime('-3 months')), 'dEnd'=>date('U', strtotime('+6 months'))));
				}
				
			}
		}
		log::add('iCalendar', 'debug', '[CRONDAILY END]=====('.round((getmicrotime()-$_sTs),3).')');
	}
	
	/**
	 * Retourne les informations pour la page "santé"
	 * @return array()
	 */
	public function health() {
		$_resAll = array();
		foreach (self::byType('iCalendar') as $_oICAL) {
			if ($_oICAL->getIsEnable()) {
				foreach ($_oICAL->getCmd('info') as $_oCmd) {
					if (substr($_oCmd->getLogicalId(),-2)=='J0') {
						$_aRes = olindoteToolsICAL::setArrayHealthNetworkForHealthPage($_oCmd, 'iCalendar');
						if ($_aRes != false) $_resAll[] = $_aRes;
					}
				}
			}
		}
		return $_resAll;
	}

	/**
	 * Traitement des actions au moment de l'enregistrement de l'objet plugin
	 * @return void
	 */
	public function preSave() {
		if ($this->getId() > 0) {
			$this->_whatLog = 'SAVE';
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getId().'] SAVE START // eqLogic.preSave(): "'.$this->getName().'"');
			// création du repertoire cache s'il n'existe pas //
			iCalendarTools::setCacheDir(true);
		}
	 }
	 
	/**
	 * Traitement des actions au moment de l'enregistrement de l'objet plugin
	 * @return void
	 */
	public function postSave() {
		if ($this->getId() > 0) {
			$this->emptyCacheWidget();
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getId().'] SAVE END // eqLogic.postSave()');
		}
	}
	
	/**
	 * Copie l'objet (equipement et commandes)
	 * @param string $_name nom du nouvel équipement
	 * @return string
	 */
	public function copy($_name) {
		$this->_whatLog = 'DUPLICATE';
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getId().'] Duplicate eqLogic/cmd starting: "'.$this->getName().'" in "'.$_name.'"');
		$eqLogicCopy = clone $this;
		$eqLogicCopy->setName($_name);
		$eqLogicCopy->setId('');
		$eqLogicCopy->setIsEnable(0);
		$eqLogicCopy->setIsVisible(0);
		$eqLogicCopy->save();
		foreach ($this->getCmd() as $_oCmd) {
			if (substr($_oCmd->getLogicalId(),-2)=='J0') {
				$cmdCopy = clone $_oCmd;
				$cmdCopy->setId('');
				$cmdCopy->setEqLogic_id($eqLogicCopy->getId());
				$cmdCopy->setLogicalId('iCal-'.$eqLogicCopy->getId().'-J0');
				$cmdCopy->setConfiguration('dateSyncLast','');
				$cmdCopy->setConfiguration('dateSyncNext','');
				$cmdCopy->setConfiguration('dateSaveFile','');
				$cmdCopy->setConfiguration('healthNetworkLastDate','');
				$cmdCopy->setConfiguration('healthNetwork',array());
				$cmdCopy->save();
			}
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getId().'] DUPLICATE eqLogic/cmd is finish.');
		return $eqLogicCopy;
	}
	
	/**
	 * Format le Widget "iCalendar"
	 * @return void
	 */
	public function toHtml($_version = 'dashboard') {
		$_sTs=getmicrotime();
		if ($this->_whatLog=='') $this->_whatLog='DASH';
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] toHtml(' . $_version . ') start ...');
		// utilisation du widget "standard" jeedom //
		if ($this->getConfiguration('widgetOther') == '1'){
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] toHtml(' . $_version . ') use jeedom widget (no plugin widget).');
			return parent::toHtml($_version);
		}
		// pré-traitement des données Jeedom //
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
/*      $parameters = $this->getDisplay('parameters');
        if (is_array($parameters)) {
            foreach ($parameters as $key => $value) {
                $replace['#' . $key . '#'] = $value;
            }
        }
*/		// utilisation du widget du plugin //
		$_version = jeedom::versionAlias($_version);
		//$this->emptyCacheWidget();
		$_wc = cache::byKey('iCalendarWidget' . $_version . $this->getId());
		if ($_wc->getValue() != '') {
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] toHtml(' . $_version . ') aborded !');
			return $_wc->getValue();
		}
		if ($_version == 'mobile') {
			$replace['#today#'] = ($this->getConfiguration('hideDateMobile',0)==1)?'':date('d');
		} else {
			$replace['#today#'] = ($this->getConfiguration('hideDateDashboard',0)==1)?'':__("aujourd'hui", __FILE__).' : '.olindoteToolsICAL::convertDate('now','TODAY');
		}
		$replace['#allowAutoResize#'] = ($this->getConfiguration('allowAutoResize',0)==1)?1:0;
		if ($_version != 'mobile') { 
			$replace['#txtCollecteDate#'] = __("collectée le", __FILE__);
			$replace['#txtValueDate#'] = __("valeur du", __FILE__);
			$replace['#txtView#'] = __("affichage", __FILE__);
			$replace['#txtGotoAG#'] = __("voir Agenda Google", __FILE__);
			$replace['#txtScenario#'] = __("Scénario", __FILE__);
			$replace['#txtAction#'] = __("Action", __FILE__);
			$replace['#txtNotActSc#'] = __("Action/Scénario désactivés", __FILE__);
			$replace['#txtExecTS#'] = __("Action traitée à", __FILE__);
			$replace['#txtErrorNotMinHeight#'] = __("Pour ne pas perturber l'affichage des données, la hauteur du widget ne peut pas être inférieure.", __FILE__);
			$replace['#txtErrorNotMinWidth#'] = __("Pour ne pas perturber l'affichage des données, la largeur du widget ne peut pas être inférieure.", __FILE__);
			$replace['#txtEventActif#'] = __('évènement actif', __FILE__);
			$replace['#txtFirstMin#'] = __('1ère minute', __FILE__);
			$replace['#txtLastMin#'] = __('dernière minute', __FILE__);
			$replace['#txtEditSc#'] = __('éditer le scénario', __FILE__);
			$replace['#txtListAct#'] = __('Liste des actions/scénarios', __FILE__);
			$replace['#txtActive#'] = __('Active scénario', __FILE__);
			$replace['#txtDesactive#'] = __('Désactive scénario', __FILE__);
		}
		// pour chaque calendrier du widget //
		$nbCalRefresh = 0;
		$_iCalList = array();
		$_dayList = array();
		//
		foreach ($this->getCmd('info') as $_oCmd) {
			$_aItemsCmd = array();
			if ($_oCmd->getIsVisible()) {
				if (($_sEvents = $_oCmd->execCmd()) != '') {
					// génère le format en fonction de la vue //
					$_bTitleOnly = ($_oCmd->getConfiguration('titleOnly','0')=='1')?true:false;
					$_aEvents = iCalendarTools::eventsList2array($_sEvents, $_oCmd->getConfiguration('defaultValue',ICALENDAR_TXT_DV), $_bTitleOnly);
					$_nbEvent = ($_oCmd->getConfiguration('defaultValue', ICALENDAR_TXT_DV) != $_sEvents) ? count($_aEvents) : 0;
					if ($_nbEvent > 0) {
						// pour chaque événement //
						foreach ($_aEvents as $_aOneEvent) {
							if (!$_bTitleOnly) {
								$_hd = $_aOneEvent['hd'];
								$_hf = $_aOneEvent['hf'];
								// masque les heures //
								if (($_oCmd->getConfiguration('showHour')==0)
									|| (($_oCmd->getConfiguration('showHour24H')==0) && ($_aOneEvent['hd']=="00:00") && ($_aOneEvent['hf']=="23:59"))) {
										$_aOneEvent['hd'] = $_aOneEvent['hf'] = '';
								}
								// récupère les informations en cache des actions/scénario //
								if ($_aOneEvent['a']=='doAct') {
									if (($_version != 'mobile') && ($_oCmd->getConfiguration('acceptLaunchActSc',0)==1)) {
										$_aOneEvent['actSc'] = $this->getCacheEventActionsList(array('idCmd'=>$_oCmd->getId(), 'hd'=>$_hd, 'hf'=>$_hf, 'uid'=>$_aOneEvent['u'], 'date'=>date('Ymd',$this->_tsRef)), true);
										if (count($_aOneEvent['actSc'])==0) unset($_aOneEvent['actSc']);
									} else {
										$_aOneEvent['actSc'] = 'yes';
									}
								} elseif ($_aOneEvent['a']=='doInter') {
									$_aOneEvent['actSc'] = 'inter';
								}
							}
							unset($_aOneEvent['u']);
							unset($_aOneEvent['a']);
							unset($_aOneEvent['upd']);
							$_aItemsCmd[] = $_aOneEvent;
						}
					} else {
						$_nbEvent = 0;
						$_aItemsCmd[] = $_aEvents[0];
					}
					$nbCalRefresh++;
				} else {
					$_aItemsCmd[] = array ('t'=>$_oCmd->getConfiguration('defaultValue',ICALENDAR_TXT_DV),'u'=>'','a'=>'','upd'=>'','hd'=>'','hf'=>'','s'=>'');
				}
				if ($_oCmd->getConfiguration('isGoogleCal',false)==true) {
					if (($_privId = iCalendarTools::getPrivateIdGoogleCal($_oCmd->getConfiguration('iCalendarUrl',''))) !== false) {
						$_urlGoogle = "https://calendar.google.com/calendar/embed?src=".$_privId;
					}
				}
				$_aLogicalId = explode('-',$_oCmd->getLogicalId());
				$_dayCmd = $_aLogicalId[2];
				$_originId = $_aLogicalId[1];
				$_dayTSHuman = (time()+intval(str_replace('J','',$_dayCmd)*(60*60*24)));
				$_iCalList[$_originId][$_dayCmd] = array('id'=>$_oCmd->getId(), 'originId'=>$_originId, 'name'=>$_oCmd->getName(), 
														 'dayView'=>$_dayCmd, 'items'=>$_aItemsCmd, 'nbEvent'=>$_nbEvent, 
														 'dayHumanD'=> date('d',$_dayTSHuman), 'dayHumanFull'=> date_fr(date('D d M Y',$_dayTSHuman)),
														 'dCollect'=>$_oCmd->getCollectDate(), 'dValue'=>$_oCmd->getValueDate(),
														 'cmdUid' => 'cmd' . $this->getId() . eqLogic::UIDDELIMITER . mt_rand() . eqLogic::UIDDELIMITER);
				if ($_dayCmd=='J0') {
					$_iCalList[$_originId]['param'] = array('id'=>$_oCmd->getId(), 'periodeView'=>$_oCmd->getConfiguration('periodeView'),
															'viewStyle'=>$_oCmd->getConfiguration('viewStyle'),
															'gCalUrl'=>(isset($_urlGoogle)?$_urlGoogle:''), 
															'icsCalName'=>$_oCmd->getConfiguration('icsCalendarName'),
															'launchActSc'=>$_oCmd->getConfiguration('acceptLaunchActSc'),
															'launchInteract'=>$_oCmd->getConfiguration('acceptLaunchInteract'),
															'calendarStyle'=>$this->getConfiguration('calendarStyle','1dayAndNav'),
															'widgetStyle'=>$this->getConfiguration('widgetStyle','V'),
															'showLocation'=>$_oCmd->getConfiguration('showLocation','0'),
															'showNotNbEvent'=>$_oCmd->getConfiguration('showNotNbEvent','0'),
															'order'=>$_oCmd->getOrder(),
															'viewTxt'=>iCalendarTools::getViewTypeInTxt($_oCmd->getConfiguration('viewStyle')));
				}
			}
		}
		// ordonne les agendas //
		$_iCalListFinal = array();
		foreach($_iCalList as $_id => $_cal) {
			ksort($_iCalList[$_id]);
			$_iCalListFinal[($_iCalList[$_id]['param']['order']+1)] = $_iCalList[$_id];
		}
		ksort($_iCalListFinal);
		$replace['#iCalendar-list#'] = json_encode($_iCalListFinal);
		//$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] toHtml().replace=' . print_r($replace, true));
		// mise à jour du cache pour le widget //
		$html = $this->postToHtml($_version, template_replace($replace, getTemplate('core', $_version, 'iCalendar', 'iCalendar')));
		$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] toHtml(' . $_version . ') Refresh Widget (' . $nbCalRefresh . ' cal.): OK ('.round((getmicrotime()-$_sTs),3).')');
		return $html;
	}

	
	/*     * *********************** Methodes PLUGIN *************************** */

	/**
	 * Retourne le nom du fichier de log
	 * @return string
	 */
	public function logFN() {
		if ($this->_logFN=='') {
			$this->_logFN = ($this->getConfiguration('logSepar','1')=='1')?'iCalendar_'.$this->getId():'iCalendar';
		}
		return $this->_logFN;
	}
	
	/**
	 * Retourne le nom du paramètre en cache pour le contenu du Widget
	 * @param string $_version version de l'affichage
	 * @return string
	 */
	public function getWidgetCacheName($_version) {
		$user_id = '';
		if (isset($_SESSION) && isset($_SESSION['user']) && is_object($_SESSION['user'])) {
			$user_id = $_SESSION['user']->getId();
		}
		return 'widgetHtml'.$this->getId().$_version.$user_id;
	}

	/**
	 * Retourne la liste des champs privés pour traitement au niveau du plugin TroubleShooting
	 * @return array
	 */
	public function getPrivateFields() {
		return "{'config':'', 'eqlogic':'',	'cmd':'configuration.iCalendarUrl,configuration.calDavUser,configuration.calDavPwd'}"; 
	}
	
	/**
	 * Permet de lancer une action par son id, avec passage de paramètre
	 * @param array $_aAction informations sur l'action
	 * @param array $_aEvent informations sur l'évènement 
	 * @param string $_idCmd id de la commande à modifier
	 * @return void
	 */
	public function launchActionFromEvent($_aAction, $_aEvent, $_idCmd) {
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] launchActionFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_aAction['name'].') starting ...');
		if (intval($_aAction['id'])>0) {
			$_oAct = cmd::byId(intval($_aAction['id']));
			if (is_object($_oAct)) {
				$_option = array();
				$_msgAdd='';
				if (($_aAction['var']=='active')&&(($_aAction['val']=='0')||($_aAction['val']=='1'))) {
					$_do = false;
					$_oEqL = eqLogic::byId($_oAct->getEqLogic_id());
					if (is_object($_oEqL)) {
						$_oEqL->setIsEnable($_aAction['val']);
						$_oEqL->save();
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchActionFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_aAction['name'].'): '.__('l\'état de l\'équipement a été modifié en ',__FILE__).': '.(($_aAction['val']=='1')?__('activé',__FILE__):__('désativé',__FILE__)).'. (event:"'.$_aEvent['t'].'" ('.$_aEvent['u'].'))');
						$this->setEventActionsState(array('idCmd'=>$_idCmd), $_aAction, $_aEvent);
						$_msgAdd = '{'.$this->getName().'}: '.__('Equipement de commande',__FILE__).' '.$_aAction['name'].': '.(($_aAction['val']==1)?__('Activé',__FILE__):__('Désactivé',__FILE__)).' / '.(($_aAction['when']=='DA')?__('Début événement',__FILE__):__('Fin événement',__FILE__)).': '.$_aEvent['t'];
					} else {
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchActionFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_aAction['name'].') ERROR: '.__('une erreur est survenue, l\'équipement associé à la commande n\'existe pas; impossible de changer l\'état',__FILE__).'. (event:"'.$_aEvent['t'].'" ('.$_aEvent['u'].'))');
						$_msg = '{'.$this->getName().'}: '.__('Equipement de commande inexistant: ',__FILE__).' '.$_aAction['id'].', '.__('pour événement',__FILE__).': '.$_aEvent['t'].'. '.__('Impossible de changer son état',__FILE__);
						message::add($this->getEqType_name(), $_msg, '', $this->getLogicalId());
					}
					if (($this->getConfiguration('addMsgForActSc','0')=='1')&&($_msgAdd!='')) {
						message::add($this->getEqType_name(), $_msgAdd, '', $this->getLogicalId());
					}
				} else {
					if (($_aAction['var']!='') && ($_aAction['val']!='')) {
						$_option = array($_aAction['var'] => $_aAction['val']);
					}
					if ($_oAct->getEqLogic()->getIsEnable()) {
						$_oAct->execute($_option);
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchActionFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_aAction['name'].'): '.__('action demandée pour',__FILE__).': "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
						$this->setEventActionsState(array('idCmd'=>$_idCmd), $_aAction, $_aEvent);
						$_msgAdd = '{'.$this->getName().'}: '.__('Action déclenchée',__FILE__).': '.$_aAction['name'].' / '.(($_aAction['when']=='DA')?__('Début événement',__FILE__):__('Fin événement',__FILE__)).': '.$_aEvent['t'];
					} else {
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchActionFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_aAction['name'].'): ERROR: '.__('impossible de lancer la commande (car inactif)', __FILE__).': "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
					}
					if (($this->getConfiguration('addMsgForActSc','0')=='1')&&($_msgAdd!='')) {
						message::add($this->getEqType_name(), $_msgAdd, '', $this->getLogicalId());
					}
				}
			} else {
				$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchActionFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_aAction['name'].') ERROR: '.__('une erreur est survenue, la commande demandée n\'existe pas',__FILE__).'. (event:"'.$_aEvent['t'].'" ('.$_aEvent['u'].'))');
				$_msg = '{'.$this->getName().'}: '.__('Commande inexistante: ',__FILE__).' '.$_aAction['id'].', '.__('pour événement',__FILE__).': '.$_aEvent['t'].'. '.__('Impossible de changer son état',__FILE__);
				message::add($this->getEqType_name(), $_msg, '', $this->getLogicalId());
			}
		}
	}

	/**
	 * Permet de lancer un scénario par son id, avec passage de paramètre
	 * @param array $_aAction informations sur l'action
	 * @param array $_aEvent informations sur l'évènement 
	 * @param string $_idCmd id de la commande à modifier
	 * @return void
	 */
	public function launchScenarioFromEvent($_aAction, $_aEvent, $_idCmd) {
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] launchScenarioFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].') starting ...');
		if (intval($_aAction['id'])>0) {
			$_oSc = scenario::byId($_aAction['id']);
			if (is_object($_oSc)) {
				// gestion de l'état du scénrio (des/active) //
				$_msgAdd='';
				if ((substr($_aAction['var'],0,1)=='#')&&($_aAction['val']=='')) {
					if ($_aAction['var']=='#active') {
						$_oSc->setIsActive(1);
						$_oSc->save();
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchScenarioFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_oSc->getName().'): '.__('Activation du scénario', __FILE__).' --- "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
						$this->setEventActionsState(array('idCmd'=>$_idCmd), $_aAction, $_aEvent);
						$_msgAdd = $this->getName().': '.__('Activation du scénario',__FILE__).': ['.$_oSc->getName().'] / '.(($_aAction['when']=='DA')?__('Début événement',__FILE__):__('Fin événement',__FILE__)).': '.$_aEvent['t'];
					} elseif ($_aAction['var']=='#desactive') {
						$_oSc->setIsActive(0);
						$_oSc->save();
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchScenarioFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_oSc->getName().'): '.__('Désactivation du scénario', __FILE__).' --- "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
						$this->setEventActionsState(array('idCmd'=>$_idCmd), $_aAction, $_aEvent);
						$_msgAdd = '{'.$this->getName().'}: '.__('Désactivation du scénario',__FILE__).': ['.$_oSc->getName().'] / '.(($_aAction['when']=='DA')?__('Début événement',__FILE__):__('Fin événement',__FILE__)).': '.$_aEvent['t'];
					} else {
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchScenarioFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_oSc->getName().'): ERROR: '.__('La variable passée ne correspond à une action sur le scénario', __FILE__).', var="'.$_aAction['var'].'" --- "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
					}
				} else {
					// définition des options //
					if (($_aAction['var']!='') && ($_aAction['val']!='')) {
						$_oSc->setData($_aAction['var'], $_aAction['val']);
					}
					if ($_oSc->getIsActive()) {
						if ($_oSc->launch(false, '', __('Scenario exécuté sur événement iCalendar',__FILE__))) {
							$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchScenarioFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_oSc->getName().'): '.__('lancement du scénario avec', __FILE__).' '.$_aAction['var'].'='.$_aAction['val'].': "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
							$this->setEventActionsState(array('idCmd'=>$_idCmd), $_aAction, $_aEvent);
							$_msgAdd = '{'.$this->getName().'}: '.__('Scénario déclenchée',__FILE__).': ['.$_oSc->getName().'] / '.(($_aAction['when']=='DA')?__('Début événement',__FILE__):__('Fin événement',__FILE__)).': '.$_aEvent['t'];
						} else {
							$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchScenarioFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_oSc->getName().'): ERROR: '.__('impossible de lancer le scénario', __FILE__).' --- "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
						}
					} else {
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchScenarioFromEvent('.$_aEvent['s'].'|'.$_aAction['id'].'|'.$_oSc->getName().'): ERROR: '.__('impossible de lancer le scénario (car inactif)', __FILE__).' --- "'.$_aEvent['t'].'" ('.$_aEvent['u'].')');
					}
				}
				if (($this->getConfiguration('addMsgForActSc','0')=='1')&&($_msgAdd!='')) {
					message::add($this->getEqType_name(), $_msgAdd, '', $this->getLogicalId());
				}
			}
		}
	}

	/**
	 * Permet de lancer une reconnaissance du texte par l'interpreteur Jeedom
	 * @param string $_sTitle titre de l'événement
	 * @return string texte de la réponse de jeedom
	 */
	public function launchInteractFromEvent($_aAction, $_aEvent, $_idCmd) {
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] launchInteractFromEvent() starting ...');
		$_reply = '';
		if (!empty($_aEvent['t'])) {
			$_reply = interactQuery::tryToReply($_aEvent['t']);
			if ($_reply!=false) {
				if ($this->getConfiguration('addMsgForActSc','0')=='1') {
					$_msgAdd = '{'.$this->getName().'}: ['.$_aAction['name'].'] '.__('Evenement traité par Interaction',__FILE__).'. '.__('Réponse',__FILE__).' = '.$_reply;
					message::add($this->getEqType_name(), $_msgAdd, '', $this->getLogicalId());
				}
				$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|' . $this->getId() . '] launchInteractFromEvent() '.__('Traité pour le titre',__FILE__).'="'.$_aEvent['t'].'"; '.__('Réponse',__FILE__).'="'.$_reply.'"'); 
			} else {
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getId() . '] launchInteractFromEvent(): ERROR: with replay interaction query'); 
			}
			$this->setEventActionsState(array('idCmd'=>$_idCmd,'reply'=>$_reply), $_aAction, $_aEvent);
		}
	}

	/**
	 * Met en cache l'état des actions des événements
	 * @param array $_p liste de paramètres utiles au traitement (idCmd, uid)
	 * @return void
	 */
	public function setEventActionsState($_p=array(), $_aAction, $_aEvent) {
		if (!isset($this->_aEventActionsState[$_p['idCmd']])) {
			$this->_aEventActionsState[$_p['idCmd']] = json_decode(cache::byKey('iCalendar::'.$_p['idCmd'].'::EventActionsState')->getValue(),true);
		}
		$_date = date('Ymd');
		if (!isset($this->_aEventActionsState[$_p['idCmd']][$_date])) {
			$this->_aEventActionsState[$_p['idCmd']][$_date] = array();
		}
		$_aInfo = array('title'=>$_aEvent['t'], 'when'=>$_aAction['when'], 'tsInit'=>$_aAction['tsWhen'], 'tsExec'=>time(),
						'uid'=>$_aAction['uid'], 'var'=>$_aAction['var'], 'val'=>$_aAction['val'], 
						'type'=>$_aAction['t'], 'id'=>$_aAction['id'], 'name'=>$_aAction['name']);
		if (($_aAction['t']=='I')&&(isset($_p['reply']))) {
			$_aInfo['reply'] = ($_p['reply']!=false)?$_p['reply']:__('Non reconnu',__FILE__);
		}
		$this->_aEventActionsState[$_p['idCmd']][$_date][$_aAction['idAct']] = $_aInfo;
		cache::set('iCalendar::'.$_p['idCmd'].'::EventActionsState', json_encode($this->_aEventActionsState[$_p['idCmd']]), 0);
		$this->_log->add($this->logFN(), 'debug', '[' .$this->_whatLog.'|'. $this->getId() . '|' . $_p['idCmd'] . '] setEventActionsState() update state of idAction = '.$_aAction['idAct']);
	}

	/**
	 * Met en cache la liste des actions d'un événément
	 * @param array $_p liste de paramètres utiles au traitement (idCmd, uid)
	 * @param array $_aActions liste des actions formatées en tableau
	 * @return void
	 */
	public function setCacheEventActionsList($_p=array(), $_aActions) {
		if (!isset($this->_aEventsActionsList[$_p['idCmd']])) {
			$this->_aEventsActionsList[$_p['idCmd']] = json_decode(cache::byKey('iCalendar::'.$_p['idCmd'].'::EventsActionsList')->getValue(),true);
		}
		if (!isset($this->_aEventsActionsList[$_p['idCmd']][$_p['now']])) {
			$this->_aEventsActionsList[$_p['idCmd']][$_p['now']] = array();
		}
		$this->_aEventsActionsList[$_p['idCmd']][$_p['now']] = array_merge($this->_aEventsActionsList[$_p['idCmd']][$_p['now']], $_aActions);
		cache::set('iCalendar::'.$_p['idCmd'].'::EventsActionsList', json_encode($this->_aEventsActionsList[$_p['idCmd']]), 0);
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getId().'|'.$_p['idCmd'].'] setCacheEventActionsList(): set cache value for uid='.$_p['uid'].' for day='.$_p['now']);
	}

	/**
	 * Récupère les informations sur la liste des actions d'un événement à partir du cache
	 * @param array $_p liste de paramètres utiles à la recherche (idCmd, uid)
	 * @param array 
	 * @return array retourne le tableau
	 */
	public function getCacheEventActionsList($_p=array(), $_withState=false) {
		if (!isset($this->_aEventsActionsList[$_p['idCmd']])) {
			$this->_aEventsActionsList[$_p['idCmd']] = json_decode(cache::byKey('iCalendar::'.$_p['idCmd'].'::EventsActionsList')->getValue(),true);
		}
		if ($_withState==true) {
			if (!isset($this->_aEventActionsState[$_p['idCmd']])) {
				$this->_aEventActionsState[$_p['idCmd']] = json_decode(cache::byKey('iCalendar::'.$_p['idCmd'].'::EventActionsState')->getValue(),true);
			}
			$_p['date'] = (!isset($_p['date']))?date('Ymd'):$_p['date'];
		}
		$_aActionsReturn = array();
		// ------ Recherche pour un UID donné //
		if (isset($this->_aEventsActionsList[$_p['idCmd']][$_p['date']])) {
			if (isset($_p['uid'])) {
				foreach($this->_aEventsActionsList[$_p['idCmd']][$_p['date']] as $_aAction) {
					if (($_p['uid'] == $_aAction['uid'])
						&&(str_replace(':','',$_p['hd']) <= str_replace(':','',$_aAction['tsWhenTimeHuman']))
						&&(str_replace(':','',$_p['hf']) >= str_replace(':','',$_aAction['tsWhenTimeHuman']))) {
							if (($_withState==true) && (isset($this->_aEventActionsState[$_p['idCmd']][$_p['date']][$_aAction['idAct']]))) {
								$_aAction['tsExec'] = date('Y/m/d H:i:s',$this->_aEventActionsState[$_p['idCmd']][$_p['date']][$_aAction['idAct']]['tsExec']);
							}
							//$_aActionsReturn[$_aAction['tsWhen'].'-'.$_aAction['t'].$_aAction['id']] = $_aAction;
							$_aActionsReturn[] = $_aAction;
					}
				}
			} else {
				// ------ Remonté global //
				foreach($this->_aEventsActionsList[$_p['idCmd']][$_p['date']] as $_aAction) {
					if (($_withState==true) && (isset($this->_aEventActionsState[$_p['idCmd']][$_p['date']][$_aAction['idAct']]))) {
						$_aAction['tsExec'] = date('Y/m/d H:i:s',$this->_aEventActionsState[$_p['idCmd']][$_p['date']][$_aAction['idAct']]['tsExec']);
					}
					//$_aActionsReturn[$_aAction['tsWhen'].'-'.$_aAction['t'].$_aAction['id']] = $_aAction;
					$_aActionsReturn[] = $_aAction;
				}
			}
			//ksort($_aActionsReturn);
		}
		return $_aActionsReturn;
	}
		
	/**
	 * Pousse des informations dans le zone message, entre 2 versions pour informer des changements //
	 * @return void
	 */
	public function cheackInfo() {
		foreach (self::byType('iCalendar') as $iCalendar) {
			// info : suppression du format "+1 heure" //
			$_doMsg = false;
			foreach ($iCalendar->getCmd('info') as $_oCmd) {
				if ((substr($_oCmd->getLogicalId(),-2)=='J0')&&($_oCmd->getConfiguration('viewStyle')=='1day_next1hour')) {
					$_doMsg = true;
				}
			}
			if ($_doMsg) {
				$_msg = 'INFO: {'.$iCalendar->getName().'}: dans la prochaine version de iCalendar l\'affichage du format "+ 1heure" ne sera plus supporté. Merci de modifier la configuration de vos commandes avant la prochaine mise à jour du plugin.';
				message::add($iCalendar->getEqType_name(),$_msg, '', $iCalendar->getLogicalId());
			}
			// --- //
		}
	}

}

/**
 * -------------------------------------------------------------------------------------------------------------------------
 * Class Extend cmd pour le plugin iCalendar
 * @author abarrau
 */
class iCalendarCmd extends cmd {
	/*     * ************************* Attributs ****************************** */
	public $_log; 
	public $_logFN = '';
	public $_isSimpleSave = false;
	public $_isFromPreSave = false;
	public $_fileCacheName;
	public $_fileCacheNameWPath;
	public $_icsContents = false;
	public $_whatLog = ''; 
	public $_sExecCmdPrevious = false;
	public $_tsRef;
	public static $_widgetPossibility = array('custom' => false);
	
	/*     * ********************* Methodes JEEDOM ************************* */

	/**
	 * Fonction utilisée à l'initiation de la class
	 * @return void
	 */
	public function __construct() {
		$this->_log = new log();
		$this->_tsRef = time();
	}

	/**
	 * Traitement des actions au moment de l'enregistrement de la commande (pré)
	 * @return void
	 */
	public function preSave() {
		if (!$this->_isSimpleSave) {
			$this->_whatLog = 'SAVE';
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.(isset($this->eqLogic_id)?$this->eqLogic_id:'NEW').'|'.$this->getId().'] SAVE START // cmd.preSave(): "'.$this->getName().'"');
			// >>> action à vérifier/utiliser, seulement si la commande en cours est la commande principale (J0) //
			if (($_originalCmdId=$this->getConfiguration("originalCmdId",false))=='') {
				if ($this->getConfiguration('iCalendarUrl') == '') {
					throw new Exception($this->getName().': '.__('L\'url de l\'agenda ne peut pas être vide', __FILE__));
				} else {
					if (strpos($this->getConfiguration('iCalendarUrl'),'webcals://')!==false) {
						$this->setConfiguration('iCalendarUrl', str_replace('webcals://', 'http://', $this->getConfiguration('iCalendarUrl')));
					}
				}
				// ce qui suit ne peut fonctionner qu'après avoir créé l'agenda //
				if ($this->getId() > 0) {
					$this->setLogicalId('iCal-'.$this->getId().'-J0');
					//$this->setOrder(intval($this->getId()*100));
					$this->actionOnSave();
					if (($this->getConfiguration('viewStyle')=='current')&&($this->getConfiguration('titleOnly','0')=='1')) {
						$this->setConfiguration('acceptLaunchActSc','0');
						$this->setConfiguration('acceptLaunchInteract','0');
						$this->setConfiguration('actionIsHistorized','0');
						$this->setConfiguration('showLocation','0');
						$this->setConfiguration('showHour','0');
						$this->setConfiguration('showHour24H','0');
						$this->setConfiguration('indicDebFin','0');
					} else if ($this->getConfiguration('viewStyle')!='current') {
						$this->setConfiguration('titleOnly','0');
					}
				} else {
					$this->setLogicalId('iCal-NEW-J0');
				}
				// définie l'état de la prochaine synchro //
				if (!$this->getEqLogic()->getIsEnable()) {
					$this->setConfiguration('dateSyncNext', 'STOP');
				} else {
					$this->setConfiguration('dateSyncNext',date('Y-m-d H:i:00',$this->getNextDateSynchro()));
				}
				$this->setIsHistorized(0);
				// suppression du cache jeedom pour les widget (ce cas est utilisée pour gérer les cas réorganisation de cmd sur le widget, soit sur dashboard) //
				$this->getEqLogic()->emptyCacheWidget();
				//DEL//cache::byKey('iCalendarWidgetdashboard' . $this->getEqLogic()->getId())->remove();
			} else {
				//$this->setOrder(($_originalCmdId*100)+intval(substr($this->getLogicalId(),-1)));
			}
		}
	}

	/**
	 * Traitement des actions au moment de l'enregistrement de la commande (post)
	 * @return void
	 */
	public function postSave() {
		if (!$this->_isSimpleSave) {
			// n'est réalisé qu'à la 1ère sauvegarde de l'objet //
			if ($this->getLogicalId()=='iCal-NEW-J0') {
				$this->_isSimpleSave = true;
				$this->setLogicalId('iCal-'.$this->getId().'-J0');
				//$this->setOrder(intval($this->getId()*100));
				$this->actionOnSave();
				$this->save();
			}
			// crée les commandes suplémentaires, uniquement si la commande en cours est la J0  //
			if (substr($this->getLogicalId(),-2)=='J0') { //$this->getConfiguration("originalCmdId")=='')) {
				$_periodeWorking = $this->getConfiguration("periodeWorking",0);
				$_EqlGC = $this->getEqLogic();
				// ----- commandes "info" : pour les journées "sur la semaine" //
				for ($_i=1;$_i<=6;$_i++) {
					$_eqLogId = 'iCal-'.$this->getId().'-J'.$_i;
					$_oCmd = $_EqlGC->getCmd('info',$_eqLogId);
					if ($_i <= $_periodeWorking) {
						//$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] postSave(): '.$_i.' &lt;= '.$_periodeWorking);
						if (!is_object($_oCmd)) {
							$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] postSave(): _oCmd of "'.$_eqLogId.'" is not an existing object (set new)');
							$_oCmd = new iCalendarCmd();
							$_oCmd->setLogicalId('iCal-'.$this->getId().'-J'.$_i);
							$_oCmd->setType("info");
							$_oCmd->setSubType("string");
							$_oCmd->setEqLogic_id($this->eqLogic_id);
							$_oCmd->setConfiguration('originalCmdId',$this->getId());
							$_oCmd->setOrder($_i);
						}
						$_oCmd->setName($this->getName()." (J+".$_i.")");
						$_oCmd->setIsVisible(($_i<=$this->getConfiguration('periodeView','0'))?$this->getIsVisible():0);
						$_oCmd->setConfiguration('iCalendarUrl', '-');
						$_oCmd->setConfiguration('periodeWorking',$this->getConfiguration('periodeWorking',0));
						$_oCmd->setConfiguration('defaultValue', $this->getConfiguration('defaultValue',''));
						$_oCmd->setConfiguration('viewStyle', $this->getConfiguration('viewStyle',''));
						$_oCmd->setConfiguration('indicDebFin', $this->getConfiguration('indicDebFin',''));
						$_oCmd->setConfiguration('showHour', $this->getConfiguration('showHour',''));
						$_oCmd->setConfiguration('showHour24H', $this->getConfiguration('showHour24H',''));
						$_oCmd->setConfiguration('periodeView', $this->getConfiguration('periodeView','0'));
						$_oCmd->setConfiguration('showLocation', $this->getConfiguration('showLocation','0'));
						$_oCmd->setConfiguration('actionIsHistorized', $this->getConfiguration('actionIsHistorized','0'));
						$_oCmd->_isSimpleSave = true;
						$_oCmd->save();
						$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] postSave(): create/update cmd:"'.$_oCmd->getName().'" - eqLogicId='.$_eqLogId);
					} else {
						if (is_object($_oCmd)) {
							$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] postSave(): delete cmd:"'.$_oCmd->getName().'" - eqLogicId='.$_eqLogId);
							$_oCmd->remove();
						}
					}
				}
				// ----- commandes "action" //
				$_eqLogId = 'iCal-'.$this->getId().'-ExecFunction';
				$_oCmd = $_EqlGC->getCmd('action',$_eqLogId);
				if (!is_object($_oCmd)) {
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] postSave(): _oCmd of "'.$_eqLogId.'" is not an existing object (set new)');
					$_oCmd = new iCalendarCmd();
					$_oCmd->setLogicalId($_eqLogId);
					$_oCmd->setType("action");
					$_oCmd->setSubType("message");
					$_oCmd->setEqLogic_id($this->eqLogic_id);
					$_oCmd->setConfiguration('originalCmdId',$this->getId());
					//$_oCmd->setOrder(($this->getId()*100)+50);
					$_oCmd->setDisplay('title_placeholder', __('Fonction', __FILE__));
					$_oCmd->setDisplay('message_placeholder', __('Arguments', __FILE__));
					$_aTitle_possibility_list = array( 	'getTimeStart - '.__('Arguments (par ligne)= title=xxx ou id=xxx / date=format php ou vide / jour=Jx (J0,J1,J2,...)',__FILE__),	
														'getTimeEnd - '.__('Arguments (par ligne)= title=xxx ou id=xxx / date=format php ou vide / jour=Jx (J0,J1,J2,...)',__FILE__),
														'getTitle - '.__('Arguments (par ligne)= id=xxx / jour=Jx (J0,J1,J2,...)',__FILE__), 
														'getUid - '.__('Arguments (par ligne)= title=xxx / jour=Jx (J0,J1,J2,...)',__FILE__),
														'getLocation - '.__('Arguments (par ligne)= title=xxx ou id=xxx / jour=Jx (J0,J1,J2,...)',__FILE__), 
														'getDaySimple - '.__('Argument= jour=Jx (J0,J1,J2,...)',__FILE__),
														'getDayTitleOnly - '.__('Argument= jour=Jx (J0,J1,J2,...)',__FILE__),
														'getDayActifOnly',
														'getDayActifAndTitleOnly');
					$_oCmd->setDisplay('title_possibility_list', json_encode($_aTitle_possibility_list));
				}
				$_oCmd->setConfiguration('periodeWorking',$this->getConfiguration('periodeWorking'));
				$_oCmd->setName($this->getName()." (ExecuteFunction-".$this->getId().")");
				$_oCmd->setIsVisible($this->getIsVisible());
				$_oCmd->_isSimpleSave = true;
				$_oCmd->save();
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] postSave(): create/update cmd:"'.$_oCmd->getName().'" - eqLogicId='.$_eqLogId);
			}
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] SAVE END // cmd.postSave()');
		}
	}
	
	/**
	 * Traitement des actions juste avant la suppression de la commande
	 * @return void
	 */
	public function preRemove() {
		// action uniquement si on est sur la commande J0 //
		if ($this->getType()=='info') {
			if ($this->getConfiguration("originalCmdId")=='') {
				// suppression des jours "complémentaires" //
				$_EqlGC = $this->getEqLogic();
				for ($_i=1;$_i<=6;$_i++) {
					$_eqLogId = 'iCal-'.$this->getId().'-J'.$_i;
					$_oCmd = $_EqlGC->getCmd('info',$_eqLogId);
					if (is_object($_oCmd)) {
						$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] preRemove(): delete cmd:"'.$_oCmd->getName().'" - eqLogicId='.$_eqLogId);
						$_oCmd->remove();
					}
				}
				// suppression des commandes actions //
				$_eqLogId = 'iCal-'.$this->getId().'-ExecFunction';
				$_oCmd = $_EqlGC->getCmd('action',$_eqLogId);
				if (is_object($_oCmd)) {
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] preRemove(): delete cmd:"'.$_oCmd->getName().'" - eqLogicId='.$_eqLogId);
					$_oCmd->remove();
				}
			}
			// suppression du cache // 
			cache::byKey('iCalendar::'.$this->getId().'::PeriodeEvents')->remove();
			cache::byKey('iCalendar::'.$this->getId().'::EventsActionsList')->remove();
			// suppression des fichiers //
			$_aSD = array_diff(scandir(ICALENDAR_CACHE_PATH), array('.','..'));
			$_iCalCmdID = $this->getId();
			foreach ($_aSD as $_f) {
				$_af = explode('-', $_f);
				if ($_af[0] == 'iCal'.$_iCalCmdID) {
					iCalendarTools::cleanCacheFile($_f);
				}
			}
		}
	}

	/**
	 * Indique que les commandes obligatoires ne peuvent pas être supprimée. 
	 * @return boolean 
	 */
	public function dontRemoveCmd() {
		if (($this->getType()=='action')||(($this->getType()=='info')&&(substr($this->getLogicalId(),-2)!='J0'))) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Traitement des actions principales (test de refresh cache, génération des données pour le widget, ...)
	 * @param array $_options tableau des options de la fonction execute()
	 * @return string
	 */
	public function execute($_options = array()) {
		$this->_whatLog = ($this->_whatLog!='')?$this->_whatLog:'EXEC';
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute('.$this->getLogicalId().') starting... ');
		if ($this->getType()=='info') {
			// --- traitement "INFO" : gestion des contenus des calendriers //
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() INFO cmd with defaultValue="' . $this->getConfiguration('defaultValue') . '", viewStyle="' . $this->getConfiguration('viewStyle') . '", indicateur="' . $this->getConfiguration('indicDebFin') . ', periodeWorking="'.$this->getConfiguration('periodeWorking').'", originalCmdId="'.$this->getConfiguration('originalCmdId').'"');
			list($_z, $_originId, $_dayCmd) = explode('-',$this->getLogicalId());
			$_aCurrentCachePeriode = json_decode(cache::byKey('iCalendar::'.$_originId.'::PeriodeEvents')->getValue());
			if (count($_aCurrentCachePeriode)==0) {
				// si la période n'existe pas, retourne la valeur par défaut //
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() ERROR: _aCurrentCachePeriode is empty, not action');
				return $this->getConfiguration('defaultValue', ICALENDAR_TXT_DV);
			}
			$_aTS = array();
			if ($_dayCmd=='J0') {
				$_aTS['dWork']['s'] = mktime('00','00','00');
				$_aTS['dWork']['e'] = mktime('23','59','59');
				// pour le jour courant //
				if ($this->getConfiguration('viewStyle')=='current') {
					$_aTS['dView']['s'] = $_aTS['dView']['e'] = $this->_tsRef;
				} elseif ($this->getConfiguration('viewStyle')=='1day_next1hour') {
					$_aTS['dView']['s'] = $this->_tsRef;
					$_aTS['dView']['e'] = strtotime("+1 hours");
				} else {
					$_aTS['dView']['s'] = $_aTS['dWork']['s'];
					$_aTS['dView']['e'] = $_aTS['dWork']['e'];
				}
			} else {
				// pour les autres jours (J+...) //
				if (($_nbDay = str_replace('J','',$_dayCmd)) >0) {
					$_nbSec = (60*60*24) * $_nbDay;
					$_aTS['dView']['s'] = $_aTS['dWork']['s'] = mktime('00','00','00') + $_nbSec;
					$_aTS['dView']['e'] = $_aTS['dWork']['e'] = mktime('23','59','59') + $_nbSec;
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute(), add '.$_nbDay.' days since today ('.$_nbSec.'sec)');
				}
			}
			// conserve la version actuelle de la commande pour comparaison //
			if ($this->_sExecCmdPrevious == false) {
				$this->_sExecCmdPrevious = $this->execCmd();
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute(), reload _sExecCmdPrevious variable');
			}
			//DEL//$_aEventsPrevious = iCalendarTools::eventsList2array($this->_sExecCmdPrevious, $this->getConfiguration('defaultValue', ICALENDAR_TXT_DV), $this->getConfiguration('titleOnly','0'), false, true);
			// récupère et structure la commande agenda //
			$_aResultEvents = array();
			$_aTS['dNow'] = $this->_tsRef;
			// traitement de la période en cache //
			foreach ($_aCurrentCachePeriode	as $_aOneEvent) {
				if ((!empty($_aOneEvent->dStart)) && (!empty($_aOneEvent->dEnd))) {
					if ($_aOneEvent->dStart <= $_aOneEvent->dEnd) {
						$_aOneEvent->dStartNew = ($_aOneEvent->dStart <= $_aTS['dWork']['s']) ? $_aTS['dWork']['s'] : $_aOneEvent->dStart;
						$_aOneEvent->dEndNew = ($_aOneEvent->dEnd >= $_aTS['dWork']['e']) ? $_aTS['dWork']['e'] : $_aOneEvent->dEnd;
						// définie si l'événement est pour la période consernée (si oui on définie son état) //
						if ($this->isEventForPeriode($_aOneEvent, $_aTS)==true) {
							if (date('H:i', $_aOneEvent->dEnd) != '23:59') { $_aOneEvent->dEnd = $_aOneEvent->dEnd - 60; }
							$_aOneEvent->state = $this->setActif($_aOneEvent, $_aTS);
							$_aNewEvent = array('hd'=>date('H:i',$_aOneEvent->dStartNew), 'hf'=>date('H:i',$_aOneEvent->dEndNew), 'a'=>'',
												's'=>$_aOneEvent->state, 't'=>str_replace('\,',',',$_aOneEvent->title), 'u'=>$_aOneEvent->uid, 
												'upd'=>$_aOneEvent->dLastUp, 'loc'=>$_aOneEvent->loc);
							if ($_dayCmd=='J0') {
								$_aNewEvent['a'] = $this->setEventParams($_aOneEvent);
							}
							$_sFormatedEvent = iCalendarTools::eventArray2EventString($_aNewEvent, $this->getConfiguration('titleOnly','0'));
							if ($_sFormatedEvent!='') {
								array_push($_aResultEvents, $_sFormatedEvent);
								$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'|' . $_aOneEvent->title . '] execute(): added event.');
							}
						}
					} else {
						$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'|' . $_aOneEvent->title . '] execute(): '.__("la date de début est supérieure à la date de fin", __FILE__).' (' . $_aOneEvent->dStart . '&gt;' . $_aOneEvent->dEnd . '). '.__("Vérifier votre RDV dans l'agent Google", __FILE__));
					}
				}
			}
			$_sResultEvents = '';
			if (count($_aResultEvents) == 0) {
				return $this->getConfiguration('defaultValue', ICALENDAR_TXT_DV);
			} else {
				// Formate les évènements dans une variable structurée //
				$_sResultEvents = implode('||',$_aResultEvents);
				return (!empty($_sResultEvents)) ? $_sResultEvents : $this->getConfiguration('defaultValue', ICALENDAR_TXT_DV);
			}
			return (!empty($this->_sExecCmdPrevious)) ? $this->_sExecCmdPrevious : $this->getConfiguration('defaultValue', ICALENDAR_TXT_DV);
		
		} else if ($this->getLogicalId()=='iCal-'.$this->getConfiguration("originalCmdId").'-ExecFunction') {
			
			// ============================ ACTION: commande d'appel à une fonction générique //
			$this->_whatLog = 'SCENARIO';
			if (isset($_options['title'])) {
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() with optionTitle='.$_options['title']);
				$_aFct = explode('-',$_options['title']);
				$_aFct[0] = trim($_aFct[0]);
				$_val = -99;
				scenario::setData($_aFct[0].'_'.$this->getConfiguration('originalCmdId'),$_val);
				if (isset($_options['message'])) {
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() with optionMessage='.$_options['message']);
					$_aArg = explode("\n",$_options['message']);
					// init les arguments récupérés //
					$_aParams = array('jour'=>'','date'=>'','title'=>'','uid'=>'','_what'=>'');
					if (count($_aArg)>0) {
						for ($i=0;$i<count($_aArg);$i++) {
							list($_kKey,$_vKey) = explode("=",$_aArg[$i]);
							$_kKey = str_replace('titre','title',$_kKey);
							$_kKey = str_replace('id','uid',$_kKey);
							if (($_vKey != '')) {
								$_aParams[$_kKey] = $_vKey;
								if (($_kKey=='title')||($_kKey=='uid')) $_aParams['_what'] = $_kKey;
							}
						}
					}
					// définie le format de date si vide //
					if ($_aParams['date']=='') $_aParams['date'] = 'U';
					if ($_aParams['jour']=='') $_aParams['jour'] = 0; else { $_aParams['jour'] = str_replace(array('J','j','+'),array('','',''),$_aParams['jour']); }
					if ($_aParams['_what']=='') $_aParams['_what']='title';
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() _aParams='.print_r($_aParams, true));
					
					// lance la fonction //
					switch($_aFct[0]) {
						case "getTimeStart":$_val = $this->Sc_getValueInEvent($_aParams[$_aParams['_what']],$_aParams['_what'],'dStart',$_aParams['jour'],$_aParams['date']); break;
						case "getTimeEnd":	$_val = $this->Sc_getValueInEvent($_aParams[$_aParams['_what']],$_aParams['_what'],'dEnd',$_aParams['jour'],$_aParams['date']); break;
						case "getTitle":	$_val = $this->Sc_getValueInEvent($_aParams[$_aParams['_what']],$_aParams['_what'],'title',$_aParams['jour']); break;
						case "getUid":		$_val = $this->Sc_getValueInEvent($_aParams[$_aParams['_what']],$_aParams['_what'],'uid',$_aParams['jour']); break;
						case "getLocation":	$_val = $this->Sc_getValueInEvent($_aParams[$_aParams['_what']],$_aParams['_what'],'loc',$_aParams['jour']); break;
						case "getDaySimple": 	$_val = $this->Sc_getDayNewTrame($_aParams['jour'], false,false); break;
						case "getDayTitleOnly": $_val = $this->Sc_getDayNewTrame($_aParams['jour'], true,false); break;
						case "getDayActifOnly": $_val = $this->Sc_getDayNewTrame(0, false,true); break;
						case "getDayActifAndTitleOnly": $_val = $this->Sc_getDayNewTrame(0, true,true); break;
						default: 
							$_val = -1;
							$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() ERROR: '.__('la fonction utilisée n\'est pas connues',__FILE__).': '.$_aFct[0]);
					} 
				} else {
					$_val = -1;
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() argument not defined, not execute function ('.$_aFct[0].')');
				}
				scenario::setData($_aFct[0].'_'.$this->getConfiguration('originalCmdId'),$_val);
			} else {
				$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] execute() ERROR: '.__('le nom de la fonction n\'est pas définie dans votre scénario',__FILE__));
			}
		}
	}

	/*     * *********************** Methodes PLUGIN *************************** */

	/**
	 * Retourne le nom du fichier de log
	 * @return string
	 */
	public function logFN() {
		if ($this->_logFN=='') {
			$this->_logFN = ($this->getEqLogic()->getConfiguration('logSepar','1')=='1')?'iCalendar_'.$this->getEqLogic()->getId():'iCalendar';
		}
		return $this->_logFN;
	}
	
	/**
	 * Fonction "fake" utilisée uniquement pour faire croire à la fonction setArrayHealthNetworkForHealthPage() (OlindoteTools) que les commandes sont enables !
	 */
	public function getIsEnable() {
		return true; 
	}

	/**
	 * Traitement des actions au moment de l'enregistrement de la commande (pré ou post) 
	 * @return void
	 */
	public function actionOnSave() {
		// vérifie si la période à changer ou s'il n'y a pas de fichier cache ; pour regénérer le fichier et le cache //
		$_currentCachePeriode = cache::byKey('iCalendar::'.$this->getId().'::PeriodeEvents')->getValue();
		if (($this->getConfiguration('periodeWorking') > $this->getConfiguration('periodeWorkingBack'))
			|| ((!file_exists($this->getFileCacheName(true))) && (empty($_currentCachePeriode)))) {
			$this->getEqLogic()->setConfiguration('forceSynchro','1');
			$this->getEqLogic()->setConfiguration('importantDataChanged','1');
		}
		// force la mise  jour du fichier //
		if ($this->getEqLogic()->getConfiguration('forceSynchro','0')=='1') {
			$this->_isFromPreSave = true;
			$this->manageICSFile(true);
			$this->getEqLogic()->setConfiguration('importantDataChanged','1');
		}
		// recharge le cache //
		if ($this->getEqLogic()->getConfiguration('importantDataChanged','0')=='1') {
			$this->setCacheRange();
		}
	}

	/**
	 * Vérifie si l'événement est sur la plage à afficher/traiter
	 * @param object $_aOneEvent objet de l'événement
	 * @param array $_aTS différent horaires : origine évènement, recalculé évènement, période traitée, heure actuelle
	 * @return boolean 
	 */
	public function isEventForPeriode($_aOneEvent, $_aTS) {
		$result = false;
		switch ($this->getConfiguration('viewStyle')) {
			case "1day_today":
				if ((($_aOneEvent->dStartNew <= $_aTS['dView']['s']) || ($_aOneEvent->dStartNew <= $_aTS['dView']['e']))
					&& (($_aTS['dView']['s'] <= $_aOneEvent->dEndNew) || ($_aTS['dView']['e'] <= $_aOneEvent->dEndNew))) {
						$result = true;
				}
				break;
			case "1day_next1hour":
				$_tsEnd = (strtotime("+1 hours") >= $_aTS['dView']['e']) ? $_aTS['dView']['e'] : strtotime("+1 hours");
				if ((($_aOneEvent->dStartNew <= $_aTS['dNow']) || ($_aOneEvent->dStartNew <= $_tsEnd))
					&& (($_aTS['dNow'] <= $_aOneEvent->dEndNew) || ($_tsEnd <= $_aOneEvent->dEndNew))) {
						$result = true;
				}
				break;
			case "current":
			default:
				if (($_aOneEvent->dStartNew <= $_aTS['dNow']) && ($_aTS['dNow'] <= $_aOneEvent->dEndNew)) {
					$result = true;
				}
				break;
		}
		if ($result) {
			$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'|' . $_aOneEvent->title . '] isEventForPeriode(): event in the periode');
		}
		return $result;
	}

	/**
	 * Vérifie et met à jour les informations de paramétrage (action/description)
	 * @param object $_aOneEvent objet de l'événement
	 * @return string
	 */
	public function setEventParams($_aOneEvent) {
		$_isEventWParam = '';
		// définie la structure des actions à produire //
		if ($_aOneEvent->description!='') {
			// -- traitement des actions //
			if (($this->getConfiguration('acceptLaunchActSc','0')=='1') && ($this->getConfiguration('indicDebFin','0')=='1')
				&&((strpos($_aOneEvent->description,'DA|')!==false) || (strpos($_aOneEvent->description,'FA|')!==false))) {
					// set Action List pour chaque événement //
					$_aActionScList = iCalendarTools::eventDescription2array($this, $_aOneEvent);
					$this->getEqLogic()->setCacheEventActionsList(array('idCmd'=>$this->getId(), 'uid'=>$_aOneEvent->uid, 'now'=>date('Ymd',$this->_tsRef)), $_aActionScList);
              		$_isEventWParam = 'doAct';
			}
		}
		if ((empty($_isEventWParam)) && ($this->getConfiguration('acceptLaunchInteract','0')=='1')) {
			$_idAct = 'I0DA'.date('mdHi',$_aOneEvent->dStart).'-'.md5($_aOneEvent->uid);
			$_aActionScList = array($_idAct=> array('uid'=>$_aOneEvent->uid, 'idAct'=>$_idAct, 'when'=>'DA', 'tsWhen'=>$_aOneEvent->dStart, 
													'tsWhenTimeHuman'=>date('H:i',$_aOneEvent->dStart), 
													'tsWhenDateHuman'=>date('d/m',$_aOneEvent->dStart), 
													't'=>'I', 'id'=>0, 'name'=>$_aOneEvent->title, 'eqLType'=>'', 'eqLId'=>'', 
													'var'=>'','val'=>''));
			$this->getEqLogic()->setCacheEventActionsList(array('idCmd'=>$this->getId(), 'uid'=>$_aOneEvent->uid, 'now'=>date('Ymd',$this->_tsRef)), $_aActionScList);
			$_isEventWParam = 'doInter';
		}
		return $_isEventWParam;
	}
	
	/**
	 * Définie la valeur de l'état "Actif"
	 * @return string
	 */
	public function setActif($_aOneEvent, $_aTS) {
		$_sActif = ((date('YmdHi', $_aOneEvent->dStart) <= date('YmdHi', $_aTS['dNow'])) && (date('YmdHi', $_aTS['dNow']) <= date('YmdHi', $_aOneEvent->dEnd))) ? 'A' : '';
//DEL??//		$_sActif = (($_aOneEvent->dStart <= $_aTS['dNow']) && ($_aTS['dNow'] <= $_aOneEvent->dEnd)) ? 'A' : '';
		if ((!empty($_sActif)) && ($this->getConfiguration('indicDebFin') == 1)) {
			if (date('YmdHi', $_aOneEvent->dStart) == date('YmdHi', $_aTS['dNow'])) {
				$_sActif = 'D' . $_sActif;
			} else {
				if ((date('YmdHi', $_aOneEvent->dEnd)) == date('YmdHi', $_aTS['dNow'])) { $_sActif = 'F' . $_sActif; }
			}
		} elseif (date('YmdHi', $_aOneEvent->dEnd) <= date('YmdHi', $_aTS['dNow'])) {
			$_sActif = (date('H:i', $_aOneEvent->dEnd) != '23:59')?'P':'';
		}
		return $_sActif;
	}

	/** 
	 * Retourne l'heure de début pour le traitement du cache
	 * @param boolean $_utc définie si doit être retourné en UTC
	 * @return timestamps
	 */
	public function getCacheDateStart($_utc=false) {
		$_ts = mktime('00','00','00');
		if ($_utc) $_ts = $_ts + ((-1)*date('Z'));
		return $_ts;
	}

	/** 
	 * Retourne l'heure de fin pour le traitement du cache
	 *	rmq: je rajoute "+1" pour toujours avoir à minima la journée courante + la journée suivante (même sur 1 journée), //
	 * 		cela permet d'anticiper le lendemain en cache, au moment du traitement de minuit (pour éviter les problèmes au niveau du cron) //
	 * @param boolean $_utc définie si doit être retourné en UTC
	 * @return timestamps
	 */
	public function getCacheDateEnd($_utc=false) {
		$_nbSec = (60*60*24) * ($this->getConfiguration('periodeWorking',0) + 1);
		$_ts = mktime('23','59','59') + $_nbSec;
		if ($_utc) $_ts = $_ts + ((-1)*date('Z'));
		return $_ts;
	}

	/**
	 * Définie le cache pour le range à traiter
	 * @return void
	 */
	public function setCacheRange() {
		$_aFormatedEvents = $this->formatRangeEvents();
		cache::set('iCalendar::'.$this->getId().'::PeriodeEvents', json_encode($_aFormatedEvents), 0);
	}

	/**
	 * Récupère et traite les données du fichier ICS
	 * @param array $_p option de la fonction
	 * @return array, tableau des événement
	 */
	public function formatRangeEvents($_p=array()) {
		// instancie la class et initialise avec le contenu du fichier //
		if (($this->_icsContents==false)||(empty($this->_icsContents))) {
			$this->_icsContents = file_get_contents($this->getFileCacheName(true));
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] formatRangeEvents()._icsContents is regerate by cache file');
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] formatRangeEvents() start initialise ICal class');
		$_sTs = getmicrotime();
		$_oICal = new ICalReaderExt();
		$_oICal->initByString($this->_icsContents);
		$_calendarName = $_oICal->calendarNameExt();
		$this->setConfiguration('isGoogleCal',$_oICal->isGoogleCalendar()); // cette action n'est mis à jour que lors d'un "save", et non lors d'un cron30 //
		if (($this->getConfiguration('icsCalendarName')=='')||($this->getConfiguration('icsCalendarName')!=$_calendarName)) {
			$this->setConfiguration('icsCalendarName',$_calendarName);
			if (!$this->_isFromPreSave) {
				$_isSimpleSaveBack = $this->_isSimpleSave;
				$this->_isSimpleSave = true;
				$this->save();
				$this->_isSimpleSave = $_isSimpleSaveBack;
			}
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] formatRangeEvents() ICal Class is initialised (time='.round((getmicrotime()-$_sTs),3).'sec)');
		// récupère l'information de la période //
		$_dStart = (isset($_p['dStart']))?$_p['dStart']:$this->getCacheDateStart();
		$_dEnd = (isset($_p['dEnd']))?$_p['dEnd']:$this->getCacheDateEnd();
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] formatRangeEvents().periodeWorking='.$this->getConfiguration('periodeWorking',0).' | _dStart='.$_dStart.' ('.date('Y-m-d H:i:s',$_dStart).') | _dEnd='.$_dEnd .' ('.date('Y-m-d H:i:s',$_dEnd).')');
		$_aPeriodeEvents = $_oICal->getEventsFromRange($this, $_dStart, $_dEnd);
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] formatRangeEvents() return Period Events ('.$_oICal->event_count.'|'.count($_aPeriodeEvents).')');
		if ($_aPeriodeEvents===false) {
			$this->_log->add($this->logFN(), 'warning', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] formatRangeEvents() ERROR with formated events array ! // STOP');
			return array();
		}
		// format les données et les mets en cache //
		$_aFormatedEvents = array();
		$_eventAdded = array();
		$_tmpId=0;
		foreach ($_aPeriodeEvents as $_aOneEvent) {
			$_key = $_keyBak = $_add = false;
			$_DUPDATE = isset($_aOneEvent['LAST-MODIFIED'])?$_oICal->convertDate2TsWithTZ($_aOneEvent['LAST-MODIFIED']):0;
			$_tmpUID = date('Ymd',$_aOneEvent['TSSTART']).'-'.$_aOneEvent['UID'];
          	// vérifie l'existance de doublon ou changement de paramètre dans l'agenda (multipliant les évènements) //
			if (($_inarray=in_array($_tmpUID,$_eventAdded))==false) {
				$_add=true;
				$_key=$_tmpId++;
				$_eventAdded[$_key] = $_tmpUID;
			} else {
				$_key = array_search($_tmpUID,$_eventAdded);
				// autorise si la date de début et fin sont différents (car exemple: événement récurent démarrant le soir et se terminant le lendemain //
				if (($_aOneEvent['TSSTART'] != $_aFormatedEvents[$_key]['dStart'])
					&&($_aOneEvent['TSEND'] != $_aFormatedEvents[$_key]['dEnd'])) {
						$_add=true;
                  		$_keyBak=$_key;
						$_key=$_tmpId++;
				}
				// action doublon avec date update plus rencente //
				if ((isset($_aFormatedEvents[$_key]))&&($_DUPDATE > $_aFormatedEvents[$_key]['dLastUp'])) {
					$_add=true;
                  	if ($_keyBak!==false) $_key = $_keyBak;
				}
			}
			//log::add('iCalendar','debug','uid='.$_tmpUID.' | '.$_aOneEvent['SUMMARY'].' | inarray='.$_inarray.' | add='.$_add.' | key='.$_key.' | id='.$_tmpId);
			if ($_add) {
				$_aFormatedEvents[$_key] = array(
						'title'=>str_replace('\;',';',$_aOneEvent['SUMMARY']), 'uid'=>$_aOneEvent['UID'], 
						'dLastUp'=> $_DUPDATE, 'dStart'=>$_aOneEvent['TSSTART'], 'dEnd'=>$_aOneEvent['TSEND'],
						'description'=>(isset($_aOneEvent['DESCRIPTION'])?$_aOneEvent['DESCRIPTION']:''), 
						'loc'=>(isset($_aOneEvent['LOCATION'])?$_aOneEvent['LOCATION']:''));
			} else {
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] formatRangeEvents() event "'.$_aOneEvent['SUMMARY'].'" ('.$_aOneEvent['UID'].') already declared with update date less (this='.date('Y-m-d H:i:s',$_DUPDATE).' |other='.date('Y-m-d H:i:s',$_aFormatedEvents[$_key]['dLastUp']).'); no more action.');
			}
		}
		return $_aFormatedEvents;
	}
	
	/**
	 * définit le nom du fichier ics sauvegardé en cache
	 * @param boolean _withPath définit si le chemin doit être retourné aussi
	 * @return string
	 */
	public function getFileCacheName($_withPath=false) {
		if ($this->_fileCacheName=='') {
			if (($_originId=intval($this->getConfiguration("originalCmdId",0)))>0) {
				$_oCmd = $this->getEqLogic()->getCmd('info', 'iCal-'.$_originId.'-J0');
				if (!is_object($_oCmd)) {
					$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getFileCacheName() ERROR: '.__("L'id de l'agenda initial n'a pas put être identifié; impossible de traiter cette journée complémentaire",__FILE__).'. Agenda="'.$this->getName().'", originalId='.$_originId);
					return false;
				}
			} else {
				$_oCmd = $this;
			}
			$_prevDate = olindoteToolsICAL::convertDate($_oCmd->getConfiguration('dateSaveFile'),'ONLYNUMBER');
			$this->_fileCacheName = 'iCal'.$_oCmd->getId().'-'.$_prevDate.'.tmp.ics';
		}
		if (($_withPath)&&($this->_fileCacheNameWPath=='')) { $this->_fileCacheNameWPath = ICALENDAR_CACHE_PATH.$this->_fileCacheName; }
		return ($_withPath)?$this->_fileCacheNameWPath:$this->_fileCacheName;
	}
	
	/**
	 * Définie l'heure de la prochaine synchro
	 * @return string
	 */
	public function getNextDateSynchro() {
		$_next = 0;
		$_now = time();
		$_refreshP = $this->getConfiguration('refreshPeriod','30');
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getNextDateSynchro() refreshPeriod='.$_refreshP);
		if ($_refreshP == '30') {
			if (($_m=date('i',$_now)) < 30) $_next = $_now+((30-$_m)*60);
				else $_next = $_now+((60-$_m)*60);
		} elseif ($_refreshP == '1440') {
			$_next = mktime('00','00','00', $_now) + (1440*60);
		} else {
			$_refreshP = $_refreshP/60;
			$_h=$_refreshP-(date('H',$_now)%$_refreshP);	// (modulo=nb heure passée après la précédente synchro);
			$_next= $_now + ($_h*60*60) - (date('i',$_now)*60);
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getNextDateSynchro() _next='.$_next);
		return $_next;
	}
	
	/**
	 * Gestion du fichier ics icalendar
	 * @param boolean _forceUpdate définie si la mise à jour du fichier doit être forcée ou non
	 * @return void
	 */
	public function manageICSFile($_forceUpdate=false) {
		$_error = false;
		$_saveFile = true;
		$_removeOld = false;
		$_now = time(); 
		if (!$this->_isFromPreSave) $this->_isSimpleSave = true;
		$this->setConfiguration('dateSyncLast',date('Y-m-d H:i:s',$_now));
		if (($_icsContents = $this->getICSFile()) != false) {
			$this->_icsContents = $_icsContents;
			// vérifie si le contenu est bien un fichie ICS //
			if ($this->isICSFileContent($_icsContents)) {
				// nom du fichier actuel //
				$_prevName = $this->getFileCacheName(true);
				if (!$_forceUpdate) {
					// vérifie avec l'ancien fichier //
					if (file_exists($_prevName)) {
						$_prevContents = ICalReaderExt::removeDTSTAMP(file_get_contents($_prevName));
						$_newContents = ICalReaderExt::removeDTSTAMP($_icsContents);
						if ($_prevContents == $_newContents) {
							$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] manageICSFile() previous and new contents ics file is the same, no save new file.');
							$_saveFile = false;
						} else {
							$_removeOld = true;
						}
					} else {
						$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] manageICSFile() previous file not exist ('.$_prevName.')');
					}
				} else {
					$_saveFile = $_removeOld = true;
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] manageICSFile() force update cache file');
				}
			} else {
				$_saveFile = $_removeOld = false;
				$_msg = __('Le contenu retourné n\'est pas au format ICS pour cet agenda. Maintient du fichier en cache (s\'il existe).',__FILE__);
				$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] manageICSFile(): ERROR: '.$_msg);
				message::add('iCalendar', $this->getName().': '.$_msg);
			}
			// sauvegarde le fichier (et supprimer l'ancien) //
			if ($_saveFile) {
				if ($this->saveICSFile('iCal'.$this->getId().'-'.date('YmdHis',$_now).'.tmp.ics', $_icsContents)) {
					$this->setConfiguration('dateSaveFile',date('Y-m-d H:i:s',$_now));
					// vide le cache des paramètres //
					cache::set('iCalendar::'.$this->getId().'::EventsActionsList', '', 0);
					// supprime l'ancien fichier //
					if ($_removeOld) {
						// supprime cache ics //
						if (file_exists($_prevName)) {
							$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] manageICSFile() delete previous ics file ('.$_prevName.')');
							if (!unlink($_prevName)) $_error = true;
						}
						// supprime cache json //
						$_fnInCache = $this->getConfiguration('jsonCacheFN','');
						if ((!empty($_fnInCache))&&(file_exists(ICALENDAR_CACHE_PATH.$_fnInCache))) {						
							$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] manageICSFile() delete previous json file ('.ICALENDAR_CACHE_PATH.$_fnInCache.')');
							if (!unlink(ICALENDAR_CACHE_PATH.$_fnInCache)) $this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] manageICSFile() ERROR where deleting json file !');
						}
					}
				} else { $_error = true; }
			}
		} else { $_error = true; }
		// définie la prochaine date de synchro //
		$this->setConfiguration('dateSyncNext',date('Y-m-d H:i:00',$this->getNextDateSynchro()));
		if (!$this->_isFromPreSave) $this->save();
		if (($this->_isFromPreSave)&&($_error)) {
			$_msg = __("Une erreur a été détectée lors de la synchro de l'agenda, consultez la log",__FILE__);
			message::add('iCalendar', $this->getName().': '.$_msg);
		}
	}

	/**
	 * Vérifie si le contenu est bien au format "VCALENDAR"
	 * @param string $_sICalContents contenu de l'agenda
	 * @return boolean
	 */
	public function isICSFileContent($_sICalContents='') {
		$_sICalContents = trim($_sICalContents);
		if ($_sICalContents != '') {
			if ((substr($_sICalContents,0,15)==ICalReaderExt::VCALENDAR_BEGIN)&&(substr($_sICalContents,-13)==ICalReaderExt::VCALENDAR_END)) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Détermine le type de connexion à réaliser pour atteindre le fichier ICS
	 * @return string or false
	 */
	public function getICSFile() {
		$_return = false;
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFile() starting ...');
		switch ($this->getConfiguration('iCalendarType','')) {
			case "caldav_read": $_return = $this->getICSFileCaldav(); break;
			case "ics_down_file": 
			default: $_return = $this->getICSFileDonwload(); break;
		}
		return $_return;
	}

	/**
	 * Récupère le contenu ICS d'un agenda CalDav
	 * @return string or false
	 */
	public function getICSFileCaldav() {
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileCaldav() starting ...');
		$_oSCDC = new SimpleCalDAVClientExt();
		if ((($_sURL = $this->getConfiguration('iCalendarUrl',''))!='') 
			&& (($_sUser = $this->getConfiguration('calDavUser',''))!='')
			&& (($_sPwd = $this->getConfiguration('calDavPwd',''))!='') ) {
				// récupère l'information de la période //
				$_tsStart = $this->getCacheDateStart(true);
				$_dStart = olindoteToolsICAL::convertDate($_tsStart,'TS2ICS');
				$_tsEnd = $this->getCacheDateEnd(true);
				$_dEnd = olindoteToolsICAL::convertDate($this->getCacheDateEnd(),'TS2ICS');
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileCaldav(): Date Start='.$_tsStart.' | '.date('Y-m-d H:i:s',$_tsStart).' | '.$_dStart);
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileCaldav(): Date End='.$_tsEnd.' | '.date('Y-m-d H:i:s',$_tsEnd).' | '.$_dEnd);
				try {
					$_oSCDC->connect($_sURL, $_sUser, $_sPwd);
				}
				catch (Exception $e) {
					$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileCaldav(): ERROR: '.$e->__toString());
					$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileCaldav(): ERROR: '.__("La connexion n'a pas put être réalisée, pour le flux suivant", __FILE__).': '. $_sURL);
					$this->setHealthNetwork(array(date('Y-m-d H:i:s')=>'X'));
					$this->_errorNetwork = true;
					return false;
				}
				$this->setHealthNetwork(array(date('Y-m-d H:i:s')=>'o'));
				$this->_errorNetwork = false;
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileCaldav(): connexion info='.print_r($_oSCDC,true));
				$_oSCDC->getAndSetCalendar($this, $this->getConfiguration('calDavCalName',''));
				$_oCalDavRes = $_oSCDC->getEvents($_dStart,$_dEnd);
				$_data = $_oSCDC->compileEventInICSFile($_oCalDavRes);
				//$_data = (isset($_oCalDavRes[0]))?$_oCalDavRes[0]->getData():false;
				if ($_data=='') {
					$_data = ICalReaderExt::VCALENDAR_BEGIN."\r\n".ICalReaderExt::VCALENDAR_END;
				}
				return $_data;
		} else {
			$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileCaldav(): ERROR: '.__('Votre nom d\'utilisateur et/ou votre mot de passe CalDav sont vides. Merci de vérifier.',__FILE__));
		}
		return false;
	}

	/** 
	 * Construit l'url iCalendar, récupère le contenu du fichier ics
	 * @return string or false
	 */
	public function getICSFileDonwload() {
		$_sTs=getmicrotime();
		// récupère l'URL de l'agenda //
		if (($_sURL = $this->getConfiguration('iCalendarUrl','')) != '') {
			// définie les paramètres de timeout //
			//DEL//$_auth = base64_encode('abarrau@priv8.eu:JeedomIsForFun');
			//DEL//$_sCtxt = stream_context_create(array('http'=>array('timeout'=>5,'header'=>"Authorization: Basic $_auth"),'https'=>array('timeout'=>5,'header'=>"Authorization: Basic $_auth"))); // JEEDOM est à 10 secondes (on dirait ?) //			
			//DEL//$_sCtxt = stream_context_create(array('http'=>array('timeout'=>5),'https'=>array('timeout'=>5))); // JEEDOM est à 10 secondes (on dirait ?) //
			$_fData = '';
			// recupère le contenu du flux pour le mettre dans un fichier //
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileDonwload() send request (file_get_contents) at : '.$_sURL);
			//DEL//$_fData = file_get_contents($_sURL, null, $_sCtxt);
            $_ch = curl_init();
    		curl_setopt($_ch, CURLOPT_HEADER, false);
            curl_setopt($_ch, CURLOPT_RETURNTRANSFER, true);
    		curl_setopt($_ch, CURLOPT_URL, $_sURL);
			curl_setopt($_ch,CURLOPT_TIMEOUT, config::byKey('olindote::synchro::timeout','iCalendar',5));
			if (substr($_sURL,0,6)=='https:') {
				curl_setopt($_ch, CURLOPT_SSL_VERIFYPEER, false);
			}
    		$_fData = curl_exec($_ch);
            curl_close($_ch);
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileDonwload() request back, analyse it');
			if (($_fData === false) || ($_fData=='')) {
				$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileDonwload(): ERROR: '.__("Le flux suivant n'est pas accéssible", __FILE__).' (error/timeout):'. $_sURL);
				$this->setHealthNetwork(array(date('Y-m-d H:i:s')=>'X'));
				$this->_errorNetwork = true;
				return false;
			} else {
				$this->setHealthNetwork(array(date('Y-m-d H:i:s')=>'o'));
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileDonwload() return contents; time='.round((getmicrotime()-$_sTs),4).'sec.');
				return $_fData;
			}
		} else {
			$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileDonwload(): ERROR: '.__("L'adresse url de l'agenda suivant n'est pas définie", __FILE__).' : '. $this->getName());
			return false; 
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getICSFileDonwload(): ERROR: catch ... ?');
		return false;
	}
	
	/**
	 * Sauvegarde dans un fichier
	 * @param string $_sFileName nom du fichier à créer
	 * @param string $_sICalContents contenu de l'agenda
	 * @return boolean
	 */
	public function saveICSFile($_sFileName, $_sICalContents) {
		if ($_sICalContents != '') {
			if (!file_exists(ICALENDAR_CACHE_PATH)) {
				iCalendarTools::setCacheDir();
			}
			if (file_put_contents(ICALENDAR_CACHE_PATH . $_sFileName, $_sICalContents) === false) {
				$this->_log->add($this->logFN(), 'error', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveICSFile(): '.__("Ecriture impossible dans le fichier (vérifier vos droits sur le répertoire)", __FILE__).': '. iCalendar_CACHE_PATH . $_sFileName);
				return false;
			}
			$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveICSFile(): '.__("Mise à jour du fichier en cache", __FILE__).': '. ICALENDAR_CACHE_PATH . $_sFileName);
			return true;
		} else {
			$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveICSFile(): ERROR: '.__("Le contenu à sauvegarder est vide, le fichier suivant n'est pas sauvegardé ", __FILE__).' : '. $this->getName());
			return false;
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveICSFile(): ERROR: catch ... ?');
		return false;
	}
	
	/**
	 * Met à jour l'état de la santé du réseau
	 * @param array $_aStat tableau avec l'état et la date : 'date'=>'statut'
	 * @return void
	 */
	public function setHealthNetwork($_aStat) {
		return olindoteToolsICAL::setHealthNetwork($_aStat, $this, 'iCalendar');
	}

	/**
	 * Ajoute la liste des actions traités dans l'historique
	 * @param string $_value valeur à intégrer dans l'historique
	 * @param string $_datetime valeur de la date
	 * @return boolean
	 */
	public function addHistoryAction($_value, $_datetime='') {
		if ($this->getConfiguration('actionIsHistorized','0')=='1') {
			if ($_datetime=='') {
				$_datetime = date('Y-m-d 00:00:00');
			}
			if (($_value!='')&&(count($_value)>0)) {
				$sqlVal = array( 'cmd_id' => $this->getId(), 'datetime' => $_datetime, 'value' => $_value );
				$sql = 'REPLACE INTO `iCalendar_Actions`
					SET `cmd_id`=:cmd_id,
					`datetime`=:datetime,
					`value`=:value';
				DB::Prepare($sql, $sqlVal, DB::FETCH_TYPE_ROW);
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Récupère la liste des actions dans l'historique
	 * @param array $_p valeur liste des paramètres à passer
	 * @return array
	 */
	public function getHistoryActions($_p=array()) {
		$_dStart = (!isset($_p['dStart']))?date('Y-m-d 00:00:00'):$_p['dStart'];
		$_dEnd = (!isset($_p['dEnd']))?date('Y-m-d H:i:59'):$_p['dEnd'];
		$_cmdId = (!isset($_p['cmdId']))?0:$_p['cmdId'];
		$_withCache = (!isset($_p['withCache']))?0:$_p['withCache'];
		$_aResActions = array();
		// données histiroque (en bdd) //
		$sqlVal = array( 'cmd_id' => $_cmdId, 'dStart' => $_dStart, 'dEnd' => $_dEnd );
		$sql = 'SELECT `value`, `datetime`, `cmd_id`
			FROM `iCalendar_Actions`
			WHERE `cmd_id`=:cmd_id
				AND (`datetime`>=:dStart AND `datetime`<=:dEnd)';
		if (($_actionsROW = DB::Prepare($sql, $sqlVal, DB::FETCH_TYPE_ALL))!==false) {
			foreach ($_actionsROW as $_action) {
				$_dt = str_replace(' 00:00:00','',$_action['datetime']);
				$_aResActions[$_dt]['dayH'] = olindoteToolsICAL::convertDate($_action['datetime'],'DAYHUMAN');
				$_aResActions[$_dt]['list'] = $_action['value'];
			}
			// données du jour (en cache) //
			if ($_withCache==1) {
				$_aEventActionsState = json_decode(cache::byKey('iCalendar::'.$_cmdId.'::EventActionsState')->getValue(),true);
				if (count($_aEventActionsState)>0) {
					foreach($_aEventActionsState as $_ts => $_actions) {
						$_dt = olindoteToolsICAL::convertDate($_ts,'DAYJEEDOM');
						$_aResActions[$_dt]['dayH'] = olindoteToolsICAL::convertDate($_dt,'DAYHUMAN');
						$_aResActions[$_dt]['list'] = json_encode($_actions);
					}
				}
			}
			krsort($_aResActions);
			return $_aResActions;
		}
		return false;
	}
	
	/**
	 * Récupère la liste des actions dans l'historique
	 * @param array $_p valeur liste des paramètres à passer
	 * @return array
	 */
	public function deleteHistoryActions($_p=array()) {
		$_dtDelete = (isset($_p['dtDelete']))?$_p['dtDelete']:false;
		$_cmdId = (!isset($_p['cmdId']))?0:$_p['cmdId'];
		if (($_dtDelete != false)&&($_dtDelete != '')) {
			if ($_dtDelete == date('Y-m-d', $this->_tsRef)) {
				$_aEventActionsState = json_decode(cache::byKey('iCalendar::'.$_cmdId.'::EventActionsState')->getValue(),true);
				foreach($_aEventActionsState as $_ts => $_actions) {
					if ($_ts == date('Ymd', $this->_tsRef)) {
						unset($_aEventActionsState[$_ts]);
						cache::set('iCalendar::'.$_cmdId.'::EventActionsState', json_encode($_aEventActionsState), 0);
						$this->_log->add($this->logFN(), 'debug', '[' .$this->_whatLog.'|'. $this->getEqLogic()->getId() . '|' . $_cmdId . '] deleteHistoryActions() delete actions list in cache for day = '.$_dtDelete);
						break;
					}
				}
				return true;
			} else {
				// données historique (en bdd) //
				$sqlVal = array( 'cmd_id' => $_cmdId, 'dtDelete' => $_dtDelete );
				$sql = 'DELETE FROM `iCalendar_Actions`
						WHERE `cmd_id`=:cmd_id
							AND `datetime`=:dtDelete';
				$res = DB::Prepare($sql, $sqlVal, DB::FETCH_TYPE_ROW);
				$this->_log->add($this->logFN(), 'debug', '[' .$this->_whatLog.'|'. $this->getEqLogic()->getId() . '|' . $_cmdId . '] deleteHistoryActions() delete actions list in history table for day = '.$_dtDelete);
				return true;
			}
		}
      	return false;
    }

	/**
	 * Définie le nom d'un fichier cache Jsom (pour le calendrier/panel)
	 * @param array $_p valeur liste des paramètres à passer
	 * @return string nom du fichier
	 */
	public function getFileCacheNameEventsInCalendar($_p=array()) {
		return 'iCal'.$this->getId().'-'.$_p['dStart'].'-'.$_p['dEnd'].'.tmp.json';
	}
	
	/**
	 * Récupère les événements pour le calendrier (panel) au format json
	 * @param array $_p valeur liste des paramètres à passer
	 * @return json
	 */
	public function getEventsInCalendar($_p=array()) {
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getEventsInCalendar() start initialise data');
		// recherche l'existance d'un fichier en cache //
		$_jsonCacheContents = '';
		$_fnInCache = $this->getConfiguration('jsonCacheFN','');
		if ((!empty($_fnInCache))&&(file_exists(ICALENDAR_CACHE_PATH.$_fnInCache))) {
			list($_z,$_dS,$_dE) = explode('-', str_replace('.tmp.json','',$_fnInCache));
			if (($_dS <= $_p['dStart'])&&($_dE<=$_p['dEnd'])) {
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getEventsInCalendar() return the cache content in file='.ICALENDAR_CACHE_PATH.$_fnInCache);
				return file_get_contents(ICALENDAR_CACHE_PATH.$_fnInCache);
			} else {
				if (!unlink(ICALENDAR_CACHE_PATH.$_fnInCache)) {
					$this->_log->add($this->logFN(), 'debug', '[' .$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getEventsInCalendar(): ERROR: '.__('Echec lors de la suppression du fichier', __FILE__) . ': '. $_fnInCache);
				} else {
					$this->_log->add($this->logFN(), 'info', '[' .$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getEventsInCalendar(): '.__('Fichier supprimé', __FILE__) . ': '. $_fnInCache);
				}
			}
		}
		// sinon récupérère les informations et crée un nouveau fichier en cache //
		$_aPeriodeEvents = $this->formatRangeEvents($_p);
		if (count($_aPeriodeEvents) > 0) $_jsonCacheContents = iCalendarTools::toJsonForFullCalendar($_aPeriodeEvents, $this->_tsRef);
		if (!empty($_jsonCacheContents)) {
			$_newFnInCache = $this->getFileCacheNameEventsInCalendar($_p);
			if (!file_exists(ICALENDAR_CACHE_PATH.$_newFnInCache)) {
				$this->saveJSONFile($_newFnInCache, $_jsonCacheContents);
				$this->setConfiguration('jsonCacheFN',$_newFnInCache);
				$this->_isSimpleSave = true;
				$this->save();
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getEventsInCalendar() json content is save in cache file : '.ICALENDAR_CACHE_PATH.$_newFnInCache);
			} else {
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getEventsInCalendar() ERROR: file cache already exist : '.ICALENDAR_CACHE_PATH.$_newFnInCache);
			}
		} else {
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] getEventsInCalendar() jsonContent is empty, do nothing');
		}
		return $_jsonCacheContents;
	}

	/**
	 * Sauvegarde dans un fichier JSOM
	 * @param string $_sFileName nom du fichier à créer
	 * @param string $_sJSONContents contenu de l'agenda
	 * @return boolean
	 */
	public function saveJSONFile($_sFileName, $_sJSONContents) {
		if ($_sJSONContents != '') {
			if (!file_exists(ICALENDAR_CACHE_PATH)) {
				iCalendarTools::setCacheDir();
			}
			if (file_put_contents(ICALENDAR_CACHE_PATH . $_sFileName, $_sJSONContents) === false) {
				$this->_log->add($this->logFN(), 'error', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveJSONFile(): '.__("Ecriture impossible dans le fichier (vérifier vos droits sur le répertoire)", __FILE__).': '. iCalendar_CACHE_PATH . $_sFileName);
				return false;
			}
			$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveJSONFile(): '.__("Mise à jour du fichier en cache", __FILE__).': '. ICALENDAR_CACHE_PATH . $_sFileName);
			return true;
		} else {
			$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveJSONFile(): ERROR: '.__("Le contenu à sauvegarder est vide, le fichier suivant n'est pas sauvegardé ", __FILE__).' : '. $this->getName());
			return false;
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] saveJSONFile(): ERROR: catch ... ?');
		return false;
	}
		
	// ===== Extended function for commande by scenario ===== //
	/**
	 * Retourne la valeur d'un objet d'un événement
	 * @param string $_key valeur de la clé à comparer 
	 * @param string $_keyType type de clé à comparer (titre ou id)
	 * @param string $_what valeur à retourner (heure début, fin, titre, uid)
	 * @param string $_dDay définie le "jour" à utiliser pour la recherche
	 * @param string $_dFormat définie le format à utiliser pour la date
	 * @return string retourne la valeur trouvée ou -1
	 */
	public function Sc_getValueInEvent($_key,$_keyType='title',$_what='',$_dDay='0', $_dFormat='U') {
		$_var = -1;
		$_aWhat = array('title','uid','dStart','dEnd','loc');
		if (($_key!='')&&($_keyType!='')&&($_what!='')&&(in_array($_what,$_aWhat))) {
			$_aCurrentCachePeriode = json_decode(cache::byKey('iCalendar::'.$this->getConfiguration('originalCmdId').'::PeriodeEvents')->getValue());
			if (count($_aCurrentCachePeriode)==0) {
				$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getValueInEvent('.$_key.','.$_keyType.','.$_what.','.$_dDay.') _aCurrentCachePeriode is empty, not action');
			} else {
				if(intval($_dDay) > intval($this->getConfiguration('periodeWorking',0))) {
					$this->_log->add($this->logFN(), 'warning', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getValueInEvent(): '.__('Le jour passé en paramètre n\'est pas dans la plage de la "période à traiter" configurée.',__FILE__).' (_dDay='.$_dDay.')');
					$_dDay = 0;
				}
				$_dWorkN = mktime('0','0','0') + ((60*60*24)*$_dDay);
				$_dWorkE = mktime('23','59','59') + ((60*60*24)*$_dDay);
				foreach ($_aCurrentCachePeriode	as $_aOneEvent) {
					if (($_var==-1) && (!empty($_aOneEvent->dStart)) && (!empty($_aOneEvent->dEnd))) {
						//$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getValueInEvent() title='.$_aOneEvent->title.' | dStart='.date('d/m H:i',$_aOneEvent->dStart).' | dWorkN='.date('d/m H:i',$_dWorkN).' | dWorkE='.date('d/m H:i',$_dWorkE).' | dEnd='.date('d/m H:i',$_aOneEvent->dEnd));
						if (($_aOneEvent->dStart < $_dWorkE)&&($_aOneEvent->dEnd > $_dWorkN)) {
							$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getValueInEvent() title='.$_aOneEvent->title.' event is in work');
							if (trim($_key)==trim($_aOneEvent->$_keyType)) {
								$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getValueInEvent('.$_key.','.$_keyType.','.$_what.','.$_dDay.') compare is find for this event = '.print_r($_aOneEvent,true));
								if ((($_what=='dStart')||($_what=='dEnd'))&&($_dFormat!='U')) {
									$_var = date($_dFormat,$_aOneEvent->$_what);
								} else {
									$_var = $_aOneEvent->$_what;
								}
								break;
							}
						}
					}
				}
			}
		} else {
			$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getValueInEvent('.$_key.','.$_keyType.','.$_what.','.$_dDay.') arguments is not set');
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getValueInEvent('.$_key.','.$_keyType.','.$_what.','.$_dDay.') return value = "'.$_var.'"');
		return $_var;
	}
	
	/**
	 * Retourne la valeur d'un objet d'un événement
	 * @param int $_sDay définie le jour à remonter. 
	 * @param boolean $_titleOnly définie si l'on souhaite afficher uniquement les titres
	 * @param boolean $_actifOnly définie si l'on souhaite afficher uniquement les événements actifs
	 * @return string retourne la nouvelle trame
	 */
	public function Sc_getDayNewTrame($_sDay=0, $_titleOnly=false,$_actifOnly=false,$_separ='||') {
		$_sCmdVal = '';
		$_do = true;
		if (($_sDay>=0)&&($_sDay<=6)) {
			$_oCmd = cmd::byLogicalId('iCal-'.$this->getConfiguration('originalCmdId').'-J'.$_sDay);
			if ((is_array($_oCmd))&&(isset($_oCmd[0]))) $_oCmd = $_oCmd[0];
			if (!is_object($_oCmd)) {
				$this->_log->add($this->logFN(), 'warning', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getDayNewTrame(_sDay='.$_sDay.') ERROR: cmd (iCal-'.$this->getConfiguration('originalCmdId').'-J'.$_sDay.') can not be found. // STOP');
				return -1;
			}
		} else {
			$this->_log->add($this->logFN(), 'warning', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getDayNewTrame(_sDay='.$_sDay.') ERROR: with the value of the day (normaly between 0 and 6). // STOP');
			return -1;
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getDayNewTrame(_sDay='.$_sDay.') _oCmd='.print_r($_oCmd,true));
		if ($_oCmd->getConfiguration('viewStyle','0')=='current') {
			if ($_actifOnly) {
				$_sCmdVal = $_oCmd->execCmd();
				$_do = false;
			} else {
				$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getDayNewTrame('.(($_titleOnly)?'titleOnly':'-').','.(($_actifOnly)?'actifOnly':'-').') ERROR: '.__('Votre commande est configurée en "évenement courrant", retourner la journée complète n\'est pas possible.',__FILE__));
				$_do = false;
			}
			if ($_oCmd->getConfiguration('titleOnly','0')=='1') {
				if ($_titleOnly) {
					$_sCmdVal = $_oCmd->execCmd();
					$_do = false;
				} else {
					$this->_log->add($this->logFN(), 'info', '['.$this->_whatLog.'|'.$this->getEqLogic()->getId().'|'.$this->getId().'] Sc_getDayNewTrame('.(($_titleOnly)?'titleOnly':'-').','.(($_actifOnly)?'actifOnly':'-').') ERROR: '.__('Votre commande est déjà configurée en "titre uniquement", votre demande n\'est pas possible.',__FILE__));
					$_do = false;
				}
			}
		}
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getEqLogic()->getId() . '|' . $this->getId() . '] Sc_getDayNewTrame('.(($_titleOnly)?'titleOnly':'-').','.(($_actifOnly)?'actifOnly':'-').') _do='.(($_do)?'true':'false'));
		if ($_do) {
			$_sExecCmd = $_oCmd->execCmd();
			if ($_sExecCmd != '') {
				$_aEvents = iCalendarTools::eventsList2array($_sExecCmd, $_oCmd->getConfiguration('defaultValue', ICALENDAR_TXT_DV), $_oCmd->getConfiguration('titleOnly','0'));
				foreach ($_aEvents as $_aOneEvent) {
					if ($_actifOnly) {
						if (substr($_aOneEvent['s'],0,1)=='A') {
							$_sCmdVal .= (!$_titleOnly)?$_aOneEvent['hd'].' '.$_aOneEvent['hf'].' ':'';
							$_sCmdVal .= $_aOneEvent['t'].$_separ;
						}
					} else {
						$_sCmdVal .= (!$_titleOnly)?$_aOneEvent['hd'].' '.$_aOneEvent['hf'].' ':'';
						$_sCmdVal .= $_aOneEvent['t'].$_separ;
					}
				}
			}
		}
		$_sCmdVal = (substr($_sCmdVal,-2)==$_separ)?substr($_sCmdVal,0,-2):$_sCmdVal;
		$_sCmdVal = ($_sCmdVal!='')?$_sCmdVal:-1;
		$this->_log->add($this->logFN(), 'debug', '['.$this->_whatLog.'|' . $this->getEqLogic()->getId() . '|' . $this->getId() . '] Sc_getDayNewTrame('.(($_titleOnly)?'titleOnly':'-').','.(($_actifOnly)?'actifOnly':'-').') return value = "'.$_sCmdVal.'"');
		return $_sCmdVal;
	}
	
}

/**
 * -------------------------------------------------------------------------------------------------------------------------
 * Class Tools pour le plugin iCalendar
 * @author abarrau
 */
class iCalendarTools {

	/**
	 * Crée le répertoire cache pour le plugin 
	 * @return void
	 */
	public function setCacheDir($_withExeption=false) {
		if (!file_exists(ICALENDAR_CACHE_PATH)) {
			if (mkdir(ICALENDAR_CACHE_PATH) === true) {
				log::add('iCalendar', 'info', '[iCalendarTools] setCacheDir(): '.__("Le répertoire suivant vient d'être créé", __FILE__).': '.ICALENDAR_CACHE_PATH);
			} else {
				log::add('iCalendar', 'error', '[iCalendarTools] setCacheDir(): '.__("Le répertoire suivant n'a pas put être créé", __FILE__).': '.ICALENDAR_CACHE_PATH);
				if ($_withExeption) throw new Exception(__("Le répertoire suivant n'a pas put être créé", __FILE__).': '.ICALENDAR_CACHE_PATH);
			}
		}
		return true;
	}

	/**
	 * Purge le répertoire temporaire
	 * @return boolean
	 */
	public static function cleanCacheDirectory() {
		log::add('iCalendar', 'debug', '[iCalendarTools] cleanCacheDirectory(): starting ...');
		// purge le répertoire temporaire des fichiers orphelins //
		$_eqLICAL = eqLogic::byType('iCalendar');
		if (count($_eqLICAL) > 0) {
			// récupère les id de chaque eqLogic //
			$_aIdCmd = array();
			foreach ($_eqLICAL as $_objEql) {
				foreach ($_objEql->getCmd('info') as $_objCmd) {
					$_aIdCmd[] = $_objCmd->getId();
				}
			}
			// analyse le répertoire //
			$_aSD = array_diff(scandir(ICALENDAR_CACHE_PATH), array('.','..'));
			log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheDirectory(): '.__('Nettoyage du répertoire temporaire des fichiers orphelins ...',__FILE__));
			foreach ($_aSD as $_f) {
				if (($_f != ".") && ($_f != "..")) {
					$_af = explode('-', $_f);
					$_fID = str_replace('iCal', '', $_af);
					if (!in_array($_fID, $_aIdCmd)) {
						iCalendarTools::cleanCacheFile($_f);
					}
				}
			}
		} else {
			// sinon purge tous les fichiers du répertoire //
			$_aSD = array_diff(scandir(ICALENDAR_CACHE_PATH), array('.','..'));
			log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheDirectory(): '.__('Nettoyage du répertoire temporaire des fichiers ...',__FILE__).' ('.count($_aSD).')');
			foreach ($_aSD as $_f) {
				if (($_f != ".") && ($_f != "..")) {
					iCalendarTools::cleanCacheFile($_f);
				}
			}
			
		}
		// test final pour suppression du répertoire chapeau //
		if (count(array_diff(scandir(ICALENDAR_CACHE_PATH), array('.','..')))==0) {
			if (!rmdir(ICALENDAR_CACHE_PATH)) {
				log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheDirectory(): ERROR: '.__('Echec lors de la suppression du répertoire temporaire du plugin', __FILE__) . ': '. ICALENDAR_CACHE_PATH);
			} else {
				log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheDirectory(): '.__('Répertoire temporaire supprimé', __FILE__) . ': '. ICALENDAR_CACHE_PATH);
			}
		}
	}

	/**
	 * Supprime les fichiers temporaires
	 * @return boolean
	 */
	public static function cleanCacheFile($_f) {
		if (is_dir(ICALENDAR_CACHE_PATH.$_f)) {
			if (!rrmdir(ICALENDAR_CACHE_PATH.$_f)) {
				log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheFile(): ERROR: '.__('Echec lors de la suppression du répertoire', __FILE__) . ': '. $_f);
			} else {
				log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheFile(): '.__('Réperoire supprimé', __FILE__) . ': '. $_f);
			}
		} else {
			if (!unlink(ICALENDAR_CACHE_PATH.$_f)) {
				log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheFile(): ERROR: '.__('Echec lors de la suppression du fichier', __FILE__) . ': '. $_f);
			} else {
				log::add('iCalendar', 'info', '[iCalendarTools] cleanCacheFile(): '.__('Fichier supprimé', __FILE__) . ': '. $_f);
			}
		}
	}

	/**
	 * retourne la valeur "texte" du type de vue
	 * @param string $_sView valeur du paramètre type de vue
	 * @return string valeur en texte lisible du paramètre
	 */
	public function getViewTypeInTxt($_sView) {
		switch ($_sView) {
			case "1day_today": $_sTxt = __('événements sur la journée',__FILE__); break;
			case "1day_next1hour": $_sTxt = __('événements sur heure à venir',__FILE__); break;
			case "current": $_sTxt = __('événements courants',__FILE__); break;
			default : $_sTxt = 'ERROR';
		}
		return $_sTxt;
	}
	
	/**
	 * convertie la string (liste des évenements en array)
	 * @param string $_sEvents valeur du contenu de la commande
	 * @param string $_dfValue valeur par défaut
	 * @param string $_isTitleOnly définie si l'on retourne que les titres
	 * @param boolean $_toJson définie si l'on retourne au format json le tableau
	 * @param boolean $_withIdKey définie si la clé du tableau est le UID
	 *		tableau (NEW) >> heure_debut;heure_fin;statut;titre;uid;actScEvent1/0);update_date
	 *		hd:heure_debut / hf:heure_fin / s:statut / t:title / u:uid / a:action / upd:update_date
	 * @return array
	 */
	public function eventsList2array($_sEvents, $_dfValue='', $_isTitleOnly=false, $_toJson=false, $_withIdKey=false) {
		$_aEvents = array();
		if ($_sEvents == $_dfValue) {
			$_aEvents[] = array ('hd'=>'', 'hf'=>'', 's'=>'', 't'=>$_sEvents, 'u'=>'', 'a'=>'', 'upd'=>'', 'loc'=>'');
		} elseif ($_sEvents!='') {
			$_aOneEvent = explode('||',$_sEvents);
			if ($_isTitleOnly == true) {
				foreach ($_aOneEvent as $_k=>$_v) {
					$_aEvents[] = array ('hd'=>'', 'hf'=>'', 's'=>'', 't'=>$_v, 'u'=>'', 'a'=>'', 'upd'=>'', 'loc'=>'');
				}
			} else {
				foreach ($_aOneEvent as $_k=>$_v) {
					$_aParams = explode(';',$_v);
					if ($_withIdKey) {
						$_id = str_replace(':','',$_aParams[0]).md5($_aParams[4]);
						$_aEvents[$_id] = array ('hd'=>$_aParams[0], 'hf'=>$_aParams[1], 's'=>$_aParams[2], 't'=>$_aParams[3], 
														 'u'=>$_aParams[4], 'a'=>$_aParams[5], 'upd'=>isset($_aParams[6])?$_aParams[6]:'',
														 'loc'=>isset($_aParams[7])?$_aParams[7]:'');
					} else {
						$_aEvents[] = array ('hd'=>$_aParams[0], 'hf'=>$_aParams[1], 's'=>$_aParams[2], 't'=>$_aParams[3], 
											 'u'=>$_aParams[4], 'a'=>$_aParams[5], 'upd'=>isset($_aParams[6])?$_aParams[6]:'',
											 'loc'=>isset($_aParams[7])?$_aParams[7]:'');
					}
				}
			}
		}
		return ($_toJson)?json_encode($_aEvents):$_aEvents;
	}

	/**
	 * convertie le tableau d'un événement en string ordonnancé 
	 * @param array $_aEvent valeur du contenu d'un événement
	 * @return string
	 */
	public function eventArray2EventString($_aEvent, $_isTitleOnly=false) {
		if ($_isTitleOnly==true) {
			$_sEvent = $_aEvent['t'];
		} else {
			$_sEvent = $_aEvent['hd'].';'.$_aEvent['hf'].';'.$_aEvent['s'].';'.$_aEvent['t'].';'.$_aEvent['u'].';'.$_aEvent['a'].';'.(isset($_aEvent['upd'])?$_aEvent['upd']:'').';'.(isset($_aEvent['loc'])?$_aEvent['loc']:'');
		}
		return $_sEvent;
	}
	 
	/**
	 * convertie la valeur de la description en tableau
	 * @param object $_event objet evenement
	 * @param boolean $_toJson défini si l'on retourne au format json le tableau
	 *		quand (DA/FA) ; type (S/A) ; qui (id) ; valeur (variable/valeur)
	 * @return array
	 */
	public function eventDescription2array($_oCmd, $_event, $_toJson=false) {
		$_sDescription = isset($_event->description)?$_event->description:'';
		$_sTitle = isset($_event->title)?$_event->title:'';
		$_aDes = array();
		if ($_sDescription!='') {
			$_aActList = explode('\n',$_sDescription);
			foreach ($_aActList as $_k=>$_v) {
				$_do = false;
				$_aOneAct = explode('|',$_v);
				if (($_aOneAct[0]=='DA')&&($_event->dStart == $_event->dStartNew)) { $_do = true; }
				if (($_aOneAct[0]=='FA')&& ((($_event->dEnd+60)==$_event->dEndNew)||(date('H:i',$_event->dEnd)=='23:59'))) { $_do = true; }
				if ($_do) {
//				if (($_aOneAct[0]=='DA')||($_aOneAct[0]=='FA')) {
					$_aType = (isset($_aOneAct[1]))?explode('=',$_aOneAct[1]):array();
					$_aVal = (isset($_aOneAct[2]))?explode('=',$_aOneAct[2]):array('','');
					if (count($_aType)==2) {
						$_type = ($_aType[0]=='sc')?'S':(($_aType[0]=='act')?'A':'');
						if ($_type!='') {
							$_name = 'ERROR';
							$_eqLType = $_eqLId = '';
							if ($_type=='A') {
								if (strpos($_aType[1], '[')===false) {
									$_id = str_replace('#','',$_aType[1]); 
									$_oCmd = cmd::byId($_id);
									if (is_object($_oCmd)) {
										$_name = str_replace('#','',cmd::cmdToHumanReadable('#'.$_id.'#'));
										$_eqLType = $_oCmd->getEqType_name();
										$_eqLId = $_oCmd->getEqLogic_id();
									} else {
										$_name = 'ERROR';
										$_id = -1;
										$_msg = __('La commande action',__FILE__).' : ['.$_aType[1].'], '.__(' est inconnue pour l événement suivant',__FILE__).' : '.$_sTitle.'. ';
										$_oCmd->_log->add($_oCmd->logFN(), 'info', '[' . $_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId() . '|' . $_oCmd->getId() . '] iCalendarTools::eventDescription2array() ERROR: '. $_msg);
										message::add('iCalendar', $_msg.' '.__('Revoir la configuration de votre événement.',__FILE__));
									}
								} else {
									$_id = str_replace('#','',cmd::humanReadableToCmd($_aType[1]));
									$_name = str_replace('#','',$_aType[1]);
									$_oCmd = cmd::byId($_id);
									if (is_object($_oCmd)) {
										$_eqLType = $_oCmd->getEqType_name();
										$_eqLId = $_oCmd->getEqLogic_id();
									} else {
										$_name = 'ERROR';
										$_id = -1;
										$_msg = __('La commande action',__FILE__).' : ['.$_aType[1].'], '.__(' est inconnue pour l événement suivant',__FILE__).' : '.$_sTitle.'. ';
										$_oCmd->_log->add($_oCmd->logFN(), 'info', '[' . $_oCmd->_whatLog.'|'.$_oCmd->getEqLogic->getId() . '|' . $_oCmd->getId() . '] iCalendarTools::eventDescription2array() ERROR: '. $_msg);
										message::add('iCalendar', $_msg.' '.__('Revoir la configuration de votre événement.',__FILE__));
									}
								}
							} else if($_type=='S') {
								$_oSc = scenario::byId($_aType[1]);
								if (is_object($_oSc)) {
									$_id = $_aType[1];
									$_name = $_oSc->getName();
								} else {
									$_name = 'ERROR';
									$_id = -1;
									$_msg = __('Le scénario',__FILE__).' : ['.$_aType[1].'], '.__(' est inconnue pour l événement suivant',__FILE__).' : '.$_sTitle.'. ';
									$_oCmd->_log->add($_oCmd->logFN(), 'info', '[' . $_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId() . '|' . $_oCmd->getId() . '] iCalendarTools::eventDescription2array() ERROR: '. $_msg);
									message::add('iCalendar', $_msg.' '.__('Revoir la configuration de votre événement.',__FILE__));
								}
							}
							if ($_id != -1) {
								$_sVal = (isset($_aVal[1]))?$_aVal[1]:'';
								$_idAct = $_type.$_id.$_aOneAct[0].date('mdHi',$_event->dStart).'-'.md5($_event->uid);
								if ($_aOneAct[0]=='DA') { 
									$_tsWhen = $_event->dStart;
								} else {
									$_tsWhen = $_event->dEnd; //(date('H:i',$_event->dEnd)!='23:59')?intval($_event->dEnd-60):$_event->dEnd;
								}
								$_aDes[$_idAct] = array('uid'=>$_event->uid, 'idAct'=>$_idAct, 'when'=>$_aOneAct[0], 'tsWhen'=>$_tsWhen, 'tsWhenTimeHuman'=>date('H:i',$_tsWhen), 'tsWhenDateHuman'=>date('d/m',$_tsWhen), 't'=>$_type, 'id'=>$_id, 'name'=>$_name, 'eqLType'=>$_eqLType, 'eqLId'=>$_eqLId, 'var'=>$_aVal[0],'val'=>$_sVal);
							}
						} else {
							$_oCmd->_log->add($_oCmd->logFN(), 'info', '[' . $_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId() . '|' . $_oCmd->getId() . '] iCalendarTools::eventDescription2array() ERROR: type of action was not correctly define for uid='.$_event['u']);
						}
					}
				}
			}
		}
		return ($_toJson)?json_encode($_aDes):$_aDes;
	}

	/**
	 * Retourne un événement en fonction de son id (uid)
	 * @param string $_sEvents événement au format texte (issue de la commande)
	 * @param string $_uid id à chercher
	 * @return string événemement au format texte
	 */
	public function getEventByUid($_sEvents, $_uid) {
		$_res = false;
		$_aEvents = iCalendarTools::eventsList2array($_sEvents);
		for ($_i=0;$_i<count($_aEvents);$_i++) {
			if ($_aEvents[$_i]['u']==$_uid) {
				$_res = $_aEvents[$_i];
				break;
			}
		}
		return $_res;
	}
	
	/**
	 * récupère l'id privé de l'agenda google
	 * @param string $_s url du fichier ics
	 * @return string id privé
	 */
	public function getPrivateIdGoogleCal($_s) {
		$_a = explode('/',str_replace('\\','/',$_s));
		return isset($_a[5])?$_a[5]:false;
	}

	/**
	 * Retourne la structure au format json
	 * @param array $_aEventsRange tableau des événements à convertir
	 * @param string $_now 
	 * @return json objet json
	 */
	public function toJsonForFullCalendar($_aEventsRange, $_now) {
		$_json = array();
		if (count($_aEventsRange)>0) {
			foreach ($_aEventsRange as $_oneEvent) {
				if (isset($_oneEvent['title']) && isset($_oneEvent['dStart']) && isset($_oneEvent['dEnd'])) {
					$_json[] = array('title'=>$_oneEvent['title'],
									 'start'=>olindoteToolsICAL::convertDate($_oneEvent['dStart'],'TS2FC'), 
									 'end'=>olindoteToolsICAL::convertDate($_oneEvent['dEnd'],'TS2FC'),
									 'loc'=>$_oneEvent['loc'],
									 'description'=>$_oneEvent['description'],
									 'dUpdate'=>olindoteToolsICAL::convertDate($_oneEvent['dLastUp'],'TS2FC'),
									 'className'=>($_oneEvent['dEnd'] <= $_now)?'iCalendar_EventIsEnd':'');
				}
			}
		}
		return json_encode($_json);
	}
}

/**
 * -------------------------------------------------------------------------------------------------------------------------
 * Class extends à la class SimpleCalDAVClient (class externe permettant de traiter les agenda caldav).
 * @author abarrau
 */
class SimpleCalDAVClientExt extends SimpleCalDAVClient {
	
	/**
	 * définie si la class est "connecté" au serveur CalDAV
	 * 	// RMQ : la variable _isConnected doit être ajoutée à la class parent SimpleCalDAVClient, 
	 * 		ainsi que la fonction connect() doit être modifiée //
	 * return boolean
	 */
	public function getIsConnected() {
		return $this->_isConnected;
	}
	
	/**
	 * récupère le calendrier automatiquement
	 * @param string $_s url du fichier ics
	 * @return string id privé
	 */
	public function getAndSetCalendar($_oCmd, $_name='') {
		$_arrayOfCalendars = $this->findCalendars();
		if (($_nb=count($_arrayOfCalendars))>0) {
			if (($_name!='')&&(isset($_arrayOfCalendars[$_name]))) {
				$_resSet = $this->setCalendar($_arrayOfCalendars[$_name]);
				$_oCmd->_log->add($_oCmd->logFN(), 'debug', '[' . $_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId().'|'.$_oCmd->getId().'] SimpleCalDAVClientExt::getAndSetCalendar() Set by name='.$_name);
			} else {
				$_k = key($_arrayOfCalendars);
				$_resSet = $this->setCalendar($_arrayOfCalendars[$_k]);
				$_txt = ($_nb==1)?'only one':'the first item';
				$_oCmd->_log->add($_oCmd->logFN(), 'debug', '['.$_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId().'|' . $_oCmd->getId() . '] SimpleCalDAVClientExt::getAndSetCalendar() Set by '.$_txt.', name='.$_k);
			}
		} else {
			$_oCmd->_log->add($_oCmd->logFN(), 'debug', '['.$_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId().'|'.$_oCmd->getId().'] SimpleCalDAVClientExt::getAndSetCalendar() no agenda was return by CalDAV Server');
		}
		if (isset($_resSet)) {
			$_oCmd->_log->add($_oCmd->logFN(), 'debug', '['.$_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId().'|'.$_oCmd->getId(). '] SimpleCalDAVClientExt::getAndSetCalendar() result of setCalendar='.print_r($_resSet,true));
		}
	}
	
	/**
	 * compile les différents évenements CalDav au format ICS dans 1 seul fichier ICS.
	 * @param array $_aObjEvent tableau des objets événement CalDAV
	 * @return string contenu du fichier ICS
	 */
	public function compileEventInICSFile($_aObjEvent) {
		$_initICS = '';
		$_otherEventICS = array();
		for($_i=0;$_i<count($_aObjEvent);$_i++) {
			if ($_aObjEvent[$_i]->getData()!='') {
				if ($_i==0) {
					$_initICS = $_aObjEvent[$_i]->getData();
				} else {
					$_posBegin = strpos($_aObjEvent[$_i]->getData(), ICalReaderExt::VEVENT_BEGIN);
					$_posEnd = strpos($_aObjEvent[$_i]->getData(), ICalReaderExt::VEVENT_END);
					$_otherEventICS[] = substr($_aObjEvent[$_i]->getData(), $_posBegin, (($_posEnd+strlen(ICalReaderExt::VEVENT_END))-$_posBegin));
				}
			}
		}
		if (count($_otherEventICS)>0) {
			$_initICS = str_replace(ICalReaderExt::VCALENDAR_END,'',$_initICS).implode("\r\n", $_otherEventICS)."\r\n".ICalReaderExt::VCALENDAR_END;
		}
		return $_initICS;
	}
}

/**
 * -------------------------------------------------------------------------------------------------------------------------
 * Class extends à la class ICalReader (class externe permettant de traiter les contenus ics).
 * @author abarrau
 */
class ICalReaderExt extends ICalReader {
	const VEVENT_BEGIN = 'BEGIN:VEVENT';
	const VEVENT_END = 'END:VEVENT';
	const VCALENDAR_BEGIN = 'BEGIN:VCALENDAR';
	const VCALENDAR_END = 'END:VCALENDAR';
    public $_timeZoneOffset;
	
	/**
     * 	Init les données de l'icalendar à partir d'un contenu
	 * 	@param string $_contents contenu à analyser
     * 	@return array structure de l'ical
     */
	public function initByString($_contents) {
		if ($_contents!='') {
			$_lines = explode("\n", $_contents);
			return $this->initLines($_lines);
		}
		return false;
	}

	/**
     * 	Remonte le nom du calendrier issue du fichier ics
     * 	@return string nom du calendrier
     */
	public function calendarNameExt() {
		return isset($this->cal['VCALENDAR']['X-WR-CALNAME'])?$this->cal['VCALENDAR']['X-WR-CALNAME']:'';
	}

    /**
     * Identifie si l'agenda est issu de google 
     * @return boolean
     */
    public function isGoogleCalendar() {
		$_pattern = "Google Calendar";
		if (isset($this->cal['VCALENDAR']['PRODID'])) {
			return (strpos($this->cal['VCALENDAR']['PRODID'], $_pattern)!==false)?true:false;
		} else {
			return false;
		}
    }
	
    /**
     * 	Supprime les lignes DTSTAMP (pour gérer la comparaison de contenu)
	 * 	@param string $_contents contenu à analyser
     * 	@return string contenu
     */
	public function removeDTSTAMP($_contents) {
		if ($_contents!='') {
			$_lines = explode("\n", $_contents);
			$_linesFiltered = array_filter($_lines, function($s){return (strpos($s,'DTSTAMP')===false)?true:false;});
			return implode("\r\n", $_linesFiltered);
		}
		return false;
	}
	
    /**
	 * Retourne l'heure au format unix, mais en prenant compte du timezone et du décallage horaire
     * @param string $_dStartRange date à traiter
     * @return string heure convertie (en seconde)
     */
    public function convertDate2TsWithTZ($_date) {
		$_ts = $this->iCalDateToUnixTimestamp($_date);
		if (substr($_date,-1)=='Z') {
			$_ts = $_ts + intval($this->getTimeOffSet(true));
		}
		return $_ts;
	}
	
    /**
	 * Retourne le décalage horaire
     * @param boolean $_inSec définie si l'on retour des secondes ou l'offset en heure
     * @return string heure convertie (en seconde)
     */
    public function getTimeOffSet($_inSec=false) {
		$_os = ($_inSec)?0:"+0000";
		if ($this->_timeZoneOffset == '') {
			if (isset($this->cal['VCALENDAR']['X-WR-TIMEZONE'])) {
				$_tzUTC = new DateTimeZone('UTC');
				$_tzDest = new DateTimeZone($this->cal['VCALENDAR']['X-WR-TIMEZONE']);
				$_dUTC = new DateTime("now", $_tzUTC);
				$_dDest = new DateTime("now", $_tzDest);
				$_os = $_tzDest->getOffset($_dDest) - $_tzUTC->getOffset($_dUTC);
				if (!$_inSec) {
					// TODO //
				}
			} else {
				if (isset($this->cal['STANDARD']['TZOFFSETTO'])) {
					// TODO : identifier le passage en heure d'été et utiliser : $this->cal['DAYLIGHT']['TZOFFSETTO']
					$_os = $this->cal['STANDARD']['TZOFFSETTO'];
					if ($_inSec) {
						preg_match("#([+|-])([0-9]{2})([0-9]{2})#", $_os, $_aOffset);
						$nbSec = $_aOffset[2]*(60*60) + $_aOffset[3]*(60);
						$_os = "$_aOffset[1]$nbSec";
					}
				}
			}
			$this->_timeZoneOffset = $_os;
		}
		return $this->_timeZoneOffset;
	}

    /**
	 * Retourne les évènements sur la période donnée
     * @param string $_dStartRange date de début de la période
     * @param string $_dEndRange date de fin de la période
     * @return array liste des évènements filtrés
     */
    public function getEventsFromRange($_oCmd, $_dStartRange=false, $_dEndRange=false) {
		if (is_array($this->events())) {
			$_events = $this->sortEventsWithOrder($this->events(), SORT_ASC);
		} else $_events = false;
        if (!$_events) {
			$_oCmd->_log->add($_oCmd->logFN(), 'debug', '['.$_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId().'|'.$_oCmd->getId().'] ICalReaderExt::getEventsFromRange() ERROR: _events is not initialised');
            return false;
        }
        $_nowEvents = array();
        if ($_dStartRange === false) {
            $_dStartRange = mktime('00','00','00');
        }
        if ($_dEndRange === false) {
            $_dEndRange = mktime('23','59','59');
        }
		$_oCmd->_log->add($_oCmd->logFN(), 'debug', '['.$_oCmd->_whatLog.'|'.$_oCmd->getEqLogic()->getId().'|'.$_oCmd->getId().'] ICalReaderExt::getEventsFromRange() _dStartRange='.$_dStartRange.' ('.date('Y-m-d H:i:s', $_dStartRange).') | _dEndRange='.$_dEndRange.' ('.date('Y-m-d H:i:s',$_dEndRange).')');
        foreach ($_events as $_oneEvent) {
			$_bUpdateDTEND = $_isExcludeDate = false;
			if (!isset($_oneEvent['DTEND'])) {
				// si dateend n'est pas définie et que datestart fait 8, alors correspond à 1 journée complète (0h->23h59) //
				$_oneEvent['DTEND'] = (strlen($_oneEvent['DTSTART'])==8)?($_oneEvent['DTSTART'].'T235959'):$_oneEvent['DTSTART'];
				$_oneEvent['DTSTART'] = (strlen($_oneEvent['DTSTART'])==8)?($_oneEvent['DTSTART'].'T000000'):$_oneEvent['DTSTART'];
			} 
			// si datestart et dateend font 8, car journée/période complète ; rajoute les heures à datestart //
			if (((strlen($_oneEvent['DTSTART'])==8)&&(strlen($_oneEvent['DTEND'])==8)) 
				|| ((substr($_oneEvent['DTSTART'],-7)=='T000000')&&(substr($_oneEvent['DTEND'],-7)=='T000000'))) {
				$_oneEvent['DTSTART'] = (strlen($_oneEvent['DTSTART'])==8)?$_oneEvent['DTSTART'].'T000000':$_oneEvent['DTSTART'];
				$_bUpdateDTEND = true;
			}
			$_oneEvent['TSSTART'] = $this->convertDate2TsWithTZ($_oneEvent['DTSTART']);
			$_oneEvent['TSEND'] = $this->convertDate2TsWithTZ($_oneEvent['DTEND']);
			// si datestart et dateend font 8, car journée/période complète ; la dateend doit être jour suivant moins 1 sec //
			if ($_bUpdateDTEND) {
				$_oneEvent['TSEND'] = $_oneEvent['TSEND'] - 1;
				$_oneEvent['DTEND'] = date('Ymd\THis',$_oneEvent['TSEND']);
			}
			// recherche des événements à conserver //
			//log::add('iCalendar','debug','------ TSSTART='.date('d/m H:i:s',$_oneEvent['TSSTART']).' | TSEND='.date('d/m H:i:s',$_oneEvent['TSEND']).' | _dStartRange='.date('d/m H:i:s',$_dStartRange).' | _dEndRange='.date('d/m H:i:s',$_dEndRange));
            if ((($_dStartRange <= $_oneEvent['TSSTART'])&&($_oneEvent['TSSTART'] <= $_dEndRange))	// debutPériode <= debutEvent < finPériode
				|| (($_dStartRange <= $_oneEvent['TSEND'])&&($_oneEvent['TSEND'] <= $_dEndRange)) 	// debutPériode <= finEvent < finPériode
				|| (($_oneEvent['TSSTART'] <= $_dStartRange)&&($_dEndRange <= $_oneEvent['TSEND'])) ) {	// debutEvent <= debutPériode & finPériode < finEvent
				// vérifie si la date doit être exclue //
				if (isset($_oneEvent['EXDATE_array'])) {
					$_isExcludeDate = $this->isExcludeDate($_oneEvent['EXDATE_array'],$_oneEvent['DTSTART']);
				}
				if ((!isset($_oneEvent['EXDATE']))||(!$_isExcludeDate)) {
					$_nowEvents[] = $_oneEvent;
				}
            }
        }
		$this->event_count = count($_nowEvents);
        return $_nowEvents;
    }

    /**
	 * Définie si la date demandée est une dat exclue
     * @param string $_aExDate liste des dates d'exclusion
     * @param string $_dDate date à comparer
     * @return boolean
     */
    public function isExcludeDate($_aExDate, $_dDate) {
		$_isExclude = false;
		foreach($_aExDate as $_v) {
			if (!is_array($_v)) {
				$_v = (strlen($_v)==8)?$_v.'T000000':$_v;
				if ($_v == $_dDate) {
					$_isExclude = true;
					break;
				}
			}
		}
		return $_isExclude;
	}
}

?>
