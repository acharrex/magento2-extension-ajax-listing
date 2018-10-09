<?php
/**
 * Copyright © Shopigo. All rights reserved.
 * See LICENSE.txt for license details (http://opensource.org/licenses/osl-3.0.php).
 */

namespace Shopigo\CatalogAjaxListing\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filter\RemoveTags;
use Psr\Log\LoggerInterface;

class ProductListing
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * Remove tags from string
     *
     * @var \Magento\Framework\Filter\RemoveTags $removeTags
     */
    protected $removeTags;

    /**
     * Query parameters added for AJAX requests
     *
     * @var array
     */
    protected $ajaxQueryParams = ['_', 'ajax'];

    /**
     * Initialize dependencies
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param LoggerInterface $logger
     * @param JsonFactory $resultJsonFactory
     * @param RemoveTags $removeTags
     * @return void
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger,
        JsonFactory $resultJsonFactory,
        RemoveTags $removeTags
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->removeTags = $removeTags;
    }

    /**
     * Remove query parameters added to the request URI for AJAX requests
     *
     * @return void
     */
    protected function cleanRequestUri()
    {
        $requestUri = $this->request->getRequestUri();

        $requestUriQuery = parse_url($requestUri, PHP_URL_QUERY);
        parse_str($requestUriQuery, $requestParams);
        if (is_array($requestParams) && count($requestParams) > 0) {
            foreach ($this->ajaxQueryParams as $queryParam) {
                if (array_key_exists($queryParam, $requestParams)) {
                    unset($requestParams[$queryParam]);
                }
            }
        }

        $this->request->setRequestUri(
            str_replace($requestUriQuery, http_build_query($requestParams), $requestUri)
        );
    }

    /**
     * Remove query parameters added for AJAX requests
     *
     * @return void
     */
    protected function removeAjaxQueryParams()
    {
        /** @var \Zend\Stdlib\Parameters */
        $query = $this->request->getQuery();
        if (count($query) > 0) {
            foreach ($this->ajaxQueryParams as $queryParam) {
                $query->set($queryParam, null);
            }
        }
    }

    /**
     * Build a JSON response
     *
     * @param array $data Response data
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function jsonResponse($data)
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setHeader('Content-type', 'application/json', true);
        $resultJson->setData($data);
        return $resultJson;
    }

    /**
     * Retrieve current page URL
     *
     * @param \Magento\Theme\Block\Html\Pager $pager
     * @return string
     */
    protected function getCurrentPageUrl($pager)
    {
        if ($pager->isFirstPage()) {
            $pageUrl = $pager->getPageUrl(null);
        } else {
            $pageUrl = $pager->getPageUrl($pager->getCurrentPage());
        }
        return $this->removeTags->filter($pageUrl);
    }

    /**
     * Retrieve previous page URL
     *
     * @param \Magento\Theme\Block\Html\Pager $pager
     * @return string
     */
    protected function getPreviousPageUrl($pager)
    {
        if ($pager->isFirstPage()) {
            return '';
        }

        // Workaround to don't include "p=1" in the URL
        if ($pager->getCurrentPage() == 2) {
            $pageUrl = $pager->getPageUrl(null);
        } else {
            $pageUrl = $pager->getPreviousPageUrl();
        }
        return $this->removeTags->filter($pageUrl);
    }

    /**
     * Retrieve next page URL
     *
     * @param \Magento\Theme\Block\Html\Pager $pager
     * @return string
     */
    protected function getNextPageUrl($pager)
    {
        if ($pager->isLastPage()) {
            return '';
        }
        return $this->removeTags->filter($pager->getNextPageUrl());
    }
}
