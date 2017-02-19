<?php
namespace Nashor;
/**
 * League Of Legends Summoner interface.
 */
interface SummonerInterface
{

    /**
     * SummonerInterfaceInstance
     */
    public function __construct(string $summonerName = '', int $summonerId = null, string $region = 'na');


    /**
     * Set the League Of Legends Summoner ID for requests.
     *
     * @param int $summonerName This param must be integer otherwise string values will be assumed to be names.
     *
     * @return SummonerInterface
     * @throws SummonerException
     */
    public function setId(int $summonerId);

    /**
     * Set the League Of Legends Summoner Name for requests.
     *
     * @param string $summonerName This param must be string otherwise string values will be assumed to be Id's.
     *
     * @return string
     * @throws SummonerException
     */
    public function setName(string $summonerName);

    /**
     * Get summoner ID.
     *
     * These options retrives the summoner ID.
     *
     * @return int
     */
    public function getId();

    /**
     * Get summoner name.
     *
     * These options retrives the summoner name.
     *
     * @return int
     */
    public function getName();

}