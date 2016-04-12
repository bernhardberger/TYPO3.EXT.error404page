<?php

namespace R3H6\Page404\Controller;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 3 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

use R3H6\Page404\Configuration\ExtensionConfiguration;
use R3H6\Page404\Domain\Repository\PageRepository;
use R3H6\Page404\Http\Request;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Messaging\ErrorpageMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Error page controller.
 */
class ErrorPageController
{
    const LOCALLANG = 'LLL:EXT:page404/Resources/Private/Language/locallang.xlf';

    /**
     * @var R3H6\Page404\Domain\Repository\PageRepository
     * @inject
     */
    protected $pageRepository;

    /**
     * @var TYPO3\CMS\Core\Cache\CacheManager
     * @inject
     */
    protected $cacheManager;

    /**
     * @var TYPO3\CMS\Core\Cache\Frontend\FrontendInterface
     */
    protected $pageCache;

    /**
     * Initialize object.
     */
    public function initializeObject()
    {
        $this->pageCache = $this->cacheManager->getCache('cache_pages');
    }

    /**
     * Renders the error page.
     *
     * @param  array $params
     * @return string Error page html.
     */
    public function handleError(array $params)
    {
        $host = GeneralUtility::getIndpEnv('REMOTE_HOST');
        $currentUrl = $params['currentUrl'];
        $reason = LocalizationUtility::translate('reasonText.' . sha1($params['reasonText']), 'page404');
        if ($reason === null) {
            $reason = $params['reasonText'];
        }

        if (!isset($_GET['tx_page404_request'])) {
            $cacheIdentifier = sha1($host . '/' . $this->getLanguage());
            $content = $this->pageCache->get($cacheIdentifier);
            if ($content === false) {
                $errorPage = $this->pageRepository->findErrorPageByHost($host);
                if ($errorPage !== null) {

                    $url = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/?id=' . $errorPage['uid'] . '&L=' . $this->getLanguage() . '&tx_page404_request=' . uniqid();
                    //@todo content_fallback!?!
die($url);
                    $request = GeneralUtility::makeInstance(Request::class, $url);
                    $content = $request->send();

                    if ($content !== null) {
                        // Cache the error page.
                        // To delete the cache when the content gets changed,
                        // we add the same tag as the core does.
                        $this->pageCache->set($cacheIdentifier, $content, ['pageId_' . $errorPage['uid']]);
                    }
                }
            }
            if (is_string($content)) {
                return str_replace(
                    ['###CURRENT_URL###', '###REASON###'],
                    [$currentUrl, $reason],
                    $content
                );
            }
        }

        // Fallback to core error message.
        $title = 'Page Not Found';
        $message = 'The page did not exist or was inaccessible.' . ($reason ? ' Reason: ' . htmlspecialchars($reason) : '');
        $messagePage = GeneralUtility::makeInstance(ErrorpageMessage::class, $message, $title);
        return $messagePage->render();
    }

    /**
     * Get system language uid
     * @return int
     */
    protected function getLanguage()
    {
        return (int) GeneralUtility::_GP('L');
    }
}
