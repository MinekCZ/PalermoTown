# PalermoTown
Pocketmine Minigame

In this minigame there are three main roles:
1. Murder - You have to kill everybody
2. Sherif - Your goal is to kill Murder
3. Innocent - Help sherif, survive as long as possible


# Features
- Easy to setup Arenas
- Easily editable language file
- Map saving
- Lobby and Game map

# How to setup

1. Create Arena: /pt admin create <ArenaName>

2. Go to Setup mode: /pt admin setup
(datas are saved in memory)

In setup mode it is possible to see all editable values using the command:
/pt info

3. Set basic info:
- /pt set name <arena_display_name>
- /pt set slots <max_players>
- /pt set world_lobby <lobby_world_name>
- /pt set world_game <game_world_name>
- /pt set save_lobby <true or false> | game map is saved automaticly, but you can also enable lobby save

4. Set position data:
- You can use tags to set position value.
- Availble tags are: player_pos ,  look_pos , look_block

- To setup spawns:
  - /pt set spawns 0 player_pos
  - /pt set spawns 1 player_pos
  - /pt set spawns 2 look_pos
  - etc...
- To setup chests you have to aim at the chests and use "look_block" tag
  - /pt set chests 0 look_block
  - /pt set chests 1 look_block
  - /pt set chests 2 look_block
  - etc...
- To setup lobby position (in the lobby world)
  - /pt set lobby player_pos
5. Last thing you want to do is save the map(s), save data and enable arena
  - /pt savelevels
  - /pt savedata
  - /pt set enable true
  - RESTART SERVER
6. The arena is ready to play

# Commands
  - Basic Commands:
    - /pt join <arena_name>
    - /pt leave
    - /pt list
  - Admin commands (op only)
    - /pt admin create <arena_name>
    - /pt admin remove <arena_name>
    - /pt admin setup
    - /pt | to list commands
  - Setup Commands (setup only)
    - /pt set <data> <value>
    - /pt set <data> <number> <value>
    - /pt info
    - /pt dump | show raw data
    - /pt dump [data] | show specific part of data | /pt dump spawns
    
# Upcoming features
  - Map vote
  - Better item shop
  - Cosmetics
# Known bugs
  - Works only on 4.0.0 Pocketmine version
  - This is beta release, for entertainment purposes only
  
# Author
 - This plugin was created by MinekCz
 - You can contact me on discord: Minek#2962
 
# Credit me
  - If you're making video please use poggit/github link.

# Video
  [![IMAGE ALT TEXT](http://img.youtube.com/vi/bmq_-e6qfGM/0.jpg)](http://www.youtube.com/watch?v=bmq_-e6qfGM "Watch on Youtube")

# Api

  ```php
  use MinekCz\PalermoTown\PalermoTown;

  PalermoTown::FindArena() :?Arena
  PalermoTown::GetAvailbleArenas() :array
  PalermoTown::IsInArena(Player $player) :bool
  PalermoTown::GetArenaByPlayer(Player $player) :?Arena
  PalermoTown::StartArena(Arena $arena) :void
  PalermoTown::EndArena(Arena $arena) :void
  PalermoTown::SetTime(Arena $arena, int $time) :void
  PalermoTown::GetArenaWorlds(Arena $arena) :array //[0 => gameWorld, 1 => lobbyWorld]
  PalermoTown::GetAllPlayer(Arena $arena) :array //Get all players in arena including spectators
  PalermoTown::GetArenaByName(string $id) :?Arena

  ´´´
