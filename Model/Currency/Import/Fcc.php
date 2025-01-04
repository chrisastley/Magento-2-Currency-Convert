<?php
namespace Thanhdv2811\CurrencyConverter\Model\Currency\Import;

use Magento\Directory\Model\Currency\Import\AbstractImport;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Currency rate import model (From https://free.currencyconverterapi.com/)
 */
class Fcc extends AbstractImport
{
    /**
     * @var string
     */
    private const CURRENCY_CONVERTER_URL = 'https://free.currconv.com/api/v8/convert?q={{CURRENCY_FROM}}_{{CURRENCY_TO}}&compact=ultra&apiKey={{API_KEY}}';

    /**
     * @var JsonHelper
     */
    private JsonHelper $jsonHelper;

    /**
     * @var ClientInterface
     */
    private ClientInterface $httpClient;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Initialize dependencies
     *
     * @param CurrencyFactory $currencyFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ClientInterface $httpClient
     * @param JsonHelper $jsonHelper
     */
    public function __construct(
        CurrencyFactory $currencyFactory,
        ScopeConfigInterface $scopeConfig,
        ClientInterface $httpClient,
        JsonHelper $jsonHelper
    ) {
        parent::__construct($currencyFactory);
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->jsonHelper = $jsonHelper;
    }

    /**
     * @inheritDoc
     */
    protected function _convert($currencyFrom, $currencyTo, $retry = 0)
    {
        $result = null;
        $timeout = (int)$this->scopeConfig->getValue(
            'currency/freeCurrencyConverter/timeout',
            ScopeInterface::SCOPE_STORE
        );
        $apiKey = (string)$this->scopeConfig->getValue(
            'currency/freeCurrencyConverter/apikey',
            ScopeInterface::SCOPE_STORE
        );

        $url = str_replace(
            ['{{CURRENCY_FROM}}', '{{CURRENCY_TO}}', '{{API_KEY}}'],
            [$currencyFrom, $currencyTo, $apiKey],
            self::CURRENCY_CONVERTER_URL
        );

        try {
            $this->httpClient->setTimeout($timeout);
            $this->httpClient->get($url);
            $response = $this->httpClient->getBody();
            
            $resultKey = $currencyFrom . '_' . $currencyTo;
            $data = $this->jsonHelper->jsonDecode($response);
            $results = $data['results'][$resultKey] ?? null;
            $queryCount = $data['query']['count'] ?? 0;

            if (!$queryCount || !isset($results)) {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
            } else {
                $result = (float)$results['val'];
            }
        } catch (\Exception $e) {
            if ($retry === 0) {
                $result = $this->_convert($currencyFrom, $currencyTo, 1);
            } else {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
            }
        }

        return $result;
    }
}