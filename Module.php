<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2020 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\twofa;

use humhub\libs\Html;
use humhub\modules\admin\models\forms\UserEditForm;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\twofa\drivers\EmailDriver;
use humhub\modules\twofa\drivers\GoogleAuthenticatorDriver;
use humhub\modules\twofa\helpers\TwofaHelper;
use humhub\modules\twofa\helpers\TwofaUrl;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;

class Module extends ContentContainerModule
{

    /**
     * @var string Default Driver, used for Users from enforced Groups by default
     */
    public $defaultDriver = EmailDriver::class;

    /**
     * @var array Drivers
     */
    public $drivers = [
        EmailDriver::class,
        GoogleAuthenticatorDriver::class,
    ];

    /**
     * @inheritdoc
     */
    public function getContentContainerTypes()
    {
        return [
            User::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return TwofaUrl::toConfig();
    }

    /**
     * @inheritdoc
     */
    public function getContentContainerDescription(ContentContainerActiveRecord $container)
    {
        if ($container instanceof User) {
            return Yii::t('TwofaModule.base', 'Two-factor authentication for your account.');
        }
    }

    /**
     * @return bool Check if current page is already URL of 2fa
     */
    public function isTwofaCheckUrl()
    {
        return Yii::$app->requestedRoute === trim(TwofaUrl::ROUTE_CHECK, '/');
    }

    /**
     * Get available drivers options for the 2fa module settings
     *
     * @param array|null Init options(Key - Driver class name, Value - Drive name), used to init None option and/or forced/default Driver
     * @param boolean true - to load only enabled drivers, false - to load all implemented drivers for the module
     * @return array
     */
    public function getDriversOptions($driversOptions = [], $onlyEnabled = false)
    {
        $drivers = $onlyEnabled ? $this->getEnabledDrivers() : $this->drivers;
        foreach ($drivers as $driverClassName) {
            $driversOptions[$driverClassName] = TwofaHelper::getDriverByClassName($driverClassName)->name;
        }
        return $driversOptions;
    }

    /**
     * Callback function to render checkbox element of Driver on backoffice module config form
     *
     * @param $index
     * @param $label
     * @param $name
     * @param $checked
     * @param $value
     * @return string
     */
    public function renderDriverCheckboxItem($index, $label, $name, $checked, $value)
    {
        $options = [
            'label' => Html::encode($label),
            'value' => $value,
            'disabled' => !TwofaHelper::getDriverByClassName($value)->isInstalled(),
        ];

        return '<div class="checkbox">' . Html::checkbox($name, $checked, $options) . '</div>';
    }

    /**
     * Get enabled drivers
     *
     * @return array
     */
    public function getEnabledDrivers()
    {
        $enabledDrivers = $this->settings->get('enabledDrivers', implode(',', $this->drivers));

        if (empty($enabledDrivers)) {
            return [];
        }

        // Check if each enabled Driver is properly installed:
        $enabledDrivers = explode(',', $enabledDrivers);
        foreach ($enabledDrivers as $d => $enabledDriverClassName) {
            if (!TwofaHelper::getDriverByClassName($enabledDriverClassName)->isInstalled()) {
                unset($enabledDrivers[$d]);
            }
        }

        return $enabledDrivers;
    }

    /**
     * Get length of verifying code
     *
     * @return integer
     */
    public function getCodeLength()
    {
        return intval($this->settings->get('codeLength', 6));
    }

    /**
     * Get groups options for the 2fa module settings
     *
     * @return array
     */
    public function getGroupsOptions()
    {
        $groups = Group::find()->all();
        return UserEditForm::getGroupItems($groups);
    }

    /**
     * Get enforced groups to 2fa E-mail driver
     *
     * @return array
     */
    public function getEnforcedGroups()
    {
        $enforcedGroups = $this->settings->get('enforcedGroups');
        if ($enforcedGroups === null) {
            // Enforce all Administrative Groups by default
            return Group::find()->select('id')->where(['is_admin_group' => '1'])->column();
        }

        return empty($enforcedGroups) ? [] : explode(',', $enforcedGroups);
    }
}
