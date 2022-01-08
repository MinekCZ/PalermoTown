<?php

namespace PalermoTown;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;

class ArenaTask extends Task
{

    public PalermoTown $palermoTown;
    public Arena $arena;


    public function __construct(PalermoTown $palermotown, Arena $arena)
    {
        $this->palermoTown = $palermotown;
        $this->arena = $arena;
    }


    public function onRun(): void
    {
        if(!count($this->arena->players + $this->arena->spectators) > 0) return;
        $this->arena->CheckPlayers();

        switch($this->arena->state) 
        {
            case Arena::state_lobby:

                if(count($this->arena->players) <= 1) 
                {
                    $this->arena->lobbyTime = 15;
                    $this->arena->sendActionBar("§7Waiting for player...");
                    break;
                }

                $this->arena->CalculatePercents();

                
                $this->arena->lobbyTime--;

                foreach($this->arena->players as $player) 
                {
                    $player->sendActionBarMessage("§7Murder: {$this->arena->percents_final[$player->getName()]["murder"]}% §7Sherif: {$this->arena->percents_final[$player->getName()]["sherif"]}%  | Starting in: §a{$this->arena->lobbyTime}");
                }
                
                
                if($this->arena->lobbyTime == 0) 
                {
                    $this->arena->startGame();
                }
                break;
            case Arena::state_pregame:

                $this->arena->preGameTime--;

                $this->arena->sendActionBar("§7Murder get their sword in: §c{$this->arena->preGameTime}");
                

                if($this->arena->preGameTime == 0) 
                {
                    $this->arena->preGameEnd();
                }

                break;

            case Arena::state_game:

                $this->arena->gameTime--;

                /** @var Player */
                foreach($this->arena->players + $this->arena->spectators as $player) 
                {
                    $player->sendActionBarMessage("§7Role: {$this->arena->GetRolePretty($player)} §7| Time left: §a{$this->formatTime($this->arena->gameTime)}");
                }

                if($this->arena->gameTime == 0) 
                {
                    $this->arena->endGame();
                }

                break;
            case Arena::state_ending:

                $this->arena->endTime--;
                $this->arena->sendActionBar("§7Teleporting to lobby in: {$this->arena->endTime}");

                if($this->arena->endTime == 0) 
                {
                    $this->arena->finalEnd();
                }

                break;
        }
    }

    public static function formatTime(int $time): string 
    {
        return gmdate("i:s", $time); 
    }
}