<?php
/**
 * Matomo - free/libre analytics platform
 * Plugin developed for Web Analytics Italia (https://webanalytics.italia.it)
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RestrictAnonymousAccess;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Config;
use Piwik\NoAccessException;
use Piwik\Piwik;
use Piwik\SettingsPiwik;
use Piwik\Url;
use Exception;

class RestrictAnonymousAccess extends \Piwik\Plugin
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected $pluginConfig;

    /**
     * The count of nested request invocations. Used to determine if the currently executing request is the root or not.
     *
     * @var int
     */
    protected static $nestedInvocationCount = 0;

    /**
     * Construct a new RestrictAnonymousAccess instance.
     */
    public function __construct() {
        parent::__construct();

        $this->pluginConfig = Config::getInstance()->{$this->pluginName};
    }

    /**
     * Get event handlers.
     *
     * @return array the event handlers
     */
    public function registerEvents()
    {
        return [
            'Request.dispatch' => 'RestrictAnonymousAccess'
        ];
    }

    /**
     * Restrict access to the configured modules for the anonymous user.
     */
    public function RestrictAnonymousAccess(&$module, &$action, &$parameters)
    {
        ++self::$nestedInvocationCount;

        if (Piwik::isUserIsAnonymous()) {
            $this->checkIsAllowedRequest();
        }
    }

    /**
     * Send a response based on the current request.
     * 
     * @throws NoAccessException if the request is not allowed and redirect is not configured
     */
    protected function checkIsAllowedRequest()
    {
        if (!$this->isCurrentRequestTheRootRequest()) {
            return;
        }

        if (!$this->isAllowedRequest()) {
            if (Request::isRootRequestApiRequest()) {
                Common::sendResponseCode(403);
            }

            if ($this->shouldRedirectUnallowedRequests()) {
                Url::redirectToUrl($this->pluginConfig['redirect_unallowed_to']);

                return;
            }

            throw new NoAccessException(Piwik::translate('General_YouMustBeLoggedIn'));
        }
    }

    /**
     * Check if the request is allowed for the anonymous user.
     * 
     * @return bool whether the request is allowed
     */
    protected function isAllowedRequest()
    {
        $allowedReferrers = $this->getAllowedReferrers();
        $referrer = Common::sanitizeInputValues(@$_SERVER['HTTP_REFERER']);
        $referrerQuery = parse_url($referrer, PHP_URL_QUERY);
        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        $piwikHost = parse_url(SettingsPiwik::getPiwikUrl(), PHP_URL_HOST);
        $isReferrerHostAllowed = 0 === stripos($referrerHost, $piwikHost);

        if ($isReferrerHostAllowed && !empty($allowedReferrers) && !empty($referrerQuery)) {
            $referrerQueryParams = [];
            parse_str(html_entity_decode($referrerQuery), $referrerQueryParams);

            if ($this->paramsAreAllowed($referrerQueryParams, $allowedReferrers)) {
                return true;
            };
        }

        $allowedRequests = $this->getAllowedRequests();

        if (empty($allowedRequests)) {
            return false;
        }

        return $this->paramsAreAllowed($_GET, $allowedRequests);
    }

    protected function paramsAreAllowed($actualQueryParams, $allowedQueriesParams)
    {
        foreach($allowedQueriesParams as $allowedQueryParams) {
            $allowedQueryParamsArray = [];
            parse_str($allowedQueryParams, $allowedQueryParamsArray);

            foreach ($allowedQueryParamsArray as $allowedQueryParamName => $allowedQueryParamValue) {
                try {
                    $actualParam = Common::getRequestVar($allowedQueryParamName, null, null, $actualQueryParams);
                } catch (Exception $e) {
                    // param not present
                    continue 2;
                }

                if (strtolower($actualParam) != strtolower($allowedQueryParamValue)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Get the list of the allowed requests.
     * 
     * @return array the list of the allowed requests
     */
    protected function getAllowedRequests()
    {
        $alwaysAllowed = [
            'module=Login',
            'module=Proxy&action=getCss',
            'module=Proxy&action=getCoreJs',
            'module=Proxy&action=getNonCoreJs',
        ];

        if (!is_array($this->pluginConfig['allowed_requests'] ?? null)) {
            return $alwaysAllowed;
        }

        $allowedRequests = array_merge($this->pluginConfig['allowed_requests'], $alwaysAllowed);

        return $this->sanitizeConfigArray($allowedRequests);
    }

    /**
     * Get the list of the allowed referrers.
     * 
     * @return array the list of the allowed referrers
     */
    protected function getAllowedReferrers()
    {
        if (!is_array($this->pluginConfig['allowed_referrers'] ?? null)) {
            return [];
        }

        return $this->sanitizeConfigArray($this->pluginConfig['allowed_referrers']);
    }

    /**
     * Sanitize an array of config elements.
     * 
     * @return array the sanitized array of config elements
     */
    protected function sanitizeConfigArray($configElements) {
        $trimmedConfigElements = array_map(function ($configElement) {
            return trim($configElement);
        }, $configElements);

        return array_unique(array_values(array_filter($trimmedConfigElements, function ($configElement) {
            return !empty($configElement);
        })));
    }

    /** 
     * Check whether the unallowed requests should be redirected.
     * 
     * @return bool whether the unallowed requests should be redirected
     */
    protected function shouldRedirectUnallowedRequests()
    {
        if (!is_string($this->pluginConfig['redirect_unallowed_to'] ?? null)) {
            return false;
        }

        return false !== filter_var($this->pluginConfig['redirect_unallowed_to'], FILTER_VALIDATE_URL);
    }

    /**
     * Check if the currently executing request is the root request or not.
     *
     * @return bool
     */
    public static function isCurrentRequestTheRootRequest()
    {
        return self::$nestedInvocationCount == 1;
    }
}
