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
		$this->RegisterPropertyInteger("ArchiveId",1);
		
		// Variables
		$this->RegisterVariableString("LatestUsageLog","Latest Usage Log");
		$this->RegisterVariableString("LatestUsageLogPosition","Latest Usage Log Position");
		$this->RegisterVariableString("LatestConfigurationLog","Latest Configuration Log");
		$this->RegisterVariableString("LatestErrorLog","Latest Error Log");
		
		$this->RegisterVariableFloat("Consumption","Consumption","~Water");
		$this->RegisterVariableFloat("HardnessIn","Hardness In");
		$this->RegisterVariableFloat("HardnessOut","Hardness Out");
		$this->RegisterVariableFloat("MaxWaterFlow","Max Water Flow");
		
		$this->RegisterVariableInteger("RegenerationsColumnA","Number of regenerations - column A");
		$this->RegisterVariableInteger("RegenerationsColumnB","Number of regenerations - column B");
		$this->RegisterVariableInteger("OutOfSaltAlerts","Out of Salt alerts");
		$this->RegisterVariableInteger("AquaWatchTriggers","Aqua-Watch was triggered");
		$this->RegisterVariableInteger("AquaStopTriggers","Aqua-Stop was triggered");
		$this->RegisterVariableInteger("AquaStopLitersTriggers","Aqua-Stop based on flow was triggered");
		
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
		$form['elements'][] = Array("type" => "SelectObject", "name" => "ArchiveId", "caption" => "Select Archive for logging");

		// Add the buttons for the test center
		$form['actions'][] = Array("type" => "Button", "label" => "Refresh Overall Status", "onClick" => 'BWT_RefreshInformation($id);');
		$form['actions'][] = Array("type" => "Button", "label" => "Reset Consumption Data", "onClick" => 'BWT_ResetConsumptionData($id);');
		
		// Return the completed form
		return json_encode($form);

	}

	public function RefreshInformation() {

		IPS_LogMessage($_IPS['SELF'],"BWT - Refresh in progress");
		
		$this->refreshLatestUsageLog();
		$this->refreshLatestConfigurationLog();
		$this->refreshLatestErrorLog();
		
		$this->refreshHardness();
		$this->refreshWaterFlowProtection();
		
		$this->processErrorLog();
		$this->processLatestUsageEntries();
		
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
	
	public function ResetConsumptionData() {
		
		AC_DeleteVariableData($this->ReadPropertyInteger("ArchiveId"), $this->GetIDForIdent("Consumption"), 0, 0);
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
		
		// print_r($fullReverseContent);
		
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
	
	protected function refreshWaterFlowProtection() {
		
		SetValue($this->GetIDForIdent("MaxWaterFlow"), intval($this->getLatestConfigurationValue("MaxWaterAtOnce")));
	}
	
	protected function countErrorEntries($errorType) {
		
		$fullFileName = $this->ReadPropertyString("path") . "/" . GetValue($this->GetIDForIdent("LatestErrorLog"));
		
		$fullFileContent = file($fullFileName);
		
		// print_r($fullFileContent);
		
		$errorCount = 0;
		
		foreach ($fullFileContent as $currentLine) {
			
			if (preg_match('/^\d{6};\d\d:\d\d;' . $errorType . '.*$/', $currentLine) ) {
				
				$errorCount++;
			}
		}
		
		return $errorCount;
	}
	
	protected function processErrorLog() {
		
		// Regenerations 
		SetValue($this->GetIDForIdent("RegenerationsColumnA"), intval($this->countErrorEntries("71")));
		SetValue($this->GetIDForIdent("RegenerationsColumnB"), intval($this->countErrorEntries("72")));
		
		// Out of Salt
		SetValue($this->GetIDForIdent("OutOfSaltAlerts"), intval($this->countErrorEntries("5")));
		
		// Aqua Stop
		SetValue($this->GetIDForIdent("AquaWatchTriggers"), intval($this->countErrorEntries("15")));
		SetValue($this->GetIDForIdent("AquaStopTriggers"), intval($this->countErrorEntries("14")));
		SetValue($this->GetIDForIdent("AquaStopLitersTriggers"), intval($this->countErrorEntries("13")));
	}
	
	protected function processLatestUsageEntries() {
		
		$fullFileName = $this->ReadPropertyString("path") . "/" . GetValue($this->GetIDForIdent("LatestUsageLog"));
		
		$fp = fopen($fullFileName, "r");
		$pos = -1;
		$currentLine = '';
		$lastLinePosition = '';
		
		$deltaValues = Array();
		
		while (-1 !== fseek($fp, $pos, SEEK_END) ) {
			
			$char = fgetc($fp);
			
			if ($char == PHP_EOL {
				
				// We reached a new line and need to process it
				print_r($currentLine);
				
				// Set the starting point to the last entry if it is not set already:
				if (! GetValue($this->GetIDForIdent("LatestUsageLogPosition") ) ) {
			
					preg_match('/^(\d{6};\d\d:\d\d);.*$/', $fcurrentLine, $matches);
					SetValue($this->GetIDForIdent("LatestUsageLogPosition"), $matches[1]);
		
					fclose($fp);
					return true;
				}
				
				if ( preg_match('/^(\d{6};\d\d:\d\d);(\d+),(\d+);(\d+).*$/', $currentLine, $matches) ) {
				
					// print $matches[1] . ": " . $matches[2] . " / " . $matches[3] . " / " . $matches[4] . "\n";
					
					if ($matches[1] == GetValue($this->GetIDForIdent("LatestUsageLogPosition") ) ) {
					
						// we reached a line that we already processed so we can stop
						// echo "Line already processed, exiting\n";
						
						break;
						
					}
					
					$currentValue = Array();
					$tsYear = "20" . substr($matches[1],4,2);
					$tsMonth = substr($matches[1],2,2);
					$tsDay = substr($matches[1],0,2);
					$tsHour = substr($matches[1],7,2);
					$tsMinute = substr($matches[1],10,2);
					$tsSecond = 0;
					$ts = mktime($tsHour, $tsMinute, $tsSecond, $tsMonth, $tsDay, $tsYear);
					
					$currentValue['TimeStamp'] = $ts;
					$currentValue['Value'] = floatval($matches[4]);
					
					$deltaValues[] = $currentValue;
					$lastLinePosition = $matches[1];
				}
				
				// And we reset the line for the next value
				$currentLine = '';
			}
			else {
				
				// Add char to current line
				$currentLine = $char . $currentLine;
			}	
			
			$pos--;
		}
		
		fseek($fp, -1, SEEK_END);
		$lastLine = fgets($fp);
		
		fclose ($fp);
		
		// print_r($fullReverseContent);
		
		//print_r($deltaValues);
		
		$result = AC_AddLoggedValues($this->ReadPropertyInteger("ArchiveId"), $this->GetIDForIdent("Consumption"), $deltaValues);
		
		if ($result) {
			
			SetValue($this->GetIDForIdent("LatestUsageLogPosition"), $lastLinePosition);
		}
		else {		
			
			IPS_LogMessage($_IPS['SELF'],"BWT - ERROR - Historic values could not be added to archive");
			return false;
		}
		
		$result = AC_ReAggregateVariable($this->ReadPropertyInteger("ArchiveId"), $this->GetIDForIdent("Consumption") );
		
		if (! $result) {
			
			IPS_LogMessage($_IPS['SELF'],"BWT - ERROR - Historic archive could not be re-aggregated");
			return false;
		}
		
		return true;
	}
}
?>
