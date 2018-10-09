<?php
/**
 * Copyright © Shopigo. All rights reserved.
 * See LICENSE.txt for license details (http://opensource.org/licenses/osl-3.0.php).
 */

namespace Shopigo\CatalogAjaxListing\Plugin\Catalogsearch\Result\Index;

use Magento\CatalogSearch\Controller\Result\Index as SearchResult;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filter\RemoveTags;
use Psr\Log\LoggerInterface;
use Shopigo\CatalogAjaxListing\Plugin\ProductListing;

class ProductListingPlugin extends ProductListing
{
    /**
     * @var ViewInterface
     */
    protected $view;

    /**
     * Initialize dependencies
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param LoggerInterface $logger
     * @param JsonFactory $resultJsonFactory
     * @param RemoveTags $removeTags
     * @param ViewInterface $view
     * @return void
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger,
        JsonFactory $resultJsonFactory,
        RemoveTags $removeTags,
        ViewInterface $view
    ) {
        $this->view = $view;
        parent::__construct($request, $response, $logger, $resultJsonFactory, $removeTags);
    }

    /**
     * Search result action
     *
     * @param SearchResult $subject
     * @param \Closure $proceed
     * @return void|\Magento\Framework\Controller\Result\Json
     */
    public function aroundExecute(
        SearchResult $subject,
        \Closure $proceed
    ) {
        if (!$subject->getRequest()->isAjax()) {
            return $proceed();
        }

        try {
            // Remove query parameters added for AJAX requests
            $this->cleanRequestUri();
            $this->removeAjaxQueryParams();

            $proceed();

            if ($this->response->isRedirect()) {
                throw new \Exception('Unable to process page result, redirect detected.');
            }

            $layout = $this->view->getLayout();
            /** @var $layout \Magento\Framework\View\LayoutInterface */

            $block = $layout->getBlock('search.result');
            if (!$block) {
                throw new \Exception('Unable to load block content.');
            }

            $pager = $layout->getBlock('product_list_toolbar_pager');
            if (!$pager) {
                throw new \Exception('Unable to load pager block.');
            }

            // Generate page content to initialize toolbar
            $htmlContent = $layout->renderElement('content');

            $response = [
                'success'           => true,
                'current_page_url'  => $this->getCurrentPageUrl($pager), // e.g. "womens.html?p=3"
                'previous_page_url' => $this->getPreviousPageUrl($pager), // e.g. "womens.html?p=2"
                'next_page_url'     => $this->getNextPageUrl($pager), // e.g. "womens.html?p=4"
                'html'              => [
                    'content'       => $htmlContent,
                    'sidebar_main'  => $layout->renderElement('sidebar.main')
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $response = [
                'success'       => false,
                'error_message' => 'Sorry, something went wrong. Please try again later.'
            ];
        }
        return $this->jsonResponse($response);
    }
}
