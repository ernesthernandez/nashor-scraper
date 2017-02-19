<?php

namespace Nashor;

use Nashor\Summoner;
use GuzzleHttp\{Client, Promise, HandlerStack};
use Goutte\Client as GoutteClient;
use GuzzleHttp\Psr7\{Request, Response};

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
    private $client;
    /**
     * Request Object
     * @var object
     */
    private $request;
    /**
     * Web Crawler Object
     * @var object
     */
    private $crawler;
    /**
     * DOM Handler Object
     * @var object
     */
    private $dom;
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
            $goutteClient = new GoutteClient();

            $goutteClient->setClient($guzzleClient);

            $this->crawler = $goutteClient;

            return $guzzleClient;
        } catch (Exception $e) {
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
        return $this->client->request('POST', $url, [
                                                    'form_params' => $params
                                                    ]);
    }

    /**
     * Send Guzzle 6 get request with query parameters.
     * @param  $url string HTTP reques URI
     * @return object
     */
    public function get(string $url, $params = array())
    {
        return $this->client->request('GET', $url, [
                                                    'query' => $params,
                                                    ]);
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
}
