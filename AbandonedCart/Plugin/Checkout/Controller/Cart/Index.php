<?php

namespace ActiveCampaign\AbandonedCart\Plugin\Checkout\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Index extends AbstractHelper
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $cart;

    /**
     * @var Session
     */
    private $customerSession;
    /**
     * @var UrlInterface
     */
    private $urlInterface;

    /**
     * Index constructor.
     *
     * @param Context                         $context
     * @param \Magento\Checkout\Model\Cart    $cart
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->urlInterface = $context->getUrlBuilder();
         $this->customerSession = $customerSession;
        $this->cart = $cart;
    }

    /**
     * Shopping cart display action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function aroundExecute($subject, callable $proceed)
    {
        if ($this->_getRequest()->getParam('ac_redirect') && (empty($this->cart->getItems()) || $this->cart->getItems()->count() == 0) && !$this->customerSession->isLoggedIn()) {
            $this->customerSession->setAfterAuthUrl($this->urlInterface->getCurrentUrl());
            $this->customerSession->authenticate();
        } else {
            $resultPage = $proceed();
            return $resultPage;
        }
    }
}
