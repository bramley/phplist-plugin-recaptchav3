<?php
/**
 * RecaptchaPlugin for phplist.
 *
 * This file is a part of ReCaptchaV3Plugin.
 *
 * @author    Duncan Cameron
 * @copyright 2016-2021 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 *
 * @see       https://developers.google.com/recaptcha/intro
 */
/**
 * This class registers the plugin with phplist and hooks into the display and validation
 * of subscribe pages.
 */
class ReCaptchaV3Plugin extends phplistPlugin
{
    /** @var string the name of the version file */
    const VERSION_FILE = 'version.txt';
    const RECAPTCHA_ACTION = 'subscribe';

    /** @var string the class for sending request to google */
    private $requestClass = null;

    /** @var string the site key */
    private $siteKey;

    /** @var string the secret key */
    private $secretKey;

    /** @var bool whether reCAPTCHA keys have been entered */
    private $keysEntered;

    /*
     *  Inherited from phplistPlugin
     */
    public $name = 'reCAPTCHA V3 Plugin';
    public $description = 'Adds a reCAPTCHA v3 field to subscribe forms';
    public $documentationUrl = 'https://resources.phplist.com/plugin/recaptchav3';
    public $authors = 'Duncan Cameron';
    public $coderoot;

    /**
     * Class constructor.
     * Initialises some dynamic variables.
     */
    public function __construct()
    {
        $this->coderoot = __DIR__ . '/' . __CLASS__ . '/';
        $this->version = file_get_contents($this->coderoot . self::VERSION_FILE);

        parent::__construct();

        if (ini_get('allow_url_fopen') == '1') {
            $this->requestClass = '\ReCaptcha\RequestMethod\Post';
        } elseif (extension_loaded('curl')) {
            $this->requestClass = '\ReCaptcha\RequestMethod\CurlPost';
        } elseif (extension_loaded('openssl')) {
            $this->requestClass = '\ReCaptcha\RequestMethod\SocketPost';
        }
    }

    /**
     * Provide the dependencies for enabling this plugin.
     *
     * @return array
     */
    public function dependencyCheck()
    {
        global $plugins;

        return array(
            'curl extension, openssl extension or http wrapper available' => $this->requestClass !== null,
            'Common Plugin v3.7.17 or later installed' => (
                phpListPlugin::isEnabled('CommonPlugin')
                && version_compare($plugins['CommonPlugin']->version, '3.7.17') >= 0
            ),
            'phpList version 3.6.6 or later' => version_compare(VERSION, '3.6.6') >= 0,
        );
    }

    /**
     * Add a configuration setting for the request method.
     * Cache the plugin's config settings.
     * Recaptcha will be used only when both the site key and secrety key have
     * been entered.
     */
    public function activate()
    {
        $this->settings = array(
            'recaptchav3_sitekey' => array(
                'description' => s('reCAPTCHA site key'),
                'type' => 'text',
                'value' => '',
                'allowempty' => false,
                'category' => 'Recaptcha V3',
            ),
            'recaptchav3_secretkey' => array(
                'description' => s('reCAPTCHA secret key'),
                'type' => 'text',
                'value' => '',
                'allowempty' => false,
                'category' => 'Recaptcha V3',
            ),
            'recaptchav3_threshold' => array(
                'description' => s('reCAPTCHA threshold, between 0.1 and 0.9'),
                'type' => 'text',
                'value' => '0.5',
                'allowempty' => false,
                'category' => 'Recaptcha V3',
            ),
        );

        parent::activate();

        $this->siteKey = getConfig('recaptchav3_sitekey');
        $this->secretKey = getConfig('recaptchav3_secretkey');
        $this->keysEntered = $this->siteKey !== '' && $this->secretKey !== '';
    }

    /**
     * Provide the recaptcha html to be included in a subscription page.
     *
     * @param array $pageData subscribe page fields
     * @param int   $userId   user id
     *
     * @return string
     */
    public function displaySubscriptionChoice($pageData, $userID = 0)
    {
        if (empty($pageData['recaptchav3_include'])) {
            return '';
        }

        if (!$this->keysEntered) {
            return '';
        }
        $apiUrl = 'https://www.google.com/recaptcha/api.js';
        $format = <<<'END'
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script type="text/javascript" src="%s?render=%s"></script>
<script>
    form = $("[name='subscribeform']");
    form.submit(function(event) {
        event.preventDefault();

        grecaptcha.ready(function() {
            grecaptcha.execute('%s', {action: '%s'}).then(function(token) {
                form.prepend('<input type="hidden" name="token" value="' + token + '">');
                form.prepend('<input type="hidden" name="action" value="%s">');
                form.prepend('<input type="hidden" name="subscribe" value="1">');
                form.unbind('submit').submit();
            });
        });
  });
</script>
END;

        return sprintf($format, $apiUrl, $this->siteKey, $this->siteKey, self::RECAPTCHA_ACTION, self::RECAPTCHA_ACTION);
    }

    /**
     * Provide additional validation when a subscribe page has been submitted.
     *
     * @param array $pageData subscribe page fields
     *
     * @return string an error message to be displayed or an empty string
     *                when validation is successful
     */
    public function validateSubscriptionPage($pageData)
    {
        require $this->coderoot . 'recaptcha/src/autoload.php';

        if (empty($_POST)) {
            return '';
        }

        if (empty($pageData['recaptchav3_include'])) {
            return '';
        }

        if ($_GET['p'] == 'asubscribe' && !empty($pageData['recaptchav3_not_asubscribe'])) {
            return '';
        }

        if (!$this->keysEntered) {
            return '';
        }
        $logger = phpList\plugin\Common\Logger::instance();
        $logger->debug(print_r($_POST, true));

        $token = (string) $_POST['token'];
        $ip = getClientIP();
        $threshold = min((float) getConfig('recaptchav3_threshold'), 0.9);
        $threshold = max(0.1, $threshold);
        $recaptcha = new \ReCaptcha\ReCaptcha($this->secretKey, new $this->requestClass());
        $response = $recaptcha
            ->setExpectedAction(self::RECAPTCHA_ACTION)
            ->setExpectedHostname(getConfig('website'))
            ->setScoreThreshold($threshold)
            ->verify($token, $ip);

        $logger->debug(print_r($response, true));

        if ($response->isSuccess()) {
            return  '';
        }
        $errors = $response->getErrorCodes();

        return (count($errors) == 1 && $errors[0] == \ReCaptcha\ReCaptcha::E_SCORE_THRESHOLD_NOT_MET)
            ? s('Rejected by reCAPTCHA')
            : implode(', ', $errors);
    }

    /**
     * Provide html for the reCAPTCHA options when editing a subscribe page.
     *
     * @param array $pageData subscribe page fields
     *
     * @return string additional html
     */
    public function displaySubscribepageEdit($pageData)
    {
        $include = isset($pageData['recaptchav3_include']) ? (bool) $pageData['recaptchav3_include'] : false;
        $notAsubscribe = isset($pageData['recaptchav3_not_asubscribe']) ? (bool) $pageData['recaptchav3_not_asubscribe'] : true;
        $html =
            CHtml::label(s('Include reCAPTCHA in the subscribe page'), 'recaptchav3_include')
            . CHtml::checkBox('recaptchav3_include', $include, array('value' => 1, 'uncheckValue' => 0))
            . '<p></p>'
            . CHtml::label(s('Do not validate reCAPTCHA for asubscribe'), 'recaptchav3_not_asubscribe')
            . CHtml::checkBox('recaptchav3_not_asubscribe', $notAsubscribe, array('value' => 1, 'uncheckValue' => 0));

        return $html;
    }

    /**
     * Save the reCAPTCHA settings.
     *
     * @param int $id subscribe page id
     */
    public function processSubscribePageEdit($id)
    {
        global $tables;

        Sql_Query(
            sprintf('
                REPLACE INTO %s
                (id, name, data)
                VALUES
                (%d, "recaptchav3_include", "%s"),
                (%d, "recaptchav3_not_asubscribe", "%s")
                ',
                $tables['subscribepage_data'],
                $id,
                $_POST['recaptchav3_include'],
                $id,
                $_POST['recaptchav3_not_asubscribe']
            )
        );
    }
}
