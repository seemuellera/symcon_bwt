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
		$this->RegisterVariableFloat("Consumption","Consumption","~Water");
		$this->RegisterVariableString("LatestUsageLog","Latest Usager Log File Name");
		
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
		
		print_r($this->listDirectory() );
		
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
	
	protected function getLastUsageLog() {
		
		$allFiles = $this->listDirectory();
		
		asort($allFiles);
		
		$latestUsageLogFile = "USAGE_00.LOG";
		
		foreach ($allFiles as $currentFile) {
			
			if(preg_match('/^USAGE_\d\d.LOG$/', $currentFile) ) {
				
				$latestUsageLogFile = $currentFile;
			}
		}
		
		return $latestUsageLogFile;
	}
	
	protected function refreshLatestUsageLog() {
		
		SetValue($this->GetIDForIdent("LatestUsageLog"), $this->getLastUsageLog() );
	}
}
?>
