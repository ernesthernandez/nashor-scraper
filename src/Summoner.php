<?php

namespace Nashor;
use PHPHtmlParser\Dom;
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

    /*
     * Default options.
     * @var array
     */
    private $config;
    /**
     * Guzzle Client Object
     * @var object
     */
    private $client;

    /**
     * Summoner Instance
     */
    public function __construct(string $summonerName = '', int $summonerId = null, string $region = 'na')
    {
        if (empty($summonerName))
        {
            throw new \RuntimeException('No summoner provided');
        }
        else if (!is_numeric($summonerId))
        {
            throw new \RuntimeException('The provided ID must be a valid numeric value');
        }
        else if (!is_string($region))
        {
            throw new \RuntimeException('The provided ID must be a valid numeric value');
        }
        $this->summonerName = $summonerName;
        $this->summonerId   = $summonerId;

        $this->client       = new Nashor($region);
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

    public function getId(){
        return $this->summonerId;
    }
    public function getName(){
        return $this->summonerName;
    }

    /**
     * Refresh summoner data in OP.GG 
     * @return string json
     */
    
    public function renew()
    {
        $params = [
                    'summonerId' =>  $this->summonerId
                  ];

        try {
            $response = $this->client->post('ajax/renew.json/', $params);
        } catch (Exception $e) {
            return false;
        }

        $sync     = $this->client->json($response);
        $finish   = $sync->finish;

        do {
            $this->delay($sync->delay);

            $response = $this->client->post('ajax/renewStatus.json/', $params);

            $sync   = $this->client->json($response);

            $finish = $sync->finish;

        } while ($finish == false);

        return $finish;
    }

    /**
     * Calculate the Matchmaking Rating for a champion. AKA  Skill level.
     * @return array
     */
    public function mmr()
    {
        $params =   [
                      'summonerName' => $this->summonerName,
                    ];

        try {
            $response =$this->client->get('ajax/mmr/', $params);

            $dom = new Dom;
            $dom->load($response->getBody()->getContents());
        } catch (Exception $e) {
            return false;
        }

        $data['summonerId']   = $this->summonerId;
        $data['summonerName'] = $this->summonerName;
        $data['mmr']          = filter_var(($dom->find('.MMR')->text), FILTER_SANITIZE_NUMBER_INT);

        try {
    
            $ex = mb_strcut($dom->find('.AverageTierScore .InlineMiddle')->text, 21, 15);
            $do = explode(' ',$ex);
            $data['league']   = strtoupper($do[0]);

            if ($do[1] <= 5)
            {
                $data['division'] =   $do[1];
                $data['lp'] = filter_var($do[2], FILTER_SANITIZE_NUMBER_INT);
            }
            else
            {
                $data['division'] =   1;
                $data['lp']       = filter_var($do[1], FILTER_SANITIZE_NUMBER_INT); 
            }
        } catch (Exception $e) {
            
            $data['league']   = 'UNRANKED';
            $data['division'] = 1;
            $data['lp']       = 0;
        }

        $data['average']      = $dom->find('.TierRankString')->text;

        try {
            $data['msg'] = $dom->find('.TipStatus')->text;
        } catch (Exception $e) {
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
                    'summonerId' =>  $this->summonerId,
                    'period'     =>  $period
                  ];

        $response = $this->client->post('ajax/lpHistory.json/', $params);
        return  $this->client->json($response);
    }

    /**
     * Retrieves summoner profile detail AKA summary.
     * @return array
     */
    public function recentInfo()
    {

        $params =   [
              'summonerId' =>  $this->summonerId
            ];

        $response = $this->client->get('http://lan.op.gg/multi/ajax/summoner/', $params);

        $dom = new Dom;
        $dom->load($response->getBody()->getContents());

        try {
            $active = trim(isset($dom->find('.RecentGameWinLogs > .Message')->text)) === 'No recent Ranked Solo within 2 months.';
        } catch (Exception $e) {
            $active = true;
        }

        $contents = $dom->find('.RecentGameWinLogs .List .Item');

        $dates = [];

        foreach ($contents as $content)
        {
            $matchResult = $content->find('i')->getAttribute('class') == '__spSite __spSite-212'? true: false;
            $matchDate   = $content->getAttribute('title');
            $dates[$matchDate] = $matchResult;
        }

        $seasons = $dom->find('.TierText');
        
        $season = [];

        foreach ($seasons as $content)
        {
            $season[] = $content->text;
        }

        preg_match("/([0-9]{1,2}|100)%/", $dom->find('.RecentGameWinLogs .Title')->text, $recentWinrate);
        preg_match("/([0-9]{1,2}|100)%/", $dom->find('.WinLoseWinRatio .WinRatio')->text, $totalWinrate);

        $data['summonerId']    = $this->summonerId;
        $data['summonerName']  = $this->summonerName;

        $data['recentMatches'] = $dates;
        try {
            $data['recentStreak']  = trim($dom->find('.WinStreak')->text);
        } catch (Exception $e) {
            $data['recentStreak']  = '';
        }

        $row = $dom->find('.Content .Row');
        $seasons   = count($dom->find('.Buttons .Button'));

        $champion = [];
        $i = 0;
        foreach ($row as $content){
            $champion[$i]['championName'] = $content->find('.ChampionName')->text;
            $champion[$i]['gameCount']  = $content->find('.GameCount')->text;
            $champion[$i]['winRatio']   = $content->find('.WinRatio span')->text;
            $champion[$i]['KDA ']       = $content->find('.KDA span')->text;
            $i++;
        }

        $data['championSummary'] = array_chunk(array_chunk($champion, 10),  $seasons);
        
        return $data;
    }

        /**
     * Retrieves summoner profile detail AKA summary.
     * @return array
     */
    public function profile()
    {

        $params =   [
              'summonerId' =>  $this->summonerId
            ];

        $response = $this->client->get('http://lan.op.gg/multi/ajax/summoner/', $params);

        $dom = new Dom;
        $dom->load($response->getBody()->getContents());

        $data['summonerId']    = $this->summonerId;
        $data['summonerName']  = trim($dom->find('.SummonerName')->text);

        $is_active = FALSE;

        try 
        {
            $is_active = trim(isset($dom->find('.RecentGameWinLogs .Message')->text)) === 'No recent Ranked Solo within 2 months.';

        } catch (Exception $e) {
            echo $dom->find('.SummonerName')->text .PHP_EOL;
        }

        if($is_active)
        {
            $tier           = 'UNACTIVE';
            $data['league'] = $tier;
        }
        else if (trim($dom->find('.TierRank')->text) == 'Level')
        {   
            $tier             = 'UNRANKED';
            $data['league']   = $tier;
        }
        else
        {
            $tier = trim(strtoupper(preg_replace('/[0-9]+/', '', $dom->find('.TierRank')->text)));
            $data['league']   = $tier;
        }
        
        try {

            $winrate = $dom->find('.WinLoseWinRatio .WinRatio')->text;
        
        } catch (Exception $e) {
            $winrate = 0;
        }

        preg_match("/([0-9]{1,2}|100)%/", $winrate, $totalWinrate);

        try
        {
            $data['division'] = filter_var($dom->find('.TierRank')->text, FILTER_SANITIZE_NUMBER_INT) ?  filter_var($dom->find('.TierRank')->text, FILTER_SANITIZE_NUMBER_INT) : 1;
        } catch (Exception $e) {
            $data['division'] = 1;
        }

        try
        {
            $data['lp']           = filter_var($dom->find('.LP')->text, FILTER_SANITIZE_NUMBER_INT, FILTER_FLAG_STRIP_LOW);
        } catch (Exception $e) {
            $data['lp']           = 0;
        }

        try
        {
            $data['winRatio']      = intval(array_shift($totalWinrate));
        } catch (Exception $e) {
                $data['winRatio']  = 0;
        }
        try {
            $data['wins']          = intval($dom->find('.WinLoseWinRatio .Wins')->text);
        } catch (Exception $e) {
            $data['wins']          = 0;
        }

        try {
            $data['losses']        = intval($dom->find('.WinLoseWinRatio .Losses')->text);
        } catch (Exception $e) {
            $data['losses']            = 0;
        }

        try {
            $data['recentStreak']  = $dom->find('.WinStreak')->text;
        } catch (Exception $e) {
            $data['recentStreak']  = '';
        }

        try {
            $data['tierImage']     = basename($dom->find('.TierRankMedal img')->getAttribute('src'));
        } catch (Exception $e) {
            $data['tierImage']     = 'default.png';
        }
        
        return $data;
    }
    /**
     * Checks summoner sepectate status AKA if summoner is playing a match.
     * @return bool
     */
    public function status()
    {
        $response = $this->client->get('ajax/spectateStatus/', ['summonerName'=> $this->summonerName]);
        $body     = $this->client->json($response, true);

        if (array_key_exists('status', $body)) 
            return true;
        else
            return false;
    }

    /**
     * obtain summoner sepectate status
     * @param  [type] $summonerName [description]
     * @return [type]               [description]
     */
    public function championSumaries($season = '7')
    {

        $params = ['summonerId' => $this->summonerId, 'season' => $season];
        $response = $this->client->get('champions/ajax/champions.rank/', $params);
        
        $dom = new Dom;
        $dom->load($response->getBody()->getContents());
        $contents = $dom->find('.Body .Row');

        $data = [];

        $keys = [
            'rank',
            'champion',
            'winRatio',
            'wins',
            'losses',
            'kills',
            'deaths',
            'assists',
            'kda',
            'gold',
            'cd',
            'towers',
            'maxKills',
            'maxDeaths',
            'damageDealt',
            'damageRecived',
            'doubleKills',
            'tripleKills',
            'quadraKills'
        ];

        $i = 0;

        foreach ($contents as $content)
        {
            
            $data[$i][] = $i +1;
            $data[$i][] = $content->find('.ChampionName a')->text;
            $data[$i][] = $content->find('.RatioGraph')->getAttribute('data-value');
            $data[$i][] = $content->find('.Graph .Text')->text;
            $data[$i][] = $content->find('.Graph .Text')->text;
            $data[$i][] = $content->find('.Kill')->text;
            $data[$i][] = $content->find('.Death')->text;
            $data[$i][] = $content->find('.Assist')->text;
            $data[$i][] = $content->find('.KDA')->getAttribute('data-value');

            $details = $content->find('.Value');

            $values = [];

            foreach ($details as $content)
            {
            $data[$i][] = $content->text;

            }
        
            $data[$i] = array_combine($keys , $data[$i]);
            
            $i++;
        }

        return $data;
    }

    /**
     * Retrieves all seasons played by a champion.
     * @return array
     */
    public function seasons()
    {
        $params   = ['userName' => $this->summonerName];
        $response = $this->client->get('header/', $params);
        
        $dom = new Dom;
        $dom->load($response->getBody()->getContents());
        $contents = $dom->find('.Item');

        $data = [];

        $data['summonerName']= $this->summonerName;
        $data['ranking']     = $dom->find('.ranking')->text;
        $data['ladderRank']  = filter_var($dom->find('.LadderRank a')->text, FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
        $data['lastUpdate']  = $dom->find('.LastUpdate span')->getAttribute('data-datetime');
        $seasons = [];
        foreach ($contents as $content)
        {
            $season = $content->find('b')->text;
            $lp     = $content->getAttribute('title');
            $tier   = $content->text;

            $seasons[] = ['season' => $season, 'tier' => $tier, 'detail' => $lp];
        }
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
    
    public function matchList($type = 'soloranked', $startInfo = 0, $championId ='')
    {
        $params = [
                    'startInfo'   => $startInfo,
                    'summonerId'  => $this->summonerId,
                    'championId'  => $championId,
                    'type'        => $type,
                  ];

        $response = $this->client->get('matches/ajax/averageAndList/', $params);
    
        $body = $this->client->json($response);

        $body->lastInfo;

        $dom = new Dom;
        $dom->load($body->html);

        $contents = $dom->find('.GameItemWrap');

        $data = [];

        foreach ($contents as $content)
        {
            $matchId = $content->find('.MatchDetail')->getAttribute('onclick');
            $trinket = $content->find('.TrinketWithItem .Item .Image');

            $i = str_replace($this->summonerId, '', filter_var($matchId, FILTER_SANITIZE_NUMBER_INT));
            
            $data[$i]['matchId']        = $i;
            $data[$i]['summonerId']     = $this->summonerId;
            $data[$i]['gameType']       = str_replace(' ', '_', trim(strtoupper($content->find('.GameType')->text)));
            $data[$i]['gameResult']     = str_replace(' ', '_', trim(strtoupper($content->find('.GameResult')->text)));
            $data[$i]['gameLength']     = $content->find('.GameLength')->text;
            //$data[$i]['ChampionImage']    = $content->find('.ChampionImage a img')->getAttribute('src');
            $data[$i]['championName']   = $content->find('.ChampionName a')->text;
            $data[$i]['championLevel']  = filter_var($content->find('.Level')->text, FILTER_SANITIZE_NUMBER_INT);
            $data[$i]['trinketId']      = basename($trinket->getAttribute('src'), '.png');
            $data[$i]['masteryId']      = basename($content->find('.Mastery img')->getAttribute('src'), '.png');
            $data[$i]['kills']          = $content->find('.KDA .Kill')->text;
            $data[$i]['deaths']         = $content->find('.KDA .Death')->text;
            $data[$i]['assists']        = $content->find('.KDA .Assist')->text;
            $data[$i]['kdaRatio']       = $content->find('span.KDARatio')->text;
            try {
                //$data[$i]['multiKill']  = str_replace(' ', '_', trim(strtoupper($content->find('.MultiKill span')->text)));
            } catch (Exception $e) {
                $data[$i]['multiKill']  = 'NONE';
            }
            $data[$i]['creepScore']     = $content->find('.CS span')->text;

            try {
                //$data[$i]['visionWards'] = $content->find('.Ward span')->text;
            } catch (Exception $e) {
                $data[$i]['visionWards'] = 0;
            }

            $data[$i]['ckRate'] = filter_var($content->find('.CKRate')->text, FILTER_SANITIZE_NUMBER_INT);
            //timeago
            $data[$i]['matchDate']      = $content->find('.TimeStamp ._timeago')->getAttribute('data-datetime');
            //$data[$i]['humanizeDate']     = $content->find('.TimeStamp ._timeago')->text;

            $items = $content->find('.Items .ItemList .Item .Image');

            $item = [];

            foreach ($items as $content)
            {
                $src  = $content->getAttribute('src');
                $name = $content->getAttribute('alt');
                $item[] = intval(basename($src, '.png'));
            }

            $data[$i]['itemBuild'] = $item;
            $data[$i]['lastInfo']       = $body->lastInfo;
        }

        $teams = $dom->find('.FollowPlayers .Team .Summoner');

        $team = [];

        foreach ($teams as $content)
        {
            $summ = $content->find('.SummonerName .Link')->text;
            $champ= $content->find('.ChampionImage .Image')->text;

            $team[] = ['summonerName' => $summ, 'championName' => $champ];
        }

        $builder = array_chunk(array_chunk($team, 5), 2);   

        $i = 0;

        foreach ($data as $key => $value)
        {
            $data[$key]['matchTeams'] = $builder[$i];
            $i++;

        }

        return $data;
    }

    // detail teamAnalysis builds gold
    public function matchDetail($gameId, $type = 'detail')
    {
        if (empty($gameId))
        {
            return 'Game ID is required.';
        }
        $params = ['summonerId' => $this->summonerId, 'gameId' => $gameId, 'moreLoad' => 1];
        $response = $this->client->get('matches/ajax/'. $type . '/', $params);
        
        $dom = new Dom;
        $dom->load($response->getBody()->getContents());
        return $dom->outerHtml;
    }

    /**
     * Retrieves most played champions of summoner.
     * @param  string  $season    Lol season.
     * @param  integer $startInfo
     * @return 
     */
    public function mostPlayed($season = '7', $startInfo = 0)
    {
        $params = ['summonerId' => $this->summonerId, 'season' => $season, 'startInfo' => $startInfo];
        $response = $this->client->get('champions/ajax/champions.most/', $params);
        
        $dom = new Dom;
        $dom->load($response->getBody()->getContents());
        $teams = $dom->find('.ChampionBox');

        $data = [];
        $i = 0;
        foreach ($teams as $content)
        {
            $data[$i]['championName']  = $content->find('.ChampionName a')->text;
            $data[$i]['championImage'] =  $content->find('.ChampionImage')->getAttribute('src');
            $data[$i]['championMinionKill'] =  $content->find('.ChampionMinionKill span')->text;
            $data[$i]['KDA'] =  $content->find('.PersonalKDA div span')->text;
            $data[$i]['kills']       =  $content->find('.KDAEach .Kill')->text;
            $data[$i]['deaths']          =  $content->find('.KDAEach .Death')->text;
            $data[$i]['assists']     =  $content->find('.KDAEach .Assist')->text;
            $data[$i]['games']       =  filter_var($content->find('.Title')->text, FILTER_SANITIZE_NUMBER_INT);
            $data[$i]['winrate']     =  $content->find('.WinRatio')->text;
            $i++;
        }

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
