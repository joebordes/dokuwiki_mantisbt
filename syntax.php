<?php
/**
 * Plugin Now: Inserts a timestamp.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Carlo Perassi <carlo@perassi.org>
 */

// based on http://wiki.splitbrain.org/plugin:tutorial

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
        'author'  => 'Christoph Lang',
        'email'   => 'calbity@gmx.de',
        'date'    => '2010-04-05',
        'name'    => 'Mantis Bug Tracker',
        'desc'    => 'Show Bugs from Mantis',
        'url'     => 'http://www.sdzecom.de'
        );
    }		
		
    private function _load($filename){
    	
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
    
    private function _save($filename,$rs,$timestamp){
    		$timestamp = (time() + ($timestamp*60));
    		$Cache = array();
    		$Cache["Update"] = $timestamp;
			$Cache["Content"] = $rs;			
			$Cache = serialize($Cache);							
			$handle = fopen($filename,"w");
			fwrite($handle,$Cache);
			fclose($handle);				
    	
    }
    
	private function getBox($text){
		
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
    }

    function getType() { return 'substition'; }

    function getSort() { return 215; }

    function handle($match, $state, $pos, &$handler) {
    
    	$match = substr($match,2,-2);
    	$match = str_replace(":",">",$match);    		
    	$arrData = explode(">",$match);  		
    		
        return $arrData;
    }

    function render($mode, &$renderer, $data) {

        if ($mode == 'xhtml') {
            $renderer->doc .= $this->replace($data);
            return true;
        }
        return false;
    }
}
