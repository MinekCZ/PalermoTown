<?php

namespace PalermoTown;

use AttachableLogger;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\item\VanillaItems;
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


    //Worlds::
    public World $lobby_world;
    public World $game_world;


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





    public function __construct(PalermoTown $palermotown, array $data)
    {
        $this->palermoTown = $palermotown;
        $this->data = $data;

        

        $this->init();

        if(!$this->enabled) return;


        $this->task = new ArenaTask($palermotown, $this);
        $this->listener = new ArenaListener($palermotown, $this);

        $this->palermoTown->getScheduler()->scheduleRepeatingTask($this->task, 20);
        $this->palermoTown->getServer()->getPluginManager()->registerEvents($this->listener, $this->palermoTown);
        
    }
    public function init() 
    {
        if(!count($this->data["spawns"]) > 0) return;
        if(!count($this->data["chests"]) > 0) return;
        if($this->data["world_game"] == "") return;
        if($this->data["world_lobby"] == "") return;
        if($this->data["lobby"] == "") return;
        if($this->data["slots"] == 0) return;

        if($this->data["name"] == "") $this->data["name"] = $this->data["id"];


        

        $this->resetMaps();
        $this->reset();

        if($this->lobby_world != null && $this->game_world != null) 
        {
            $this->enabled = true;
            $this->state = self::state_lobby;

            $this->getLogger()->info("§aArena \"{$this->data["id"]}\" loaded");
        }

        
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

            $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
            
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

            $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());

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

        $this->sendMessage("§7[§a+§7] {$player->getName()}");
        $player->sendMessage("\n§7Welcome to §cPalermo§7Town!");

        $this->percents[$player->getName()]["murder"] = rand(0, 10);
        $this->percents[$player->getName()]["sherif"] = rand(0, 10);
    }

    public function KillPlayer(Player $player, Player $by = null) 
    {   
        if($by != null) $player->sendMessage("\n§7You've been killed by {$this->GetRolePretty($by)}\n§7§oYou're now spectator");
        if($by == null) $player->sendMessage("§7You've have been killed.\n§7§oYou're now spectator");

        //TODO:: Check $this->murder-> & $this->sherif->
        if($player == $this->murder) 
        {
            $this->murder = null;
        }
        if($player == $this->sherif) 
        {
            $this->sherif = null;
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
            $this->percents[$player->getName()]["murder"] += $this->percents[$player->getName()]["murder"] > 1 ? rand(-1, 2) : rand(0, 2); 
            $this->percents[$player->getName()]["sherif"] += $this->percents[$player->getName()]["sherif"] > 1 ? rand(-1, 2) : rand(0, 2); 

            $msum += $this->percents[$player->getName()]["murder"];
            $ssum += $this->percents[$player->getName()]["sherif"];
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
        if(isset($this->innocents[$player->getName()])) return "§aInnocent";
        if($player == $this->murder) return "§cMurder";
        if($player == $this->sherif) return "§bSherif";
        if(isset($this->spectators[$player->getName()])) return "§7Spectator";

        return "§7none";
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
                $player->sendTitle("§cMurder", "§o§7Kill all your opponents!");
                continue;
            }

            if($sherif[0] == $player) 
            {
                $this->sherif = $player;
                $player->sendTitle("§bSherif", "§o§7Kill Murder!");
                continue;
            }

            $this->innocents[$player->getName()] = $player;
            $player->sendTitle("§aInnocent", "§o§7Survive as long as possible!");
        }


        $this->original_roles["murder"] = $this->murder;
        $this->original_roles["sherif"] = $this->sherif;


        

        $this->sendMessage("\n§7> Game is starting soon!");
    }

    public function preGameEnd() 
    {
        $this->state = self::state_game;

        $this->sendMessage("\n§7> Game has started! Murder has their sword!");

        $this->murder->getInventory()->setItem(1, $this->GetItem(ItemIds::IRON_SWORD, 0, 1, "§cMurder's sword"));
        $this->murder->getInventory()->setItem(2, $this->GetItem(ItemIds::BOW, 0, 1, "§o§7FakeBow"));


        $this->sherif->getInventory()->setItem(1, $this->GetItem(ItemIds::BOW, 0, 1, "§o§bSherif's bow"));
        $this->sherif->getInventory()->setItem(2, $this->GetItem(ItemIds::ARROW, 0, 1, "§7Arrow"));

        foreach($this->innocents as $inno) 
        {
            $inno->getInventory()->setItem(1, $this->GetItem(ItemIds::BOW, 0, 1, "§7Bow"));
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
            $this->sendTitle("§aInnocents Won!");
            $this->sendMessage("\n§cPalermo§7Town\n§7Won: §aInnocents\n§7---\n\n§cMurder: §7{$this->original_roles["murder"]->getName()} \n§bSherif: §7{$this->original_roles["sherif"]->getName()}");
        }

        if($this->murder != null) 
        {
            if($this->gameTime == 0) 
            {
                $this->sendTitle("§aInnocents Won!");
                $this->sendMessage("\n§cPalermo§7Town\n§7Won: §aInnocents\n§7---§cMurder: §7{$this->original_roles["murder"]->getName()} \n§bSherif: §7{$this->original_roles["sherif"]->getName()}");
            } else 
            {
                $this->sendTitle("§cMurder Won!");
                $this->sendMessage("\n§cPalermo§7Town\n§7Won: §cMurder\n§7---\n\n§cMurder: §7{$this->original_roles["murder"]->getName()} \n§bSherif: §7{$this->original_roles["sherif"]->getName()}");
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

    public function GetItemFactory() :ItemFactory
    {
        return ItemFactory::getInstance();
    }


}