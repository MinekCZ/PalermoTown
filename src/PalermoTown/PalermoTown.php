<?php

namespace PalermoTown;

use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\World;

class PalermoTown extends PluginBase 
{

    /** @var PalermoTown */
    public static $palermoTown;

    public $commands;


    /** @var Arena[] */
    public array $arenas;

    
    
    public function onEnable() : void 
    {
        self::$palermoTown = $this;
        $this->getLogger()->info("§cLoading PalermoTown...");

        $this->commands = new Commands($this);
        $this->getServer()->getCommandMap()->register("palermotown", $this->commands, "PalermoTown");

        $this->saveResource("lang.yml");
        $lang = new Config($this->getDataFolder() . "lang.yml", Config::YAML);
        Lang::$lang = $lang->getAll();

        $this->Load();
    }

    public function Load() 
    {
        if(!is_dir($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }

        if(!is_dir($this->getDataFolder() . "data")) {
            @mkdir($this->getDataFolder() . "data");
        }

        if(!is_dir($this->getDataFolder() . "data\\saves")) {
            @mkdir($this->getDataFolder() . "data\\saves");
        }

        
        $this->arenas = ArenaLoader::LoadArenas();
    }
    
    
    public static function Get() : PalermoTown 
    {
        return self::$palermoTown;
    }

    public static function VecToString(Vector3 $vec) : string
    {
        return "{$vec->x},{$vec->y},{$vec->z}";
    }

    public static function StringToVec(string $str) : Vector3 
    {
        $split = explode(",", $str);

        if(count($split) != 3) return Vector3::zero();

        return new Vector3($split[0], $split[1], $split[2]);
    }


    public function saveMap(World $level) 
    {

        $level->save(true);

        $levelPath = $this->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $level->getFolderName();
        $target = $this->getDataFolder() . "data\\saves" . DIRECTORY_SEPARATOR . $level->getFolderName();

        $this->getServer()->getWorldManager()->unloadWorld($level);

        
        if(!is_dir($target)) {
            @mkdir($target);
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(realpath($levelPath)), \RecursiveIteratorIterator::LEAVES_ONLY);

        /** @var \SplFileInfo $file */
        foreach ($files as $file) 
        {
            
            if($file->isDir()) 
            {
                $localPath = substr($file->getPath(), strlen($this->getServer()->getDataPath() . "worlds"));


                if(!is_dir($target . "\.." . $localPath)) {
                    @mkdir($target . "\.." . $localPath);
                }
                
            }

            if($file->isFile()) 
            {
                $filePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename();
                $localPath = substr($file->getPath(), strlen($this->getServer()->getDataPath() . "worlds\\" . $level->getFolderName()));

                //var_dump($localPath . DIRECTORY_SEPARATOR . $file->getFilename());

                //var_dump($target . $localPath . "\\" . $file->getFilename());
                $ex = $file->getExtension();
                $name = $file->getFilename();
                if($ex == "log" || $name == "LOCK" || $name == "LOG") 
                {
                    continue;
                }
                var_dump($file->getFilename());

                copy($filePath, $target . $localPath . "\\" . $file->getFilename());
            }

        }
        

    }

    public function loadMap(string $folderName) :bool
    {

        if(!$this->getServer()->getWorldManager()->isWorldGenerated($folderName)) return false;

        if($this->getServer()->getWorldManager()->isWorldLoaded($folderName)) 
        {
            $this->getServer()->getWorldManager()->unloadWorld($this->getServer()->getWorldManager()->getWorldByName($folderName));
        }



        $levelpath = $this->getDataFolder() . "data\\saves\\" . $folderName;

        if(!is_dir($levelpath)) 
        {
            $this->getLogger()->error("Could not load map \"$folderName\". File was not found, try save level in setup");
        }

        $target = $this->getServer()->getDataPath() . "worlds\\" . $folderName;

        array_map('unlink', glob("$target\\db/*.*"));
        array_map('unlink', glob("$target\\db/*"));
        rmdir($target . "\\db");

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(realpath($levelpath)), \RecursiveIteratorIterator::LEAVES_ONLY);



        foreach ($files as $file) 
        {
            
            
            if($file->isDir()) 
            {
                $localPath = substr($file->getPath(), strlen($this->getDataFolder() . "data\\saves"));


                if(!is_dir($target . "\.." . $localPath)) {
                    @mkdir($target . "\.." . $localPath);
                }
                
            }

            if($file->isFile()) 
            {
                $filePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename();
                $localPath = substr($file->getPath(), strlen($this->getDataFolder() . "data\\saves\\" . $folderName));

                $ex = $file->getExtension();
                $name = $file->getFilename();
                if($ex == "log" || $name == "LOCK" || $name == "LOG") 
                {
                    continue;
                }

                copy($filePath, $target . $localPath . "\\" . $file->getFilename());
            }

        }

        //$this->getServer()->getWorldManager()->loadWorld($folderName);

        return true;
    }
}