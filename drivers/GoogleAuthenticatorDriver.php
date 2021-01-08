<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2020 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\twofa\drivers;

use humhub\modules\twofa\helpers\TwofaHelper;
use humhub\modules\twofa\models\UserSettings;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;
use Yii;
use yii\bootstrap\ActiveForm;

class GoogleAuthenticatorDriver extends BaseDriver
{
    /**
     * @var string Setting name for secret code per User
     */
    protected const SECRET_SETTING = 'twofaGoogleAuthSecret';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->name = Yii::t('TwofaModule.base', 'Google Authenticator');
        $this->info = Yii::t('TwofaModule.base', 'Open the two-factor authentication app on your device to view your authentication code and verify your identity.');
    }

    /**
     * Check if this Driver is installed successfully and can be used properly
     *
     * @return bool
     */
    public function isInstalled()
    {
        // Google Authenticator library must be installed for work of this Driver:
        return class_exists('\Sonata\GoogleAuthenticator\GoogleAuthenticator') &&
            class_exists('\Sonata\GoogleAuthenticator\GoogleQrUrl');
    }

    /**
     * @inheritdoc
     */
    public function send()
    {
        if (!$this->beforeSend()) {
            return false;
        }

        $secret = TwofaHelper::getSetting(self::SECRET_SETTING);
        if (empty($secret))
        {   // If secret code is empty then QR code was not generated,
            // so current User cannot use this Driver for 2FA
            return false;
        }

        return true;
    }

    /**
     * Render additional user settings
     *
     * @param ActiveForm $form
     * @param UserSettings $model
     */
    public function renderUserSettings(ActiveForm $form, UserSettings $model)
    {
        Yii::$app->getView()->registerJsConfig('twofa', [
            'text' => [
                'confirm.action.header' => Yii::t('TwofaModule.config', '<strong>Request</strong> new code'),
                'confirm.action.question' => Yii::t('TwofaModule.config', 'Do you really want to request new code?') . '<br>'
                    . Yii::t('TwofaModule.config', 'Please <strong>don\'t forget</strong> to update new code in your Google Authenticator app, otherwise you will cannot log in!'),
                'confirm.action.button' => Yii::t('TwofaModule.config', 'Request'),
            ]
        ]);

        $this->renderUserSettingsFile([
            'driver' => $this,
        ]);
    }

    /**
     * Check code
     *
     * @param string Verifying code
     * @return bool
     */
    public function checkCode($code)
    {
        return $this->getGoogleAuthenticator()->checkCode(TwofaHelper::getSetting(self::SECRET_SETTING), $code);
    }

    /**
     * Get Google Authenticator
     *
     * @return GoogleAuthenticator
     */
    protected function getGoogleAuthenticator()
    {
        // NOTE: Don't try to pass different code length, only default $passCodeLength = 6 can be used here!
        return new GoogleAuthenticator(/* Yii::$app->getModule('twofa')->getCodeLength() */);
    }

    /**
     * Request code by AJAX request on user settings form
     *
     * @param array Params
     * @return string
     */
    public function actionRequestCode($params)
    {
        // Generate new secret code and store for current User:
        $secret = $this->getGoogleAuthenticator()->generateSecret();

        // Save new generated secret in DB:
        TwofaHelper::setSetting(self::SECRET_SETTING, $secret);

        return $this->getQrCodeSecretKeyFile();
    }

    /**
     * Get file with QR code and secret key
     *
     * @return string|void
     * @throws \Throwable
     */
    public function getQrCodeSecretKeyFile()
    {
        $secret = TwofaHelper::getSetting(self::SECRET_SETTING);

        if (empty($secret)) {
            return '';
        }

        return $this->renderFile([
            'qrCodeUrl' => GoogleQrUrl::generate(Yii::$app->user->getIdentity()->username, $secret, Yii::$app->request->hostName, 300),
            'secret' => $secret,
        ], ['suffix' => 'Code']);
    }
}