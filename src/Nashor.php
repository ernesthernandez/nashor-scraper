<?php

namespace Nashor;

use Nashor\Summoner;
use GuzzleHttp\{Client, Promise, HandlerStack};
use Psr\Http\Message\{RequestInterface, ResponseInterface, UriInterface};
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

use PHPHtmlParser\Dom as Parser;

class Nashor
{

    /**
     * Base OP.GG URI
     * @var string
     */
    public  $url = 'https://%s.op.gg/summoner/';
    /**
     * Guzzle Client Object
     * @var object
     */
    protected $client;
    /**
     * Request Object
     * @var object
     */
    protected $request;
    /**
     * Web Crawler Object
     * @var object
     */
    protected $crawler;
    /**
     * DOM Handler Object
     * @var object
     */
    protected $dom;
    /**
     * League of Legends Summoner Region.
     * @var string
     */
    protected $region;

    /**
     * A list of all permitted regions for the every call.
     *
     * @param array
     */
    protected $permittedRegions = [
        'br',
        'eune',
        'euw',
        'lan',
        'las',
        'na',
        'oce',
        'ru',
        'tr',
        'kr',
    ];
    /**
     * Nashor Scraper Instance.
     */
    public function __construct(string $region = 'na')
    {
        $this->client = $this->guzzleClient($region);
    }

    /**
     * Gets the instance of connection to the OP.GG website.
     * @return object
     */
    public function guzzleClient(string $region)
    {
        try {

            $guzzleClient =  new Client([
                //'handler' => $stack,
                'base_uri'=> $this->baseUrl($region),
                'verify'=> false,
                'defaults' => ['headers' => ['Accept-Language' => 'en', 'Accept-Encoding' => 'gzip,deflate', 'setLocale' => 'en']],
            ]);

            return $guzzleClient;
        } catch ( \Exception $e) {
            throw new \RuntimeException('OP.GG is down or something goes wrong.');
        }
    }

    /**
     * Build correct url for all requests based on passed region.
     * @param string $region Summoner Login region.
     * @return string OP.GG base URL
     */
    public function baseUrl(string $region)
    {
        $this->url = sprintf($this->url, $this->setRegion($region));
        return $this->url;
    }


    /**
     * Send Guzzle 6 get request with query parameters.
     * @param  $url string HTTP reques URI
     * @return object
     */
    public function post(string $url, $params = array())
    {
        try {
            $response = $this->client->request('POST', $url, [
                                            'form_params' => $params
                                            ]);
            return $this->jsonResponse($response);
        } catch (RequestException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Send Guzzle 6 get request with query parameters.
     * @param  $url string HTTP reques URI
     * @return object
     */
    public function get(string $url, $params = array())
    {
        try {
            $response = $this->client->request('GET', $url, [
                                            'query' => $params,
                                            ]);  

            return $this->parsedResponse($response);
        } catch (RequestException $e) {
             return $e->getMessage();
        }
    }

    /**
     * Send Guzzle 6 get request with query parameters.
     * @param  $url string HTTP reques URI
     * @return object
     */
    public function request(string $url, $params = array())
    {
        try {
            $response = $this->client->request('GET', $url, [
                                            'query' => $params,
                                            ]);  

            return $response->getBody();
        } catch (RequestException $e) {
             return $e->getMessage();
        }
    }
    /**
     * Print array from json string
     * @param  object  $request Guzzle rquest
     * @param  boolean $result  as array or object
     * @return array
     */
    public function json($request, $result = false)
    {
        return json_decode($request->getBody(), $result);
    }

    /**
     * Set the League Of Legends Summoner region.
     *
     * @param string $region Summoner Login region.
     *
     * @return string
     * @throws SummonerException
     */
    
    public function setRegion(string $region)
    {
        $region = strtolower($region);
        if (in_array($region, $this->permittedRegions))
          {
          $this->region = $region;
          }
        else
          {
            throw  new \RuntimeException('Not valid League Of Legends Region!');
          }

        return $this->getRegion();
    }

    /**
     * Get summoner login region.
     *
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * Get request URI.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    public function getRedirectUrl($url, $params = [])
    {
        $onRedirect = function(
            RequestInterface $request,
            ResponseInterface $response,
            UriInterface $uri
        ){
            return $uri;
        };

        $res = $this->client->request('GET',$url, [
            'stream'      => true,
            'synchronous' => false,
            'expect' => false,
            'decode_content' => false,
            'query'           => $params,
            'allow_redirects' => [
                'max'             => 1,
                'strict'          => true,
                'referer'         => false,
                'protocols'       => ['http', 'https'],
                'on_redirect'     => $onRedirect,
                'track_redirects' => true
            ],
        ]);

        return $res->getHeaderLine('X-Guzzle-Redirect-History');
    }

    protected function parsedResponse(ResponseInterface $response)
    {
        $dom = new Crawler((string) $response->getBody()->getContents());
        return $dom;        
    }
    protected function jsonResponse(ResponseInterface $response)
    {
        return json_decode($response->getBody(), false);
    }
}
