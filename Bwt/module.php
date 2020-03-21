<?php

// Klassendefinition
class Bwt extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {

		// Diese Zeile nicht löschen
        parent::__construct($InstanceID);
 
        // Selbsterstellter Code
    }
 
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {
            
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","Bwt");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyString("path","");
		
		// Variables
		$this->RegisterVariableString("LatestUsageLog","Latest Usager Log");
		$this->RegisterVariableString("LatestConfigurationLog","Latest Configuration Log");
		$this->RegisterVariableString("LatestErrorLog","Latest Error Log");
		
		$this->RegisterVariableFloat("Consumption","Consumption","~Water");
		$this->RegisterVariableFloat("HardnessIn","Hardness In");
		$this->RegisterVariableFloat("HardnessOut","Hardness Out");
		
		// Default Actions
		// $this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'BWT_RefreshInformation($_IPS[\'TARGET\']);');

    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {

		
		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		

       	// Diese Zeile nicht löschen
       	parent::ApplyChanges();
    }


	public function GetConfigurationForm() {

        	
		// Initialize the form
		$form = Array(
            	"elements" => Array(),
				"actions" => Array()
        	);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "ValidationTextBox", "name" => "path", "caption" => "Path to the logfiles");

		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'BWT_RefreshInformation($id);');
		
		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		IPS_LogMessage($_IPS['SELF'],"BWT - Refresh in progress");
		
		$this->refreshLatestUsageLog();
		$this->refreshLatestConfigurationLog();
		$this->refreshLatestErrorLog();
		
		$this->refreshHardness();
		
		// print_r($this->listDirectory() );
		
	}
	
	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			
			case "Status":
				// Default Action for Status Variable
				
				// Neuen Wert in die Statusvariable schreiben
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
			
		}
	}
	
	protected function listDirectory() {
		
		$allFiles = scandir($this->ReadPropertyString("path") );
		
		return $allFiles;
	}
	
	protected function getLastLog($baseFileName) {
		
		$allFiles = $this->listDirectory();
		
		asort($allFiles);
		
		$latestLogFile = $baseFileName . "_00.LOG";
		
		foreach ($allFiles as $currentFile) {
			
			if(preg_match('/^' . $baseFileName . '_\d\d.LOG$/', $currentFile) ) {
				
				$latestLogFile = $currentFile;
			}
		}
		
		return $latestLogFile;
	}
	
	protected function refreshLatestUsageLog() {
		
		SetValue($this->GetIDForIdent("LatestUsageLog"), $this->getLastLog("USAGE") );
	}
	
	protected function refreshLatestConfigurationLog() {
		
		SetValue($this->GetIDForIdent("LatestConfigurationLog"), $this->getLastLog("CONF") );
	}
	
	protected function refreshLatestErrorLog() {
		
		SetValue($this->GetIDForIdent("LatestErrorLog"), $this->getLastLog("ERR") );
	}
	
	protected function getLatestConfigurationValue($attributeName) {
		
		$fullFileName = $this->ReadPropertyString("path") . "/" . GetValue($this->GetIDForIdent("LatestConfigurationLog"));
		
		$fullFileContent = file($fullFileName);
		
		// rsort($fullFileContent, SORT_STRING);
		$fullReverseContent = array_reverse($fullFileContent);
		
		print_r($fullReverseContent);
		
		foreach ($fullReverseContent as $currentLine) {
			
			if ( preg_match('/^\d{6};\d\d:\d\d;' . $attributeName . ' (.*)/', $currentLine, $matches) ) {
				
				// print_r($matches);				
				
				break;
			}
		}
		
		return $matches[1];
	}
	
	protected function refreshHardness() {
		
		SetValue($this->GetIDForIdent("HardnessIn"), floatval($this->getLatestConfigurationValue("HardnessIn")) / 10);
		SetValue($this->GetIDForIdent("HardnessOut"), floatval($this->getLatestConfigurationValue("usHardnessOut")) / 10);
	}
}
?>
