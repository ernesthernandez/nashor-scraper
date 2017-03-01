<?php

namespace Nashor;

use Symfony\Component\DomCrawler\Crawler;

class Summoner implements SummonerInterface
{

    /**
     * League of Legends Summoner Name.
     * @var string
     */
    protected $summonerName;

    /**
     * League of Legends Summoner Id.
     * @var int
     */
    protected $summonerId;
    /**
     * Guzzle Client Object
     * @var object
     */
    protected $client;

    /*
     * Default options.
     * @var array
     */
    private $config;

    /**
     * Summoner Instance
     */
    public function __construct(string $summonerName = '', string $region = 'na', int $summonerId = 0)
    {
        if (empty($summonerName))
        {
            throw new \RuntimeException('The provided Summoner name is invalid.');
        }
        else if (!is_string($region))
        {
            throw new \RuntimeException('The provided region name is invalid.');
        }
        $this->client       = new Nashor($region);
        $this->region       = $region;
        $this->summonerName = $summonerName;
        $this->summonerId   = $summonerId;
    }

    public function setId(int $summonerId)
    {
        $this->summonerId = $summonerId;

        return $this->getId();
    }

    public function setName(string $summonerName)
    {
        $this->summonerName = $summonerName;

        return $this->getName();
    }

    public function getId()
    {
        if ($this->summonerId == 0)
        {
          $url = $this->client->getRedirectUrl('http://www.lolking.net/search?', ['name' => $this->summonerName, 'region' => $this->region]);
          $id = sscanf($url, 'http://www.lolking.net/summoner/'.$this->region.'/%d/');
           $this->summonerId = $id[0];
        }

        return $this->summonerId;
    }
    /**
     * Obtain Summoner Name
     * @return string
     */
    public function getName()
    {
        return $this->summonerName;
    }

    /**
     * Refresh summoner data in OP.GG 
     * @return string json
     */
    
    public function renew()
    {
        $params = [
                    'summonerId' =>  $this->getId()
                  ];

        $sync = $this->client->post('ajax/renew.json/', $params);

        $finish   = $sync->finish;

        do {
            $this->delay($sync->delay);

            $sync = $this->client->post('ajax/renewStatus.json/', $params);

            $finish = $sync->finish;

        } while ($finish == false);

        return $finish;
    }

    /**
     * Get the Matchmaking Rating for a champion. AKA  Skill level.
     * MMR displays your skill level.
     * OP.GG updates the mmr algorithms more than twenty times per two months.
     * @return array
     */
    public function info()
    {
        $params =   [
                      'summonerName' => $this->summonerName,
                    ];
        $dom = $this->client->get('ajax/mmr/', $params);

        $data['summonerId']   = $this->getId();
        $data['summonerName'] = $this->summonerName;
        $data['mmr']          = filter_var(($dom->filter('.MMR')->text()), FILTER_SANITIZE_NUMBER_INT);

        try {
            $detail = sscanf(trim($dom->filter('.AverageTierScore > .InlineMiddle')->text()), 'The Average MMR for %s %s %d is %s');

            $data['league']   = strtoupper($detail[0]);

            if ($data['league'] == 'CHALLENGER' or $data['league'] == 'MASTER')
            {
                $data['division'] = 1;
                $data['points']   = intval($detail[1]);
            }
            else
            {
                $data['division'] = $detail[1];
                $data['points']   = intval($detail[2]);
            }


        } catch ( \Exception $e) {
            
            $data['league']   = 'UNRANKED';
            $data['division'] = 1;
            $data['points']   = 0;
        }

        $data['average'] = trim($dom->filter('.TierRankString')->text());

        try {
            $data['msg'] = $dom->filter('.TipStatus')->text();

        } catch ( \Exception $e) {
            $data['msg'] =  '';
        }

         return $data;
    }

    /**
     * Get a summary of LP gained by a champion for a period.
     * @param  string $period period of time (month, week, day)
     * @return array
     */
    public function lpHistory($period = 'month')
    {
        $params = [
                    'summonerId' =>  $this->getId(),
                    'period'     =>  $period
                  ];

        return $this->client->post('ajax/lpHistory.json/', $params);
    }

    /**
     * Retrieves Solo Ranked Summary
     * @return array
     */
    public function soloRanked()
    {

        $params =   [
              'summonerId' =>  $this->getId()
            ];

        $dom   = $this->client->get('http://'.$this->region.'.op.gg/multi/ajax/summoner/', $params);

        $games = $dom->filter('.RecentGameWinLogs > .List > .Item')->each(function (Crawler $node, $i)
            {
                $matchResult = $node->filter('i')->attr('class') == '__spSite __spSite-225'? 'WIN': 'LOSE';
                //$matchDate   = $node->attr('title');
                return $matchResult;
            });

        $seasons = $dom->filter('.SummonerExtra > .PreviousSeason')->each(function (Crawler $node, $i)
        {
            return ['season' => $node->filter('.TierText')->text(), 'image' => $node->filter('img')->attr('src')];
        });   

        $data['summonerId']    = $this->getId();
        $data['summonerName']  = $this->summonerName;

        $data['recentMatches']   = $games;
        $data['previousSeasons'] = $seasons;

        try {
             preg_match("/([0-9]{1,2}|100)%/", $dom->filter('.RecentGameWinLogs > .Title')->text(), $recentWinrate);
             $data['recentWinRatio']  = intval(array_shift($recentWinrate));
        } catch (\Exception $e) {
            $data['recentWinRatio']  = 0;
        }

        try {
            $data['recentStreak']  = trim($dom->filter('.WinStreak')->text());
        } catch ( \Exception $e) {
            $data['recentStreak']  = 'No recent streak.';
        }

        $champion = $dom->filter('.ChampionSmallStats .Content .Row')->each(function (Crawler $node, $i)
        {
            return [
                        'rank'      => $i + 1, 
                        'champion'  => trim($node->filter('.ChampionName')->text()), 
                        'games'     => trim($node->filter('.GameCount')->text()),
                        'winRatio'  => trim($node->filter('.WinRatio > .Ratio')->text()),
                        'KDA'       => trim($node->filter('.KDA > .Ratio')->text())
                        ];
        });

        $seasons   = count($dom->filter('.Buttons > .Button'));

        $data['championSummary'] = array_chunk(array_chunk($champion, 10),  $seasons)[0];
        
        return $data;
    }

    /**
     * Retrieves summoner info detail AKA summary.
     * @return array
     */
    public function summary()
    {

        $params =   [
              'summonerId' =>  $this->getId()
            ];

        $dom = $this->client->get('http://'.$this->region.'.op.gg/multi/ajax/summoner/', $params);

        $data['summonerId']    = $this->getId();
        $data['summonerName']  = $this->summonerName;

        try 
        {
            $is_unactive = trim($dom->filter('.RecentGameWinLogs > .Message')->text()) == 'No recent Ranked Solo within 2 months.';

        } catch ( \Exception $e) {
             $is_unactive = FALSE;
        }

        if(!$is_unactive)
        {
            $detail = sscanf($dom->filter('.TierRank')->text(), '%s %d');
            $data['league']   = strtoupper($detail[0]);
            $data['division'] = strtoupper($detail[1]);
        }
        else if (trim($dom->filter('.TierRank')->text()) == 'Level')
        {   
            $tier           = 'UNRANKED';
            $data['league'] = $tier;
        }
        else
        {
            $tier           = 'UNRANKED';
            $data['league'] = $tier;
        }
        
        try {

            $winrate = $dom->filter('.WinLoseWinRatio > .WinRatio')->text();
        
        } catch ( \Exception $e) {
            $winrate = 0;
        }

        preg_match("/([0-9]{1,2}|100)%/", $winrate, $totalWinrate);

        try
        {
            $data['points']           = filter_var($dom->filter('.LP')->text(), FILTER_SANITIZE_NUMBER_INT, FILTER_FLAG_STRIP_LOW);
        } catch ( \Exception $e) {
            $data['points']           = 0;
        }

        try {
            $data['wins']          = intval($dom->filter('.WinLoseWinRatio > .Wins')->text());
        } catch ( \Exception $e) {
            $data['wins']          = 0;
        }

        try {
            $data['losses']        = intval($dom->filter('.WinLoseWinRatio > .Losses')->text());
        } catch ( \Exception $e) {
            $data['losses']        = 0;
        }
        try
        {
            $data['winRatio']      = intval(array_shift($totalWinrate));
        } catch ( \Exception $e) {
                $data['winRatio']  = 0;
        }

        try {
            $data['recentStreak']  = trim($dom->filter('.WinStreak')->text());
        } catch ( \Exception $e) {
            $data['recentStreak']  = 'No recent streak.';
        }

        try {
            $data['tierImage']     = $dom->filter('.TierRankMedal img')->attr('src');
        } catch ( \Exception $e) {
            $data['tierImage']     = 'default.png';
        }
        
        return $data;
    }
    /**
     * Checks summoner sepectate status AKA if summoner is playing a match.
     * @return bool
     */
    public function isPlaying()
    {
        $params = ['summonerName'=> $this->summonerName];
        $response = $this->client->getAsJson('ajax/spectateStatus/', $params);

        if (isset($response->status))
        {
            $dom   = $this->client->get('ajax/spectator3/', $params);
            
           return $dom->html();
        }
        else
        {
            return false;
        }
    }

    /**
     * Get the 20 most played champions by a summoner.
     * @param  int $season 
     * @return array
     */
    public function championSumaries($season = '7')
    {

        $params = ['summonerId' => $this->getId(), 'season' => $season];

        $dom = $this->client->get('champions/ajax/champions.rank/', $params);

        $champions = $dom->filter('.Body > .Row')->each(function (Crawler $node, $i)
        {

            try {
                $wins = filter_var($node->filter('.Graph > .Text')->eq(0)->text(), FILTER_SANITIZE_NUMBER_INT);
            } catch ( \Exception $e) {
                $wins = 0;
            }
            try {
                $losses = filter_var($node->filter('.Graph > .Text')->eq(1)->text(), FILTER_SANITIZE_NUMBER_INT);
            } catch ( \Exception $e) {
                $losses = 0;
            }

            return [
                'rank'          => $i + 1,
                'champion'      => $node->filter('.ChampionName > a')->text(),
                'winRatio'      => $node->filter('.RatioGraph')->attr('data-value'),
                'wins'          => trim($wins),
                'losses'        => trim($losses),
                'kills'         => $node->filter('.Kill')->text(),
                'deaths'        => $node->filter('.Death')->text(),
                'assists'       => $node->filter('.Assist')->text(),
                'kda'           => $node->filter('.KDA')->attr('data-value'),
                'gold'          => trim($node->filter('.Value')->eq(0)->text()),
                'cd'            => trim($node->filter('.Value')->eq(1)->text()),
                'towers'        => trim($node->filter('.Value')->eq(2)->text()),
                'maxKills'      => trim($node->filter('.Value')->eq(3)->text()),
                'maxDeaths'     => trim($node->filter('.Value')->eq(4)->text()),
                'damageDealt'   => trim($node->filter('.Value')->eq(5)->text()),
                'damageRecived' => trim($node->filter('.Value')->eq(6)->text()),
                'doubleKills'   => trim($node->filter('.Value')->eq(7)->text()),
                'tripleKills'   => trim($node->filter('.Value')->eq(8)->text()),
                'quadraKills'   => trim($node->filter('.Value')->eq(9)->text())
            ];

            return $champions;
        });

        return $champions;
    }

    /**
     * Retrieves all seasons played by a champion.
     * @return array
     */
    public function ladderRank()
    {
        $params   = ['userName' => $this->summonerName];

        $dom = $this->client->get('header/', $params);

        $data = [];

        $data['summonerName'] = $this->summonerName;
        $data['summonerId']   = $this->getId();

        try {
            $data['ranking']     = $dom->filter('.ranking')->text();
            $data['ladderRank']  = filter_var($dom->filter('.LadderRank a')->text(), FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);  
        } catch ( \Exception $e) {
            $data['ranking']     = '';
            $data['ladderRank']  = '';
            
        }
        $data['lastUpdate']  = $dom->filter('.LastUpdate > span')->attr('data-datetime');

        $seasons = $dom->filter('.Item')->each(function (Crawler $node, $i)
        {
            $season = trim($node->filter('b')->text());
            $lp     = trim($node->attr('title'));
            $tier   = trim($node->text());

            return ['season' => $season, 'tier' => strtoupper($tier), 'detail' => strtoupper($lp)];

        });

        $data['seasons'] = $seasons;

        return $data;
    }


    /**
    * Retrieves recent match history of a summoner. (Last 20 games)
    * @param  string  $type       Queue Tyope (normal, soloranked, rankedflex, total, custom, aram, event)
    * @param  integer $startInfo  Timestamp
    * @param  string  $championId Champion id filter.
    * @return array   Match history.
    *                   startInfo: startInfo,
                    summonerId: summonerId,
                    championId: championId,
                    positionType: positionType,
    */
    
    public function matchList($type = '', $startInfo = 0, $championId = '')
    {
        $params = [
                    'startInfo'   => $startInfo,
                    'summonerId'  => $this->getId(),
                    'championId'  => $championId,
                    'type'        => $type,
                  ];

        $body = $this->client->getAsJson('matches/ajax/averageAndList/', $params);

        $body->lastInfo;

        $dom = new Crawler($body->html);

        $data = $dom->filter('.GameItemWrap')->each(function (Crawler $node, $i)
        {
            $matchId = $node->filter('.MatchDetail')->attr('onclick');
            $trinket = $node->filter('.TrinketWithItem > .Item > .Image');
            $id      = $this->getId();

            $matchId   = str_replace($id, '', filter_var($matchId, FILTER_SANITIZE_NUMBER_INT));
            
            $itemBuild = $node->filter('.Items > .ItemList > .Item > .Image')->each(function (Crawler $nd, $i)
            {
                $src  = $nd->attr('src');
                $name = $nd->attr('alt');
                return $src;
            });

            try {
                $visionWards = $node->filter('.Ward span')->text();
            } catch ( \Exception $e) {
                $visionWards = 0;
            }
            try {
                
                $multiKill = str_replace(' ', '_', trim(strtoupper($node->filter('.MultiKill span')->text())));
                
            } catch ( \Exception $e) {
                $multiKill = 'NONE';
            }

            $team = $node->filter('.FollowPlayers > .Team > .Summoner')->each(function (Crawler $node, $i)
            {
                $summ = $node->filter('.SummonerName > .Link')->text();
                $champ= $node->filter('.ChampionImage > .Image')->text();

                return ['summonerName' => $summ, 'championName' => $champ];
            });

            $builder = array_chunk(array_chunk($team, 5), 2);   

            $response = [
                    'matchId'      => $matchId,
                    'summonerId'   => $id,
                    'gameType'     => str_replace(' ', '_', trim(strtoupper($node->filter('.GameType')->text()))),
                    'gameResult'   => str_replace(' ', '_', trim(strtoupper($node->filter('.GameResult')->text()))),
                    'gameLength'   => $node->filter('.GameLength')->text(),
                    'ChampionImage'=> $node->filter('.ChampionImage a img')->attr('src'),
                    'championName' => $node->filter('.ChampionName a')->text(),
                    'championLevel'=> filter_var($node->filter('.Level')->text(), FILTER_SANITIZE_NUMBER_INT),
                    'trinketId'    => basename($trinket->attr('src'), '.png'),
                    'masteryId'    => basename($node->filter('.Mastery img')->attr('src'), '.png'),
                    'kills'        => $node->filter('.KDA > .Kill')->text(),
                    'deaths'       => $node->filter('.KDA > .Death')->text(),
                    'assists'      => $node->filter('.KDA > .Assist')->text(),
                    'kdaRatio'     => $node->filter('span.KDARatio')->text(),
                    'ckRate'       => filter_var($node->filter('.CKRate')->text(), FILTER_SANITIZE_NUMBER_INT),
                    'matchDate'    => $node->filter('.TimeStamp > ._timeago')->attr('data-datetime'),
                    'creepScore'   => $node->filter('.CS span')->text(),
                    'itemBuild'    => $itemBuild,
                    'visionWards'  => $visionWards,
                    'multiKill'    => $multiKill,
                    'matchTeamates'=> $builder[0],
            ];

            return $response;

        });

        return $data;

    }

    // detail teamAnalysis builds gold
    public function matchDetail($gameId, $type = 'detail')
    {
        if (empty($gameId))
        {
            return 'Game ID is required.';
        }
        $params = ['summonerId' => $this->getId(), 'gameId' => $gameId, 'moreLoad' => 1];
        
        $dom = $this->client->get('matches/ajax/'. $type . '/', $params);

        return $dom->html();
    }

    /**
     * Retrieves 7 most played champions of summoner.
     * @param  string  $season    Lol season.
     * @param  integer $startInfo
     * @return array
     */
    public function mostPlayed($season = '7', $startInfo = 0)
    {
        $params = [
                   'summonerId' => $this->getId(), 
                   'season'     => $season, 
                   'startInfo'  => $startInfo
                   ];

        $dom = $this->client->get('champions/ajax/champions.most/', $params);

        $data = $dom->filter('.ChampionBox')->each(function (Crawler $node, $i)
        {
            return [
                        'rank'          => $i + 1,
                        'championName'  => trim($node->filter('.ChampionName a')->text()),
                        'championImage' => trim($node->filter('.ChampionImage')->attr('src')),
                        'minionKill'    => trim($node->filter('.ChampionMinionKill span')->text()),
                        'KDA'           => $node->filter('.PersonalKDA div span')->text(),
                        'kills'         => $node->filter('.KDAEach > .Kill')->text(),
                        'deaths'        => $node->filter('.KDAEach > .Death')->text(),
                        'assists'       => $node->filter('.KDAEach > .Assist')->text(),
                        'games'         => filter_var($node->filter('.Title')->text(), FILTER_SANITIZE_NUMBER_INT),
                        'winrate'       => trim($node->filter('.WinRatio')->text()),
                      ];
        });

        return $data;
    }
    /**
     * Delays the given request by an amount of microseconds.
     *
     * @param float $time The amount of time (in microseconds) to delay by.
     *
     * @codeCoverageIgnore
     */
    protected function delay($time)
    {
        usleep($time);
    }
}
