<?php
/**
 * Copyright © Shopigo. All rights reserved.
 * See LICENSE.txt for license details (http://opensource.org/licenses/osl-3.0.php).
 */

namespace Shopigo\CatalogAjaxListing\Plugin\Catalog\Category\View;

use Magento\Catalog\Controller\Category\View as CategoryView;
use Shopigo\CatalogAjaxListing\Plugin\ProductListing;

class ProductListingPlugin extends ProductListing
{
    /**
     * Category view action
     *
     * @param CategoryView $subject
     * @param \Closure $proceed
     * @return void|\Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Json
     */
    public function aroundExecute(
        CategoryView $subject,
        \Closure $proceed
    ) {
        if (!$subject->getRequest()->isAjax()) {
            return $proceed();
        }

        try {
            // Remove query parameters added for AJAX requests
            $this->cleanRequestUri();
            $this->removeAjaxQueryParams();

            $page = $proceed();
            if (!$page) {
                throw new \Exception('No page result.');
            }

            if ($this->response->isRedirect()) {
                throw new \Exception('Unable to process page result, redirect detected.');
            }

            $className = '\Magento\Framework\View\Result\Page';
            if (!($page instanceof $className)) {
                throw new \Exception(
                    sprintf(
                        'Unable to process page result. Instance of %s expected, instance of %s got.',
                        $className,
                        get_class($page)
                    )
                );
            }

            $layout = $page->getLayout();
            /** @var $layout \Magento\Framework\View\LayoutInterface */

            $block = $layout->getBlock('category.products.list');
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
