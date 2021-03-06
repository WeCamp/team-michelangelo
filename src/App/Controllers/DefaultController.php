<?php

namespace App\Controllers;

use App\Services\DatabaseServiceContainer;
use App\Services\WebsiteService;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\HttpFoundation\JsonResponse;


class DefaultController
{

    protected $returnValues;

    /** @var WebsiteService  */
    protected $databaseServiceContainer;

    public function __construct(DatabaseServiceContainer $databaseServiceContainer)
    {
        $this->databaseServiceContainer = $databaseServiceContainer;
    }

    public function search($website, $endpoint, $key, $value)
    {
        $selectors = $this->databaseServiceContainer->getSelectorService()->getAllByWebsiteIdAndEndpointId($website['id'], $endpoint['id']);

        if (null === $this->returnValues)
        {
            $this->parseHtml($selectors, $website['url']);
        }

        if (false === is_array($this->returnValues))
        {
            return new JsonResponse('Unable to load any content', 400);
        }


        $response = [];
        foreach ($this->returnValues as $valueArray)
        {
            if (key_exists($key, $valueArray) && strstr($valueArray[$key], $value))
            {
                $response[] = $valueArray;
            }
        }

        return new JsonResponse($response, 200, ['Content-Type' => 'application/json']);
    }

    public function processEndPoint($website, $endpoint)
    {
        $selectors = $this->databaseServiceContainer->getSelectorService()->getAllByWebsiteIdAndEndpointId($website['id'], $endpoint['id']);

        if (null === $this->returnValues)
        {
            $this->parseHtml($selectors, $website['url']);
        }

        if (false === is_array($this->returnValues))
        {
            return new JsonResponse('Unable to load any content', 400);
        }

        return new JsonResponse($this->returnValues, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Parses the HTML into a dom document, and processes all the dom selectors.
     * @param $domSelectors
     * @param $websiteUrl
     * @throws \Exception
     * @internal param $ [] $domSelectors
     */
    protected function parseHtml($domSelectors, $websiteUrl)
    {
        $c = curl_init($websiteUrl);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

        $html = curl_exec($c);

        if (curl_error($c))
        {
            throw new \Exception(curl_error($c));
        }

        // Get the status code
//        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);

        curl_close($c);

        $html = HtmlDomParser::str_get_html($html);

        foreach ($domSelectors as $selector) {
            foreach ($html->find($selector['selector']) as $key => $element) {

                if (isset($element->src) && !empty($element->src))
                {
                    $src = trim(strip_tags((string)$element->src));

                    $this->returnValues[$key][$selector['alias']] = $src; //$this->convertPathToExact($websiteUrl, $src);
                }
                else {
                    $this->returnValues[$key][$selector['alias']] = trim(strip_tags((string)$element));
                }
            }
        }
    }

    protected function convertPathToExact($websiteUrl, $path)
    {
        // check if src is relative
        if (false === file_exists($path)) {
            $exactUrl =  parse_url($websiteUrl, PHP_URL_SCHEME).'://'
                . parse_url($websiteUrl, PHP_URL_HOST)
                . $path;

            if (false !== file_get_contents($exactUrl))
            {
                return $exactUrl;
            }
        }

        return $path;
    }
}