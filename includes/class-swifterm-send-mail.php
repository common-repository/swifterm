<?php

class Swifterm_Send_Mail {
	
	public function __construct() {
		if (function_exists('wp_mail') && count($_POST)) {
			$postData = $_POST;
			if (isset($postData['version']) && isset($postData['domain']) && isset($postData['data'])) {
				
				//We time this regardless of whether debug is set as we can't yet tell if we want the debug or not
				$fetchKeyTimeStart = microtime(true);
				$key = hex2bin(get_option(swifterm::OPTION_API_KEY));
				$fetchKeyTimeEnd = microtime(true); 
				
				try {
					
					//Again, we can't tell yet if time debug is needed, so grab it anyway
					$decodeTimeStart = microtime(true);
					$emailPackage = SwiftAPI::Decode($postData['version'], $postData['domain'], $postData['data'], $key);
					if($emailPackage->timeDebug === true) {$decodeTimeEnd = microtime(true); $this->timeDebugOutput['SwiftAPI::Decode'] = $decodeTimeEnd-$decodeTimeStart;}
					
					if($emailPackage->timeDebug === true) {$allEmailTimeStart = microtime(true);}
					foreach($emailPackage->emailPackage as $emailContent) {
						
						$body = $emailContent->body;
						$emailTo = $emailContent->email;
						$subject = $emailContent->subject;
						$headers = array('Content-Type: text/html; charset=UTF-8');
						if($emailPackage->timeDebug === true) {$singleEmailTimeStart = microtime(true);}
						wp_mail($emailTo, $subject, $body, $headers);
						if($emailPackage->timeDebug === true) {$singleEmailTimeEnd = microtime(true); $this->timeDebugOutput['wp_mail() for ' . $emailTo] = $singleEmailTimeEnd-$singleEmailTimeStart;}
						
						
						if (property_exists($emailContent, 'monitor') && filter_var( $emailContent->monitor, FILTER_VALIDATE_EMAIL)) {
							if($emailPackage->timeDebug === true) {$monitorModeEmailStart = microtime(true);}
							wp_mail($emailContent->monitor, $emailTo.'_'.$subject, $body, $headers);
							if($emailPackage->timeDebug === true) {$monitorModeEmailEnd = microtime(true); $this->timeDebugOutput['Monitor mode email for ' . $emailTo] = $monitorModeEmailEnd-$monitorModeEmailStart;}
						}
					}
					if($emailPackage->timeDebug === true) {$allEmailTimeEnd = microtime(true); $this->timeDebugOutput['foreach($emailPackage->emailPackage as $emailContent)'] = $allEmailTimeEnd-$allEmailTimeStart;}
					
					
					if($emailPackage->timeDebug === true)
					{
						echo json_encode($this->timeDebugOutput);
					}
					else
					{
						echo 'data recieved and email sent';
					}
				}
				catch (SwiftAPI_Exception $e) {
					echo $e->getMessage();
				}
				catch (Exception $e) {
					echo $e->getMessage();
					echo "Failed to send a message, incoming data could have been spoofed";
				}
			}
		}
		
	}
	
}
