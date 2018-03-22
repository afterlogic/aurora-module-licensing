<?php

namespace Aurora\Modules\Licensing;

include_once __DIR__.'/classes/KI.php';

class Module extends \Aurora\System\Module\AbstractModule
{
	protected $LicenseKey = null;
	
	public function GetLicenseKey()
	{
		if (!isset($this->LicenseKey))
		{
			$this->LicenseKey = \Aurora\System\Api::GetSettings()->GetConf('LicenseKey', '');
			\Aurora\System\Api::AddSecret($this->LicenseKey);
		}
		
		return $this->LicenseKey;
	}

	protected function getKeyInfo()
	{
		$mResult = false;
		$sKey = isset($this->LicenseKey) ? $this->LicenseKey : Module::Decorator()->GetLicenseKey();
	
		if (!empty($sKey))
		{
			$oKI = new \KI($this->GetLicenseKeyPrefix());
			$mResult = $oKI->GKI($sKey);
		}
		
		return $mResult;
	}
	
	public function GetLicenseKeyPrefix()
	{
		$sKey = isset($this->LicenseKey) ? $this->LicenseKey : Module::Decorator()->GetLicenseKey();
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
		$bResult = true;
		$aInfo = $this->GetPartKeyInfo('System');
		if (is_array($aInfo))
		{
			$iType = (int) $aInfo[2];

			if ($iType !== 10 && $iType !== 0)
			{
				$bResult = ($iCount < $this->GetUsersCount('System'));
			}
		}
		else
		{
			$bResult = false;
		}
		
		return $bResult;
	}
	
	public function ValidatPeriod()
	{
		$bResult = true;
		$aInfo = $this->GetPartKeyInfo('System');
		if (isset($aInfo[1]) && $aInfo[1] !== '*')
		{
			$iTime = (int) $aInfo[1];
			$iDeltaTime = $iTime - time();
			if ($iDeltaTime < 1)
			{
				$bResult = false;
			}
		}
		
		return $bResult;
	}

	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		return array(
			'LicenseKey' => $this->LicenseKey ? $this->LicenseKey : Module::Decorator()->GetLicenseKey()
		);
	}
	
	/**
	 * @return bool
	 */
	public function UpdateSettings($LicenseKey)
	{
		$bResult = false;
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);
		
		if ($LicenseKey !== null)
		{
			\Aurora\System\Api::GetSettings()->SetConf('LicenseKey', $LicenseKey);
			$bResult = \Aurora\System\Api::GetSettings()->Save();
		}
		
		return $bResult;
	}
	
	public function GetLicenseInfo()
	{
		$mResult = false;
		$aInfo = $this->GetPartKeyInfo('System');
		if (isset($aInfo[2]))
		{
			$mResult = array(
				'Count' => (int) $aInfo[0],
				'DateTime' => $aInfo[1],
				'Type' => (int) $aInfo[2],
				'ExpiresIn' => $aInfo[1] !== '*' ? ceil(((int) $aInfo[1] - time()) / 60 / 60 / 24) : '*',
			);
		}
		return $mResult;
	}
	
	/**
	 * @return int
	 */
	public function GetUserNumberLimitAsString()
	{
		$aInfo = $this->GetPartKeyInfo('System');
		$sResult = $aInfo ? 'Empty' : 'Invalid';
		if (isset($aInfo[2]))
		{
			switch ($aInfo[2])
			{
				case 0:
					$sResult = 'Unlim';
					break;
				case 1:
					$sResult = $aInfo[0].' users, Permanent';
					break;
				case 2:
					$sResult = $aInfo[0].' domains';
					break;
				case 3:
					$sResult =  $aInfo[0].' users, Annual';
					if (isset($aInfo[1]) && $aInfo[1] !== '*')
					{
						$iTime = (int) $aInfo[1];
						$iDeltaTime = $iTime - time();
						if ($iDeltaTime > 0)
						{
							$sResult .= ', expires in '.ceil($iDeltaTime / 60 / 60 / 24).' day(s).';
						}
						else
						{
					$sResult = $aInfo[0].' users, Annual, Expired.
This license is outdated, please contact AfterLogic to upgrade your license key.';
						}
					}
					break;
				case 4:
					$iTime = (int) $aInfo[1];
					$iDeltaTime = $iTime - time();
					if ($iDeltaTime < 1)
					{
						$sResult = 'This license is outdated, please contact AfterLogic to upgrade your license key.';
					}
					break;
				case 10:
					$sResult = 'Trial';
					if (isset($aInfo[1]) && $aInfo[1] !== '*')
					{
						$iTime = (int) $aInfo[1];
						$iDeltaTime = $iTime - time();
						if ($iDeltaTime > 0)
						{
							$sResult .= ', expires in '.ceil($iDeltaTime / 60 / 60 / 24).' day(s).';
						}
						else
						{
							$sResult = $aInfo[0].' users, Annual, Expired.
This license is outdated, please contact AfterLogic to upgrade your license key.';
						}
					}
					break;
			}
		}

		return $sResult;
	}	
}
