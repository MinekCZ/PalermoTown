<?php

namespace MinekCz\PalermoTown;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;


//Credit: https://forums.pmmp.io/threads/how-to-properly-use-scoreboards-and-especially-updates.10136/
//Msg by: nexTRushh

class ScoreBoardManager 
{

    public PalermoTown $palermoTown;
    public Arena $arena;

    public string $id;
    

    public function __construct(PalermoTown $palermoTown, Arena $arena)
    {
        $this->arena = $arena;
        $this->palermoTown = $palermoTown;

        $this->id = "pt-" . $this->arena->data["id"];
    }
    



    public function Display(Player $player)
    {
        $this->Update($player);
            

        $this->SetLine($player, 1, Lang::format("sb_map", ["{map}"], [$this->arena->data["name"]]));
        $this->SetLine($player, 2, Lang::format("sb_time", ["{time}"], [$this->arena->GetTime(true)]));
        $this->SetLine($player, 3, Lang::format("sb_role", ["{role}"], [$this->arena->GetRolePretty($player)]));
        $this->SetLine($player, 4, "   ");

        //$this->SetLine($player, $line+1, "   ");
        $this->SetLine($player, 5, Lang::get("sb_description"));
        
    }
    







    public function SetLine(Player $player, int $score, string $text)
    {
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $this->id;
        $entry->type = 3;
        $entry->customName = " $text   ";
        $entry->score = $score;
        $entry->scoreboardId = $score;
        $pk = new SetScorePacket();
        $pk->type = 0;
        $pk->entries[$score] = $entry;
        
        //$player->sendDataPacket($pk);
        $player->getNetworkSession()->sendDataPacket($pk);
    }


    public function Update(Player $player) 
    {
        $this->RemoveScoreBoard($player);

        $pk = new SetDisplayObjectivePacket();
        $pk->displaySlot = "sidebar";
        $pk->objectiveName = $this->id;
        $pk->displayName = Lang::get("prefix");
        $pk->criteriaName = "dummy";
        $pk->sortOrder = 0;
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    public function RemoveScoreBoard(Player $player) 
    {
        $pk = new RemoveObjectivePacket();
        $pk->objectiveName = $this->id;
        $player->getNetworkSession()->sendDataPacket($pk);
    }


}