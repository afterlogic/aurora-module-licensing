<?php

namespace Aurora\Modules\Licensing;

include_once __DIR__.'\classes\KI.php';

class Module extends \Aurora\System\Module\AbstractModule
{
	protected function getKeyInfo()
	{
		$mResult = false;
		$sKey = \Aurora\System\Api::GetSettings()->GetConf('LicenseKey');
		
		if (isset($sKey))
		{
			$oKI = new \KI($this->GetLicenseKeyPrefix());
			$mResult = $oKI->GKI($sKey);
		}
		
		return $mResult;
	}
	
	public function GetLicenseKey()
	{
		return \Aurora\System\Api::GetSettings()->GetConf('LicenseKey');
	}
	
	public function GetLicenseKeyPrefix()
	{
		$sKey = \Aurora\System\Api::GetSettings()->GetConf('LicenseKey');
		$aParts = \explode('-', $sKey);
		return \array_shift($aParts);
	}
	
	
	protected function GetPartKeyInfo($sPart)
	{
		$mResult = false;
		$aKeyInfo = $this->getKeyInfo();
		if (isset($aKeyInfo[$sPart]))
		{
			$mResult = $aKeyInfo[$sPart];
		}		

		return $mResult;
	}
	
	protected function GetPartKeyData($sPart, $iPosition)
	{
		$mResult = false;
		$aModuleKeyInfo = $this->GetPartKeyInfo($sPart);
		
		if ($aModuleKeyInfo)
		{
			if (isset($aModuleKeyInfo[$iPosition]) && $aModuleKeyInfo[$iPosition] !== '*')
			{
				$mResult = $aModuleKeyInfo[$iPosition];
			}
			else
			{
				$aModuleKeyInfo = $this->GetPartKeyInfo('System');
				if ($aModuleKeyInfo && isset($aModuleKeyInfo[$iPosition]))
				{
					$mResult = $aModuleKeyInfo[$iPosition];
				}
			}
		}
		
		return $mResult;
	}

	public function GetUsersCount($Module)
	{
		return $this->GetPartKeyData($Module, 0);
	}
	
	public function Validate($oModule)
	{
		return !!$this->GetPartKeyInfo($oModule->GetName()) && !!$this->GetPartKeyInfo('System');
	}
	
	public function ValidateUsersCount($iCount)
	{
		return ($iCount <= $this->GetUsersCount('System')) ;
	}
}
