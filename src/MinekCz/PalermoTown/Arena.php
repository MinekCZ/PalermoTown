<?php

namespace MinekCz\PalermoTown;

use AttachableLogger;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;

class Arena 
{
    //consts::
    public const state_none = -1, state_lobby = 0, state_pregame = 1, state_game = 2, state_ending = 3;



    //Base::
    public PalermoTown $palermoTown;
    public array $data;
    public bool $enabled = false;

    public ArenaTask $task;
    public ArenaListener $listener;
    public ScoreBoardManager $score;


    //Worlds::
    public ?World $lobby_world = null;
    public ?World $game_world = null;


    //State::
    public int $state = self::state_none;

    //Players::

    /** @var Player[] */
    public array $players = [];
    
    /** @var Player[] */
    public array $spectators = [];

    /** @var Player|null */
    public $murder;
    /** @var Player|null */
    public $sherif;

    /** @var Player[] */
    public array  $innocents;

    public array $percents = [];
    public array $percents_final = [];

    public array $original_roles = [];


    //Time::
    public int $gameTime;
    public int $lobbyTime;
    public int $preGameTime;
    public int $endTime;
    public int $chestRefill;

    public int $sherifBow;





    public function __construct(PalermoTown $palermotown, array $data)
    {
        $this->palermoTown = $palermotown;
        $this->data = $data;

        $this->getLogger()->info("§7> Loading \"{$this->data["id"]}\"");

        $status = $this->init();

        if(!$this->enabled) 
        {
            switch($status) {
                case "null_lobby":
                    $this->getLogger()->error("[{$this->data["id"]}.yml] > Failed to load lobby level...");
                    break;
                case "null_game":
                    $this->getLogger()->error("[{$this->data["id"]}.yml] > Failed to load game level...");
                    break;
                default:
                    $this->getLogger()->error("[{$this->data["id"]}.yml] > Failed to load \"{$status}\"");
                    break;
            }


            return;
        }


        $this->task = new ArenaTask($palermotown, $this);
        $this->listener = new ArenaListener($palermotown, $this);
        $this->score = new ScoreBoardManager($palermotown, $this);

        $this->palermoTown->getScheduler()->scheduleRepeatingTask($this->task, 20);
        $this->palermoTown->getServer()->getPluginManager()->registerEvents($this->listener, $this->palermoTown);
        
    }
    public function init() :string
    {
        if(!count($this->data["spawns"]) > 0) return "spawns";
        if(!count($this->data["chests"]) > 0) return "chests";
        if($this->data["world_game"] == "") return "world_game";
        if($this->data["world_lobby"] == "") return "world-lobby";
        if($this->data["lobby"] == "") return "lobby";
        if($this->data["slots"] == 0) return "slots";

        if($this->data["name"] == "") { $this->data["name"] = $this->data["id"]; }


        

        $this->resetMaps();
        $this->reset();

        if($this->game_world == null) return "null_game";
        if($this->lobby_world == null) return "null_lobby";

        $this->enabled = true;
        $this->state = self::state_lobby;

        $this->getLogger()->info("§a> Arena \"{$this->data["id"]}\" loaded");

        return "ok";
    }


    //Logic:


    public function JoinPlayer(Player $player) 
    {
        if($this->state == self::state_ending || $this->state == self::state_none) return;

        if(isset($this->players[$player->getName()]) || isset($this->spectators[$player->getName()])) 
        {
            $player->sendMessage("§cAlready in game");
            return;
        }

        if($this->state == self::state_lobby && count($this->players) < $this->data["slots"])
        {
            $this->players[$player->getName()] = $player;
        } else 
        {
            $this->spectators[$player->getName()] = $player;
        }

        if($this->state == self::state_lobby) 
        {

            $vec = PalermoTown::StringToVec($this->data["lobby"]);
            $player->teleport(new Position($vec->x, $vec->y, $vec->z, $this->lobby_world));
            $this->InitPlayer($player, false);

        } else 
        {
            $vec = PalermoTown::StringToVec($this->data["spawns"][array_rand($this->data["spawns"])]);
            $player->teleport(new Position($vec->x, $vec->y, $vec->z, $this->game_world));
            $this->InitPlayer($player, true);
        }
    }

    public function LeavePlayer(Player $player) 
    {
        if(isset($this->players[$player->getName()])) 
        {
            $this->sendMessage("§7[§c-§7] {$player->getName()}");
            unset($this->players[$player->getName()]);

            $player->setGamemode(GameMode::SURVIVAL());
            $player->setHealth(20);
            $player->getHungerManager()->setFood(20);
            $player->getXpManager()->setXpLevel(0);
            $player->getXpManager()->setXpProgress(0);
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getInventory()->clearAll();
            $player->getOffHandInventory()->clearAll();
            
            $this->score->RemoveScoreBoard($player);
            $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
            $player->setNameTagAlwaysVisible(true);
            $player->setNameTagVisible(true);
            
        }

        if(isset($this->spectators[$player->getName()])) 
        {
            unset($this->spectators[$player->getName()]);

            $player->setGamemode(GameMode::SURVIVAL());
            $player->setHealth(20);
            $player->getHungerManager()->setFood(20);
            $player->getXpManager()->setXpLevel(0);
            $player->getXpManager()->setXpProgress(0);
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getInventory()->clearAll();
            $player->getOffHandInventory()->clearAll();


            $this->score->RemoveScoreBoard($player);
            $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());

            $player->setNameTagAlwaysVisible(true);
            $player->setNameTagVisible(true);

            unset($this->percents[$player->getName()]);
        }

        if($player == $this->murder) 
        {
            $this->murder = null;
        }

        if($player == $this->sherif) 
        {
            $this->sherif = null;
        }
    }

    public function IsInArena(Player $player) : bool 
    {
        return isset($this->players[$player->getName()]);
    }


    public function InitPlayer(Player $player, bool $spectator) 
    {
        //Set::
        $player->setGamemode($spectator ? GameMode::SPECTATOR() : GameMode::ADVENTURE());
        $player->setHealth(20);
        $player->getHungerManager()->setFood(20);
        $player->getXpManager()->setXpLevel(0);
        $player->getXpManager()->setXpProgress(0);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getInventory()->clearAll();
        $player->getOffHandInventory()->clearAll();

        $this->sendMessage(Lang::format("arena_join", 
            ["{player}"], 
            [
            $player->getName()
        ]));

        $player->sendMessage(Lang::format("arena_welcome", 
            ["{player}"], 
            [
            $player->getName()
        ]));

        $this->percents[$player->getName()]["murder"] = rand(0, 20);
        $this->percents[$player->getName()]["sherif"] = rand(0, 20);
    }

    public function KillPlayer(Player $player, Player $by = null) 
    {   
        if($by != null) 
        {

            $player->sendMessage(Lang::format("killed_by_now_spectator", 
                ["{role}"], 
                [
                $this->GetRolePretty($by)
            ]));

        } else 
        {
            $player->sendMessage(Lang::get("killed_now_spectator"));
        }

        //TODO:: Check $this->murder-> & $this->sherif->
        if($player == $this->murder) 
        {
            $this->murder = null;
        }
        if($player == $this->sherif) 
        {
            $this->sherif = null;
            $this->game_world->dropItem($player->getPosition(), $this->GetItem(ItemIds::BOW, 0, 1, Lang::get("item_sherif_bow")));
        }

        $this->JoinSpectator($player);


        $this->CheckPlayers();
    }

    public function CheckPlayers() 
    {
        foreach($this->players as $sender) 
        {
            if(!$sender->isOnline()) 
            {
                $this->sendMessage("§7[§c-§7] {$sender->getName()}");
                unset($this->players[$sender->getName()]);
                unset($this->percents[$sender->getName()]);
                continue;
            }

            if($sender->getWorld() != ($this->state == self::state_lobby ? $this->lobby_world : $this->game_world)) 
            {
                $this->sendMessage("§7[§c-§7] {$sender->getName()}");
                unset($this->players[$sender->getName()]);
                unset($this->percents[$sender->getName()]);
                continue;
            }
        }

        foreach($this->spectators as $sender) 
        {
            if(!$sender->isOnline()) 
            {
                unset($this->spectators[$sender->getName()]);
                continue;
            }

            if($sender->getWorld() != ($this->state == self::state_lobby ? $this->lobby_world : $this->game_world)) 
            {
                unset($this->spectators[$sender->getName()]);
                continue;
            }
            
            if($sender == null) 
            {
                unset($this->spectators[$sender->getName()]);
                continue;
            }
        }

        if($this->state != self::state_lobby && count($this->players) == 0 && $this->state != self::state_ending) 
        {
            
            $this->leaveAll();
            $this->resetMaps();
            $this->reset();
        }


        if($this->state != self::state_game) return;

        if(count($this->players) == 1) 
        {
            $this->endGame();
            return;
        }

        if($this->murder == null) 
        {
            $this->endGame();
            return;
        }
        
    }

    public function CalculatePercents() 
    {
        $msum = 0;
        $ssum = 0;

        foreach($this->players as $player) 
        {
            $msum += $this->percents[$player->getName()]["murder"];
            $ssum += $this->percents[$player->getName()]["sherif"];
        }

        if($msum == 0 || $ssum == 0) 
        {
            $msum = 1;
            $ssum = 1;
        }

        foreach($this->players as $player) 
        {
            $this->percents_final[$player->getName()]["murder"] = (int)(($this->percents[$player->getName()]["murder"] / $msum) * 100); 
            $this->percents_final[$player->getName()]["sherif"] = (int)(($this->percents[$player->getName()]["sherif"] / $ssum) * 100); 
        }
    }


    public function GetRole(Player $player) :string 
    {
        if(isset($this->innocents[$player->getName()])) return "Innocent";
        if($player == $this->murder) return "Murder";
        if($player == $this->sherif) return "Sherif";
        if(isset($this->spectators[$player->getName()])) return "Spectator";

        return "none";
    }

    public function JoinSpectator(Player $player) 
    {
        $this->spectators[$player->getName()] = $player;

        $player->setGamemode(GameMode::SPECTATOR());
        $player->getInventory()->clearAll();

        if(isset($this->players[$player->getName()])) unset($this->players[$player->getName()]);


    }



    public function GetRolePretty(Player $player) :string 
    {
        if(isset($this->innocents[$player->getName()])) return Lang::get("innocent");
        if($player == $this->murder) return Lang::get("murder");
        if($player == $this->sherif) return Lang::get("sherif");
        if(isset($this->spectators[$player->getName()])) return Lang::get("spectator");

        return Lang::get("none");
    }



    public function startGame() 
    {
        $this->state = self::state_pregame;

        $murder = [null, 0];
        $sherif = [null, 0];

        
        foreach($this->players + $this->spectators as $player) 
        {
            $player->getInventory()->clearAll();
            $player->getInventory()->clearAll();

            $vec = PalermoTown::StringToVec($this->data["spawns"][array_rand($this->data["spawns"])]);
            $player->teleport(new Position($vec->x, $vec->y, $vec->z, $this->game_world));

            $player->setNameTagAlwaysVisible(false);
            $player->setNameTagVisible(false);
        }


        //murder::
        foreach($this->players as $player) 
        {
            if($this->percents[$player->getName()]["murder"] > $murder[1]) 
            {
                $murder = [$player, $this->percents[$player->getName()]["murder"]];
            }
        }

        //sherif::
        foreach($this->players as $player) 
        {
            if($this->percents[$player->getName()]["sherif"] > $sherif[1]) 
            {
                if($player == $murder[0]) 
                {
                    continue;
                }
                $sherif = [$player, $this->percents[$player->getName()]["sherif"]];
            }
        }


        foreach($this->players as $player) 
        {
            if($murder[0] == $player) 
            {
                $this->murder = $player;
                $player->sendTitle(Lang::get("start_title_murder"), Lang::get("start_subtitle_murder"));
                continue;
            }

            if($sherif[0] == $player) 
            {
                $this->sherif = $player;
                $player->sendTitle(Lang::get("start_title_sherif"), Lang::get("start_subtitle_sherif"));
                continue;
            }

            $this->innocents[$player->getName()] = $player;
            $player->sendTitle(Lang::get("start_title_innocent"), Lang::get("start_subtitle_innocent"));
        }


        $this->original_roles["murder"] = $this->murder;
        $this->original_roles["sherif"] = $this->sherif;


        

        $this->sendMessage(Lang::get("game_starting_soon"));
    }

    public function preGameEnd() 
    {
        $this->state = self::state_game;

        $this->sendMessage(Lang::get("game_started"));

        $this->murder->getInventory()->setItem(1, $this->GetItem(ItemIds::IRON_SWORD, 0, 1, Lang::get("item_murder_sword")));
        $this->murder->getInventory()->setItem(2, $this->GetItem(ItemIds::BOW, 0, 1, Lang::get("item_murder_fakebow")));


        $this->sherif->getInventory()->setItem(1, $this->GetItem(ItemIds::BOW, 0, 1, Lang::get("item_sherif_bow")));
        $this->sherif->getInventory()->setItem(2, $this->GetItem(ItemIds::ARROW, 0, 1, Lang::get("item_arrow")));

        foreach($this->innocents as $inno) 
        {
            $inno->getInventory()->setItem(1, $this->GetItem(ItemIds::BOW, 0, 1, Lang::get("item_innocent_bow")));
        }


    }

    public function endGame() 
    {
        $this->state = self::state_ending;

        foreach($this->players as $player) 
        {
            $this->JoinSpectator($player);
        }

        if($this->murder == null) 
        {
            $this->sendTitle(Lang::get("innocent_win_title"));
            $this->sendMessage(Lang::get("prefix") . Lang::get("innocent_win_info"));

            $this->sendMessage(Lang::format("win_info", 
                        ["{murder}", "{sherif}"], 
                        [
                        $this->original_roles["murder"]->getName(), 
                        $this->original_roles["sherif"]->getName()
            ]));
        }

        if($this->murder != null) 
        {
            if($this->gameTime == 0) 
            {
                $this->sendTitle(Lang::get("innocent_win_title"));
                $this->sendMessage(Lang::get("prefix") . Lang::get("innocent_win_info"));

                $this->sendMessage(Lang::format("win_info", 
                        ["{murder}", "{sherif}"], 
                        [
                        $this->original_roles["murder"]->getName(), 
                        $this->original_roles["sherif"]->getName()
                ]));
            } else 
            {
                $this->sendTitle(Lang::get("murder_win_title"));
                $this->sendMessage(Lang::get("prefix") . Lang::get("murder_win_info"));

                $this->sendMessage(Lang::format("win_info", 
                        ["{murder}", "{sherif}"], 
                        [
                        $this->original_roles["murder"]->getName(), 
                        $this->original_roles["sherif"]->getName()
                ]));
            }
        }
    }

    public function finalEnd() 
    {
        $this->leaveAll();
        $this->reset();
        $this->resetMaps();
    }



    public function reset() 
    {
        $this->players = [];
        $this->spectators = [];

        $this->state = self::state_lobby;

        $this->murder = null;
        $this->sherif = null;
        $this->innocents = [];
        $this->original_roles = [];
        $this->percents = [];
        $this->percents_final = [];

        $this->gameTime = 300;
        $this->lobbyTime = 15;
        $this->preGameTime = 10;
        $this->endTime = 10;
        $this->sherifBow = 0;
        $this->chestRefill = 30;
    }

    public function leaveAll() 
    {
        foreach($this->players + $this->spectators as $player) 
        {
            $this->LeavePlayer($player);
        }
    }

    public function resetMaps() 
    {
        $g = $this->data["world_game"];
        $l = $this->data["world_lobby"];

        if(!$this->palermoTown->loadMap($g)) return;
        if($this->data["savelobby"] == "true") 
        {
            if(!$this->palermoTown->loadMap($l)) return;
        }

        $this->getServer()->getWorldManager()->loadWorld($g);
        $this->getServer()->getWorldManager()->loadWorld($l);

        $this->lobby_world = $this->getServer()->getWorldManager()->getWorldByName($l);
        $this->game_world  = $this->getServer()->getWorldManager()->getWorldByName($g);

        $this->generateChests();
    }

    public function generateChests() 
    {
        $chests = $this->data["chests"];
        
        foreach($chests as $chest) 
        {
            $vec = PalermoTown::StringToVec($chest[0]);
            $this->game_world->setBlock($vec, $this->GetBlock(BlockLegacyIds::CHEST, (int)$chest[1]), true);
        }
    }

    public function GetTime(bool $format = false) :string
    {
        if($format) 
        {
            switch($this->state) 
            {
                case Arena::state_lobby:
                    return $this->task->formatTime($this->lobbyTime);
                case Arena::state_pregame:
                    return $this->task->formatTime($this->preGameTime);
                case Arena::state_game:
                    return $this->task->formatTime($this->gameTime);
                case Arena::state_ending:
                    return $this->task->formatTime($this->endTime);
            }
        } else 
        {
            switch($this->state) 
            {
                case Arena::state_lobby:
                    return (string)$this->lobbyTime;
                case Arena::state_pregame:
                    return (string)$this->preGameTime;
                case Arena::state_game:
                    return (string)$this->gameTime;
                case Arena::state_ending:
                    return (string)$this->endTime;
            }
        }


        return "";
        
    }

    public function sendMessage(string $msg) 
    {
        foreach($this->players + $this->spectators as $sender) 
        {
            if(!$sender->isOnline()) { $this->LeavePlayer($sender); continue; };
            $sender->sendMessage($msg);
        }
    }

    public function sendTitle(string $msg, string $sub = "") 
    {
        foreach($this->players + $this->spectators as $sender) 
        {
            if(!$sender->isOnline()) { $this->LeavePlayer($sender); continue; };
            $sender->sendTitle($msg, $sub);
        }
    }

    public function sendActionBar(string $msg) 
    {
        foreach($this->players + $this->spectators as $sender) 
        {
            if(!$sender->isOnline()) { $this->LeavePlayer($sender); continue; };
            $sender->sendActionBarMessage($msg);
        }
    }

    public function getServer() :Server
    {
        return $this->palermoTown->getServer();
    }

    public function getLogger() :AttachableLogger
    {
        return $this->palermoTown->getLogger();
    }

    public function GetItem(int $id, int $meta, int $count, string $name) :Item 
    {
        $item = $this->GetItemFactory()->get($id, $meta, $count);
        $item->setCustomName($name);

        return $item;
    }

    public function GetBlock(int $id, int $meta) :Block 
    {
        return $this->GetBlockFactory()->get($id, $meta);
    }

    public function GetItemFactory() :ItemFactory
    {
        return ItemFactory::getInstance();
    }

    public function GetBlockFactory() :BlockFactory 
    {
        return BlockFactory::getInstance();
    }


}