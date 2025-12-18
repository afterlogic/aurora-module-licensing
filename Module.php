<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Licensing;

include_once __DIR__ . '/classes/KI.php';

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    protected $key = null;

    protected $keyInfo = false;

    protected $bIsPermanent = true;

    public function init()
    {
        $this->denyMethodsCallByWebApi([
            'GetLicenseKey',
            'GetUsersCount',
            'Validate',
            'ValidateUsersCount',
            'ValidatePeriod',
            'IsTrial',
            'IsUnlim',
        ]);
    }

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /***** private functions *****/

    protected function getKeyInfo()
    {
        if (!$this->keyInfo) {
            $sKey = Module::Decorator()->GetLicenseKey();

            if (!empty($sKey)) {
                $oKI = new \KI();
                $this->keyInfo = $oKI->GKI($sKey);
            }
        }

        return $this->keyInfo;
    }

    protected function GetPartKeyInfo($sPart = 'System')
    {
        $mResult = false;
        $aKeyInfo = $this->getKeyInfo();
        if (!isset($aKeyInfo[$sPart])) {
            $sPart = 'System';
        }
        if (isset($aKeyInfo[$sPart])) {
            $mResult = $aKeyInfo[$sPart];
        }

        return $mResult;
    }

    protected function GetPartKeyData($sPart, $iPosition)
    {
        $mResult = false;
        $aModuleKeyInfo = $this->GetPartKeyInfo($sPart);

        if ($aModuleKeyInfo) {
            if (isset($aModuleKeyInfo[$iPosition]) && $aModuleKeyInfo[$iPosition] !== '*') {
                $mResult = $aModuleKeyInfo[$iPosition];
            } else {
                $aModuleKeyInfo = $this->GetPartKeyInfo('System');
                if ($aModuleKeyInfo && isset($aModuleKeyInfo[$iPosition])) {
                    $mResult = $aModuleKeyInfo[$iPosition];
                }
            }
        }

        return $mResult;
    }

    public function GetLicenseKey()
    {
        if (!isset($this->key)) {
            $oSettings = \Aurora\System\Api::GetSettings();
            if ($oSettings) {
                $this->key = trim($oSettings->LicenseKey);
                \Aurora\System\Api::AddSecret($this->key);
            }
        }

        return $this->key;
    }

    public function GetUsersCount($Module)
    {
        return $this->GetPartKeyData($Module, 0);
    }

    public function Validate($sModule)
    {
        return !!$this->GetPartKeyInfo($sModule) ? true : $this->IsTrial() && !!$this->GetPartKeyInfo();
    }

    public function ValidateUsersCount($iCount, $sModule = 'System')
    {
        $bResult = true;
        $aInfo = $this->GetPartKeyInfo($sModule);
        if (is_array($aInfo)) {
            $iType = (int) $aInfo[2];

            if ($iType !== 10 && $iType !== 0) {
                $bResult = ($iCount < $this->GetUsersCount($sModule));
            }
        } else {
            $bResult = false;
        }

        return $bResult;
    }

    public function ValidatePeriod($sModule = 'System')
    {
        $bResult = false;

        $sPeriod = $this->GetPartKeyData($sModule, 1);
        $sType = $this->GetPartKeyData($sModule, 2);

        if (isset($sType)) {
            $iType = (int) $sType;
            if (isset($sPeriod) && ($iType === 3 || $iType === 4 || $iType === 10)) {
                $iPeriod = (int) $sPeriod;
                $bResult = ($iPeriod - time()) > 0;
            } else {
                $bResult = true;
            }
        }

        return $bResult;
    }

    public function IsTrial($Module = 'System')
    {
        $mResult = false;
        $aInfo = $this->GetPartKeyInfo($Module);

        if (isset($aInfo[2])) {
            $mResult = ((int)$aInfo[2] === 10);
        }

        return $mResult;
    }

    public function IsUnlim($Module = 'System')
    {
        $mResult = false;
        $aInfo = $this->GetPartKeyInfo($Module);

        if (isset($aInfo[2])) {
            $mResult = ((int)$aInfo[2] === 0);
        }

        return $mResult;
    }

    /***** private functions *****/

    /***** public functions *****/
    public function GetSettings()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        return array(
            'LicenseKey' => Module::Decorator()->GetLicenseKey()
        );
    }

    /**
     * @param string $LicenseKey
     *
     * @return bool
     */
    public function UpdateSettings($LicenseKey)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $bResult = false;

        if ($LicenseKey !== null) {
            \Aurora\System\Api::GetSettings()->LicenseKey = trim($LicenseKey);
            $bResult = \Aurora\System\Api::GetSettings()->Save();
        }

        return $bResult;
    }

    public function GetLicenseInfo($Module = 'System')
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $mResult = false;
        $aInfo = $this->GetPartKeyInfo($Module);
        if (isset($aInfo[2])) {
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
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $aInfo = $this->GetPartKeyInfo('System');
        $sResult = $aInfo ? 'Empty' : 'Invalid';
        if (isset($aInfo[2])) {
            switch ($aInfo[2]) {
                case 0:
                    $sResult = 'Unlim';
                    break;
                case 1:
                    $sResult = $aInfo[0] . ' users, Permanent';
                    break;
                case 2:
                    $sResult = $aInfo[0] . ' domains';
                    break;
                case 3:
                    $sResult =  $aInfo[0] . ' users, Annual';
                    if (isset($aInfo[1]) && $aInfo[1] !== '*') {
                        $iTime = (int) $aInfo[1];
                        $iDeltaTime = $iTime - time();
                        if ($iDeltaTime > 0) {
                            $sResult .= ', expires in ' . ceil($iDeltaTime / 60 / 60 / 24) . ' day(s).';
                        } else {
                            $sResult = $aInfo[0] . ' users, Annual, Expired.
This license is outdated, please contact Afterlogic to upgrade your license key.';
                        }
                    }
                    break;
                case 4:
                    $iTime = (int) $aInfo[1];
                    $iDeltaTime = $iTime - time();
                    if ($iDeltaTime < 1) {
                        $sResult = 'This license is outdated, please contact Afterlogic to upgrade your license key.';
                    }
                    break;
                case 10:
                    $sResult = 'Trial';
                    if (isset($aInfo[1]) && $aInfo[1] !== '*') {
                        $iTime = (int) $aInfo[1];
                        $iDeltaTime = $iTime - time();
                        if ($iDeltaTime > 0) {
                            $sResult .= ', expires in ' . ceil($iDeltaTime / 60 / 60 / 24) . ' day(s).';
                        } else {
                            $sResult = $aInfo[0] . ' users, Annual, Expired.
This license is outdated, please contact Afterlogic to upgrade your license key.';
                        }
                    }
                    break;
            }
        }

        return $sResult;
    }
}
