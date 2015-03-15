<?php
/**
 * MantisBT Plugin: Dockuwiki MantisBT Integration from Christoph Lang
 * MantisBT Plugin: Hyperlinks references to Mantis Issues from Victor Boctor
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christoph Lang <calbity@gmx.de>
 *   Christoph is the creator of the original code this plugin is based on
 * @author     Victor Boctor (http://www.futureware.biz)
 *   Victor implemented a previous version which permitted linking to MantisBT. I merged his code.
 * @author     Joe Bordes <joe@tsolucio.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');


require_once(DOKU_PLUGIN . 'mantis/lib/nusoap.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_mantis extends DokuWiki_Syntax_Plugin {

	private $sPath = "data/cache/mantis/";

	function getInfo() {
		return array(
			'author'  => 'Joe Bordes',
			'email'   => 'joe@tsolucio.com',
			'date'    => '2015-03-15',
			'name'    => 'Mantis Bug Tracker and Issues Plugin',
			'desc'    => 'Show and Links to Bugs from MantisBT',
			'url'     => 'http://www.tsolucio.com'
			);
	}

	private function _load($filename) {
		$Cache = null;
		$Update = true;
		if(file_exists($filename)){
			$Content = file_get_contents($filename);
			$Content = unserialize($Content);
			$Update = $Content["Update"];
			if(time() > $Update)
				$Update = true;
			else
				$Update = false;
			$Cache = $Content["Content"];
		}
		return array($Update,$Cache);
	}

	private function _save($filename,$rs,$timestamp) {
		$timestamp = (time() + ($timestamp*60));
		$Cache = array();
		$Cache["Update"] = $timestamp;
		$Cache["Content"] = $rs;
		$Cache = serialize($Cache);
		$handle = fopen($filename,"w");
		fwrite($handle,$Cache);
		fclose($handle);
	}

	private function getBox($text) {
		$sText = '<div class="redbox" style="text-align: left; border: 1px solid #BB8C8C; background-color: #ECDEDE; padding: 7px 10px; margin: 10px 0;">'.$text.'</div>';
		return $sText;
	}

	private function replace($data) {
		$info = $this->getInfo();
		$sReturn = "";
		if(!class_exists("soapclient")){
			$text = str_replace('[url]',$info['url'],$this->getLang('nosoap'));
			$sReturn =  $this->getBox($text);
			return $sReturn;
		}
		$server = $this->getConf('mantis_server');
		$user = $this->getConf('mantis_user');
		$pass = $this->getConf('mantis_password');
		$refresh = $this->getConf('mantis_refresh');
		$limit = $this->getConf('mantis_limit');

		if(empty($server) || empty($user) || empty($pass)){
			$text = str_replace('[url]',$info['url'],$this->getLang('noconfig'));
			$sReturn =  $this->getBox($text);
			return $sReturn;
		}

		if(!is_dir($this->sPath))
			mkdir($this->sPath);

		$filename = $this->sPath.md5(implode(".",$data));

		$Cache = $this->_load($filename);

		//print_r($Cache);
		if(is_array($Cache)){
			if($Cache[0] == false)
				return $Cache[1];
		}

		if (extension_loaded('soap'))
			$client = new soapclient($server);
		else
			$client = new soapclient($server,true);

		$sReturn .= "<h1>Project: ";
		if(!is_numeric($data[1])){
			try{
				if (extension_loaded('soap')){
					$return = $client->mc_projects_get_user_accessible($user,$pass);

					$i=1;
					$project_name = $data[$i];

					while(isset($project_name) && !empty($project_name)){

						if($return == false){
							$text = str_replace(array('[url]','[project]'),array($server,$project_name),$this->getLang('accessdenied'));
							$sReturn =  $this->getBox($text);
							return $sReturn;
						}

						if(empty($return)){
							$text = str_replace(array('[url]','[project]'),array($info['url'],$project_name),$this->getLang('projectnotfound'));
							$sReturn =  $this->getBox($text);
							return $sReturn;
						}

						foreach($return as $project){
							if($project->name == $project_name){
								$projectid = $project->id;
								$return = $project->subprojects;
							}
						}
						if($i > 1)
							$sReturn .= " > ";
						$sReturn .= $project_name." (".$projectid.")";
						$i++;
						$project_name = $data[$i];
					}

					if(empty($projectid)){
						$text = str_replace(array('[url]','[project]'),array($info['url'],$data[($i-1)]),$this->getLang('projectnotfound'));
						$sReturn =  $this->getBox($text);
						return $sReturn;
					}
				}else{
					$return = $client->call("mc_projects_get_user_accessible",array($user,$pass));
					$i=1;
					$project_name = $data[$i];

					while(isset($project_name) && !empty($project_name)){

						if($return == false){
							$text = str_replace(array('[url]','[project]'),array($server,$project_name),$this->getLang('accessdenied'));
							$sReturn =  $this->getBox($text);
							return $sReturn;
						}

						if(empty($return)){
							$text = str_replace(array('[url]','[project]'),array($info['url'],$project_name),$this->getLang('projectnotfound'));
							$sReturn =  $this->getBox($text);
							return $sReturn;
						}

						foreach($return as $project){
							if($project["name"] == $project_name){
								$projectid = $project["id"];
								$return = $project["subprojects"];
							}
						}
						if($i > 1)
							$sReturn .= " > ";
						$sReturn .= $project_name." (".$projectid.")";
						$i++;
						$project_name = $data[$i];
					}

						if(empty($projectid)){
							$text = str_replace(array('[url]','[project]'),array($info['url'],$data[($i-1)]),$this->getLang('projectnotfound'));
							$sReturn =  $this->getBox($text);
							return $sReturn;
						}
				}

			}catch(Exception $ex){
				$text = str_replace('[url]',$server,$this->getLang('accessdenied'));
				$sReturn =  $this->getBox($text);
				return $sReturn;
			}
		}else{
			$projectid = $data[1];
		}

		// make the call
		try{
			if (extension_loaded('soap'))
				$return = $client->mc_project_get_issues($user,$pass,$projectid,1,$limit);
			else
				$return = $client->call("mc_project_get_issues",array($user,$pass,$projectid,1,$limit));
		}catch(Exception $ex){
			$text = str_replace('[url]',$info['url'],$this->getLang('accessdenied'));
			$sReturn =  $this->getBox($text);
			return $sReturn;
		}

		if(empty($return)){
			$text = str_replace('[url]',$info['url'],$this->getLang('noissuesfound'));
			$sReturn =  $this->getBox($text);
			return $sReturn;
		}
		$sReturn .= " - Issues: ".count($return)."</h1>";
		$sReturn .= '<table class="inline" style="width:100%;">';
		$i=0;
		$sReturn .= '<tr class="row'.$i.'">';
		$sReturn .= '<th class="col0">'.$this->getLang('table_summary').'</th><th class="col1">'.$this->getLang('table_reporter').'</th><th class="col2">'.$this->getLang('table_description').'</th>';
		$sReturn .= '</tr>';

		foreach($return as $key => $value){
			$i++;
			if (extension_loaded('soap')){
				$sReturn .= '<tr class="row'.$i.'">';
				$sReturn .= '<td class="col0">';

				if($value->status->id == 60)
					$sReturn .= "<del>";

				$sReturn .= $value->summary;

				if($value->status->id == 60)
					$sReturn .= "</del>";

				$sReturn .= '</td>';
				$sReturn .= '<td class="col1">';

				if(!empty($value->reporter->real_name))
					$sReturn .= $value->reporter->name;
				else
					$sReturn .= $value->reporter->real_name;

				$sReturn .= '</td>';
				$sReturn .= '<td class="col2">';
				$sReturn .= nl2br(trim($value->description));
				$sReturn .= '</td>';
				$sReturn .= '</tr>';
			}else{
				$sReturn .= '<tr class="row'.$i.'">';
				$sReturn .= '<td class="col0">';

				if($value["status"]["id"] == 60)
					$sReturn .= "<del>";

				$sReturn .= $value["summary"];

				if($value["status"]["id"] == 60)
					$sReturn .= "</del>";

				$sReturn .= '</td>';
				$sReturn .= '<td class="col1">';

				if(!empty($value["reporter"]["real_name"]))
					$sReturn .= $value["reporter"]["name"];
				else
					$sReturn .= $value["reporter"]["real_name"];

				$sReturn .= '</td>';
				$sReturn .= '<td class="col2">';
				$sReturn .= nl2br(trim($value["description"]));
				$sReturn .= '</td>';
				$sReturn .= '</tr>';
			}
		}
		$sReturn .= '</table>';
		$sReturn = utf8_encode($sReturn);
		$this->_save($filename,$sReturn,$refresh);
		return $sReturn;
	}

	function connectTo($mode) {
		//$this->Lexer->addSpecialPattern('\[\[Mantis\:.*?\]\]', $mode, 'plugin_mantis');
		$this->Lexer->addSpecialPattern('{{Mantis>.*?}}', $mode, 'plugin_mantis');
		$this->Lexer->addSpecialPattern('~~issue:[0-9]+~~', $mode, 'plugin_mantis');
	}

	function getType() { return 'substition'; }

	function getPType() { return 'normal'; }

	function getSort() { return 215; }

	function handle($match, $state, $pos, &$handler) {
		if (stripos($match,'issue:')) { // we have ~~issue~~ format
			$match = substr( $match, 8, -2 ); // strip "~~issue:" from start and "~~" from end
			$arrData = array( strtolower( $match ) );
		} else {
			$match = substr($match,2,-2);
			$match = str_replace(":",">",$match);
			$arrData = explode(">",$match);
		}
		return $arrData;
	}

	function render($mode, &$renderer, $data) {
		if ($mode == 'xhtml') {
			if (is_numeric($data[0])) { // we have ~~issue~~ format
				$server = $this->getConf('mantis_server');
				$server = substr($server,0,stripos($server,'api'));
				$renderer->externallink( $server . 'view.php?id=' . $data[0], $data[0] );
			} elseif ((strtolower($data[1])=='bug' or strtolower($data[1])=='issue') and is_numeric($data[2])) { // we have {{Mantis>bug:}} format
				$server = $this->getConf('mantis_server');
				$server = substr($server,0,stripos($server,'api'));
				$renderer->externallink( $server . 'view.php?id=' . $data[2], $data[2] );
			} else { // List project issues
				$renderer->doc .= $this->replace($data);
			}
			return true;
		}
		return false;
	}
}
