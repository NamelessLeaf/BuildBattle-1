<?php

namespace WEATHERCRAFTYT1;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat as TE;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\entity\EntityInventoryChangeEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use WEATHERCRAFTYT1\ResetMap;
use WEATHERCRAFTYT1\API\ScoreAPI;
use pocketmine\level\sound\PopSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\item\Item;
use pocketmine\event\entity\EntityLevelChangeEvent;
use onebone\economyapi\EconomyAPI;

class BB extends PluginBase implements Listener {

        public $prefix = TE::GRAY . "[ " . TE::BLUE. TE::BOLD . "[Build" . TE::BLUE."Battle]". TE::RESET . TE::GRAY . " ]";
	public $mode = 0;
	public $arenas = array();
	public $currentLevel = "";
        public $op = array();
	public $score;
	
	public function onEnable()
	{
		$this->getLogger()->notice(TE::AQUA . "BuildBattle by WEATHERCRAFTYT1 ;)");
                $this->getServer()->getPluginManager()->registerEvents($this ,$this);
		$this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		@mkdir($this->getDataFolder());
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		if($config->get("arenas")!=null)
		{
			$this->arenas = $config->get("arenas");
		}
		foreach($this->arenas as $lev)
		{
			$this->getServer()->loadLevel($lev);
		}
                $temas = array("Bird","Fish","Car","Dragon","Internet","Frog","Mario Bros","Dog","Mage","Tractor","Boat","WaterFall","Tiger","AirPlane","Superhero","Soccer","MCPE Mobs-Monsters","WC Server","Anything","Shark","Cereal","Car","Tornado","Planets","Landscape","Train","House","Sports");
		if($config->get("temas")==null)
		{
			$config->set("temas",$temas);
		}
		$config->save();
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                $slots->save();
		$this->score = new ScoreAPI($this);
		$this->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 20);
	}
        
        public function onDisable() {
            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
            if($config->get("arenas")!=null)
            {
                    $this->arenas = $config->get("arenas");
            }
            foreach($this->arenas as $arena)
            {
                    for ($i = 1; $i <= 16; $i++) {
                        $slots->set("slot".$i.$arena, 0);
                    }
                    $slots->save();
                    $config->set($arena . "Join", 0);
                    $config->save();
                    $points = new Config($this->getDataFolder() . "/puntos".$arena.".yml", Config::YAML);
                    foreach($points->getAll() as $key => $w){
                        $points->set($key, 0);
                    }
                    $points->save();
                    $this->reload($arena);
            }
        }
        
        public function reload($lev)
        {
                if ($this->getServer()->isLevelLoaded($lev))
                {
                        $this->getServer()->unloadLevel($this->getServer()->getLevelByName($lev));
                }
                $zip = new \ZipArchive;
                $zip->open($this->getDataFolder() . 'arenas/' . $lev . '.zip');
                $zip->extractTo($this->getServer()->getDataPath() . 'worlds');
                $zip->close();
                unset($zip);
                return true;
        }
	
        public function enCambioMundo(EntityLevelChangeEvent $event)
        {
            $pl = $event->getEntity();
            if($pl instanceof Player)
            {
                $lev = $event->getOrigin();
                $level = $lev->getFolderName();
                if($lev instanceof Level && in_array($level,$this->arenas))
		{
                $pl->removeAllEffects();
                $pl->getInventory()->clearAll();
                $pl->setGamemode(0);
		$api = $this->score;
                    $api->remove($pl);
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                $limit = new Config($this->getDataFolder() . "/limit.yml", Config::YAML);
                $limit->set($pl->getName(), 0);
                $limit->save();
                for ($i = 1; $i <= 16; $i++) {
                    if($slots->get("slot".$i.$level)==$pl->getName())
                    {
                        $slots->set("slot".$i.$level, 0);
                    }
                }
                $slots->save();
                }
            }
        }
        
        public function enDrop(PlayerDropItemEvent $ev) {
            $player = $ev->getPlayer();
            if(in_array($player->getLevel()->getFolderName(),$this->arenas))
            {
                $ev->setCancelled();
            }
        }
        
        public function eninv(EntityInventoryChangeEvent $ev) {
            $level = $ev->getEntity()->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
                $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                if($config->get($level . "PlayTime") >= 470)
                {
                    $ev->setCancelled();
                }
                elseif($config->get($level."PlayTime")<170)
                {
                    $ev->setCancelled();
                }
            }
        }

        public function onLog(PlayerLoginEvent $event)
	{
		$player = $event->getPlayer();
                $player->setGamemode(0);
                if(in_array($player->getLevel()->getFolderName(),$this->arenas))
		{
                    $player->getInventory()->clearAll();
                    $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
                    $this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
                    $player->teleport($spawn,0,0);
                }
	}
        
        public function onQuit(PlayerQuitEvent $event)
        {
            $pl = $event->getPlayer();
            $level = $pl->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
                $pl->removeAllEffects();
                $pl->getInventory()->clearAll();
                $pl->setGamemode(0);
                $pl->setNameTag($pl->getName());
		$api = $this->score;
                    $api->remove($pl);   
                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                for ($i = 1; $i <= 16; $i++) {
                    if($slots->get("slot".$i.$level)==$pl->getName())
                    {
                        $slots->set("slot".$i.$level, 0);
                    }
                }
                $slots->save();
            }
        }
	
        public function Puntuar(PlayerItemHeldEvent $event) {
            $player = $event->getPlayer();
            $level = $player->getLevel()->getFolderName();
            if(in_array($level,$this->arenas))
            {
                $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                $time = $config->get($level . "PlayTime");
                if($time<=170)
                {
                    if($config->get("actual".$level)!=$player->getName())
                    {
                        if($event->getItem()->getDamage()==14)
                        {
                            $player->sendTip(TE::BOLD.TE::DARK_RED."SHAME TO MY EYES");
                        }
                        elseif($event->getItem()->getDamage()==6)
                        {
                            $player->sendTip(TE::BOLD.TE::RED."JUST POOP");
                        }
                        elseif($event->getItem()->getDamage()==5)
                        {
                            $player->sendTip(TE::BOLD.TE::GREEN."OK THATS FINE");
                        }
                        elseif($event->getItem()->getDamage()==13)
                        {
                            $player->sendTip(TE::BOLD.TE::DARK_GREEN."GOOD I DO LIKE IT");
                        }
                        elseif($event->getItem()->getDamage()==11)
                        {
                            $player->sendTip(TE::BOLD.TE::DARK_PURPLE."THIS IS VERY GOOD RECOMMENED");
                        }
                        elseif($event->getItem()->getDamage()==4)
                        {
                            $player->sendTip(TE::BOLD.TE::GOLD."NOW THIS SHOULD BE ON THE SERVER");
                        }
                    }
                    else
                    {
                        $player->sendTip(TE::BOLD.TE::RED."You cant vote your own Plot Silly.");
                    }
                }
            }
        }
        
        public function getPoints($damage){
            if($damage == 14){
                return 1;
            }
            if($damage == 6){
                return 2;
            }
            if($damage == 5){
                return 3;
            }
            if($damage == 13){
                return 4;
            }
            if($damage == 11){
                return 5;
            }
            if($damage == 4){
                return 6;
            }
            return 1;
        }
        
        public function getConfirm($damage){
            if($damage == 14){
                return TE::DARK_RED."SHAME TO MY EYES";
            }
            if($damage == 6){
                return TE::RED."JUST POOP";
            }
            if($damage == 5){
                return TE::GREEN."OK THATS FINE";
            }
            if($damage == 13){
                return TE::DARK_GREEN."GOOD I DO LIKE IT";
            }
            if($damage == 11){
                return TE::DARK_PURPLE."THIS IS VERY GOOD RECOMMENDED";
            }
            if($damage == 4){
                return TE::GOLD."THIS SHOULD BE ON THE SERVER";
            }
            return TE::DARK_RED."SHAME TO MY EYES";
        }
        
        public function onMov(PlayerMoveEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
                    $limit = new Config($this->getDataFolder() . "/limit.yml", Config::YAML);
                    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                    if($config->get($level . "PlayTime") < 470 && $config->get($level . "PlayTime") > 170)
                    {
                        if($limit->get($player->getName()) != null)
                        {
                            $pos = $limit->get($player->getName());
                            if($player->x>$pos[0]+13.5 || $player->x<$pos[0]-13.5 || $player->y>$pos[1]+20 || $player->y<$pos[1]-1 || $player->z>$pos[2]+13.5 || $player->z<$pos[2]-13.5)
                            {
                                $event->setCancelled();
                            }
                        }
                    }
		}
	}
	
	public function onBlockBr(BlockBreakEvent $event)
	{
		$player = $event->getPlayer();
                $block = $event->getBlock();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
                    $limit = new Config($this->getDataFolder() . "/limit.yml", Config::YAML);
                    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                    if($config->get($level . "PlayTime") != null)
                    {
                            if($config->get($level . "PlayTime") >= 470)
                            {
                                    $event->setCancelled();
                            }
                    }
                    if($limit->get($player->getName()) != null)
                    {
                        $pos = $limit->get($player->getName());
                        if($block->getX()>$pos[0]+13.5 || $block->getX()<$pos[0]-13.5 || $block->getY()>$pos[1]+20 || $block->getY()<$pos[1]-1 || $block->getZ()>$pos[2]+13.5 || $block->getZ()<$pos[2]-13.5)
                        {
                            $event->setCancelled();
                        }
                    }
		}
	}
        
        public function onBlockPla(BlockPlaceEvent $event)
	{
		$player = $event->getPlayer();
                $block = $event->getBlock();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
                    $limit = new Config($this->getDataFolder() . "/limit.yml", Config::YAML);
                    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                    if($config->get($level . "PlayTime") != null)
                    {
                            if($config->get($level . "PlayTime") >= 470)
                            {
                                    $event->setCancelled();
                            }
                    }
                    if($limit->get($player->getName()) != null)
                    {
                        $pos = $limit->get($player->getName());
                        if($block->getX()>$pos[0]+13.5 || $block->getX()<$pos[0]-13.5 || $block->getY()>$pos[1]+20 || $block->getY()<$pos[1]-1 || $block->getZ()>$pos[2]+13.5 || $block->getZ()<$pos[2]-13.5)
                        {
                            $event->setCancelled();
                        }
                    }
		}
	}
	
	public function onCommand(CommandSender $player, Command $cmd, $label, array $args) : bool{
        switch($cmd->getName()){
			case "bb":
                            if($player->isOp())
                            {
                                if(!empty($args[0]))
				{
                                    if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[0]))
                                    {
                                            $this->getServer()->loadLevel($args[0]);
                                            $this->getServer()->getLevelByName($args[0])->loadChunk($this->getServer()->getLevelByName($args[0])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[0])->getSafeSpawn()->getFloorZ());
                                            array_push($this->arenas,$args[0]);
                                            $this->currentLevel = $args[0];
                                            $this->mode = 1;
                                            $player->sendMessage($this->prefix . "Please start putting the spawns! Make sure it is in the middle.");
                                            $player->setGamemode(1);
                                            array_push($this->op, $player->getName());
                                            $player->teleport($this->getServer()->getLevelByName($args[0])->getSafeSpawn(),0,0);
                                            $name = $args[0];
                                            $this->zipper($player, $name);
                                    }
                                    else
                                    {
                                            $player->sendMessage($this->prefix . "ERROR missing world.");
                                    }
                                }
                            }
			return true;
			case 'bbnpc':
                                    if ($player->isOp()) {
                                        $npc = new EntityManager($this);
                                        $npc->setEntity($player);
                                    }
                                return true;
	}
        }
        
        public function PlayerInteractEvent(PlayerInteractEvent $ev){
            $item = $ev->getItem();
            if($item->getId() === Item::SPAWN_EGG){
                $ev->setCancelled();
            }
        }
	
	public function onInteract(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);
		
		if($tile instanceof Sign) 
		{
			if(($this->mode==26)&&(in_array($player->getName(), $this->op)))
			{
				$tile->setText(TE::AQUA . "§7[ §fJoin §7]",TE::GREEN  . "§e0 / 16","§f" . $this->currentLevel,$this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . "Arena Sucess!");
                                array_shift($this->op);
			}
			else
			{
				$text = $tile->getText();
				if($text[3] == $this->prefix)
				{
					if($text[0]==TE::AQUA . "§7[ §fJoin §7]")
					{
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                                                $slots = new Config($this->getDataFolder() . "/slots.yml", Config::YAML);
                                                $namemap = str_replace("§f", "", $text[2]);
                                                $level = $this->getServer()->getLevelByName($namemap);
                                                for ($i = 1; $i <= 16; $i++) {
                                                    if($slots->get("slot".$i.$namemap)==null)
                                                    {
                                                            $thespawn = $config->get($namemap . "Spawn17");
                                                            $slots->set("slot".$i.$namemap, $player->getName());
                                                            goto with;
                                                    }
                                                }
                                                $player->sendMessage($this->prefix."No Slots");
                                                goto sinslots;
                                                with:
                                                $slots->save();
						$player->getInventory()->clearAll();
                                                $player->sendMessage($this->prefix . "You Entered A BuildBattle Match");
                                                foreach($level->getPlayers() as $playersinarena)
                                                {
                                                $playersinarena->sendMessage($player->getNameTag() .TE::BLUE. " joined the game!");
                                                }
                                                $spawn = new Position($thespawn[0]+0.5,$thespawn[1],$thespawn[2]+0.5,$level);
						$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
						$player->teleport($spawn,0,0);
						$player->getInventory()->clearAll();
                                                $player->removeAllEffects();
                                                $player->setMaxHealth(20);
						$api = $this->plugin->score;
                                                $api->new($player, $player->getName(), TE::BOLD.TE::BLUE."[Build".TE::BOLD.TE::BLUE."Battle]");
                                                $i = 0;
                                                $lines = [
                                                TE::WHITE."   ",
                                                TE::BLUE."Players: §bDisabled",
                                                TE::WHITE."   ",
                                                TE::BLUE."Starting in: ".TE::AQUA.$timeToStart,
                                                TE::WHITE."   ",
                                                TE::BLUE."Mode: §bSolo",
                                                TE::WHITE."    ",
                                                TE::BLUE."playwcserver.ddns.net ",
                                                ];
                                                foreach($lines as $line){
                                                        if($i < 15){
                                                           $i++;
                                                        $api->setLine($pla, $i, $line);
                                                           }
                                                }
                                                $player->setHealth(20);
                                                $player->setFood(20);
                                                sinslots:
					}
					else
					{
						$player->sendMessage($this->prefix . "You cannot join!");
					}
				}
			}
		}
		elseif(in_array($player->getName(), $this->op)&& $this->mode>=1 && $this->mode<=16)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+1,$block->getZ()));
			$player->sendMessage($this->prefix . "Spawn ".$this->mode." Registered!");
			$this->mode++;
			$config->save();
		}
		elseif(in_array($player->getName(), $this->op)&&$this->mode==17)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+1,$block->getZ()));
			$player->sendMessage($this->prefix . "Spawn Lobby Registrered!");
			$config->set("arenas",$this->arenas);
                        $config->set($this->currentLevel . "Start", 0);
			$player->sendMessage($this->prefix . "Touch a spawn to registered Arena!");
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn,0,0);
			$config->save();
			$this->mode=26;
		}
	}
	
	public function refreshArenas()
	{
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$config->set("arenas",$this->arenas);
		foreach($this->arenas as $arena)
		{
			$config->set($arena . "PlayTime", 470);
			$config->set($arena . "StartTime", 30);
		}
		$config->save();
	}
        
        public function zipper($player, $name)
        {
        $path = realpath($player->getServer()->getDataPath() . 'worlds/' . $name);
				$zip = new \ZipArchive;
				@mkdir($this->getDataFolder() . 'arenas/', 0755);
				$zip->open($this->getDataFolder() . 'arenas/' . $name . '.zip', $zip::CREATE | $zip::OVERWRITE);
				$files = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($path),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);
                                foreach ($files as $datos) {
					if (!$datos->isDir()) {
						$relativePath = $name . '/' . substr($datos, strlen($path) + 1);
						$zip->addFile($datos, $relativePath);
					}
				}
				$zip->close();
				$player->getServer()->loadLevel($name);
				unset($zip, $path, $files);
        }
}

class RefreshSigns extends BBTask {
    
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
                $this->prefix = $this->plugin->prefix;
		parent::__construct($plugin);
	}
  
	public function onRun($tick)
	{
		$allplayers = $this->plugin->getServer()->getOnlinePlayers();
		$level = $this->plugin->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Sign) {	
				$text = $t->getText();
				if($text[3]==$this->prefix)
				{
					$aop = 0;
                                        $namemap = str_replace("§f", "", $text[2]);
					foreach($allplayers as $player){if($player->getLevel()->getFolderName()==$namemap){$aop=$aop+1;}}
					$ingame = TE::AQUA . "§7[ §fJoin §7]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($namemap . "PlayTime")!=470)
					{
						$ingame = TE::DARK_PURPLE . "§7[ §cGame Running §7]";
					}
					elseif($aop>=16)
					{
						$ingame = TE::GOLD . "§7[ §9Full §7]";
					}
                                        $t->setText($ingame,TE::YELLOW  . $aop . " / 16",$text[2],$this->prefix);
				}
			}
		}
	}
}

class GameSender extends BBTask {
    public $prefix = "";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
                $this->prefix = $this->plugin->prefix;
		parent::__construct($plugin);
	}
        
        public function getResetmap() {
        Return new ResetMap($this);
        }
  
	public function onRun($tick)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
                $slots = new Config($this->plugin->getDataFolder() . "/slots.yml", Config::YAML);
		$arenas = $config->get("arenas");
		if(!empty($arenas))
		{
			foreach($arenas as $arena)
			{
				$time = $config->get($arena . "PlayTime");
				$timeToStart = $config->get($arena . "StartTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);
				if($levelArena instanceof Level)
				{
					$playersArena = $levelArena->getPlayers();
					if(count($playersArena)==0)
					{
						$config->set($arena . "PlayTime", 470);
						$config->set($arena . "StartTime", 30);
                                                $config->set($arena . "start", 0);
					}
					else
					{
                                                if(count($playersArena)>=2)
                                                {
                                                    $config->set($arena . "start", 1);
                                                    $config->save();
                                                }
						if($config->get($arena . "start")==1)
						{
							if($timeToStart>0)
							{
								$timeToStart--;
								foreach($playersArena as $pl)
								{
									$pl->sendTip(TE::BLUE."Game will start in ".TE::AQUA . $timeToStart . TE::RESET);
                                                                        if($timeToStart<=5)
                                                                        {
                                                                        $levelArena->addSound(new PopSound($pl));
                                                                        }
                                                                        if($timeToStart<=0)
                                                                        {
                                                                        $levelArena->addSound(new AnvilUseSound($pl));
                                                                        $temas = array_rand($config->get("temas"));
                                                                        $tema = $config->get("temas")[$temas];
                                                                        $config->set($arena . "tema", $tema);
                                                                        }
								}
                                                                if($timeToStart==29)
                                                                {
                                                                    $levelArena->setTime(7000);
                                                                    $levelArena->stopTime();
                                                                }
								if($timeToStart==10)
								{
                                                                   $pl->broadcastMessage(TE::RED."10\n§ePrepare to build");
								}
                                                                if($timeToStart==5)
								{
                                                                   $pl->broadcastMessage(TE::RED."5\n§ePrepare to build");
								}
                                                                if($timeToStart==4)
								{
                                                                   $pl->broadcastMessage(TE::RED."4\n§ePrepare to build");
								}
								if($timeToStart==3)
								{
                                                                   $pl->broadcastMessage(TE::RED."3\n§ePrepare to build");
								}
								if($timeToStart==2)
								{
                                                                   $pl->broadcastMessage(TE::RED."2\n§ePrepare to build");
								}
								if($timeToStart==1)
								{
                                                                   $pl->broadcastMessage(TE::RED."1\n§ePrepare to build");
								}
								if($timeToStart<=0)
								{
                                                                    foreach($playersArena as $pla)
                                                                    {
                                                                        if($slots->get("slot1".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn1");
                                                                        }
                                                                        elseif($slots->get("slot2".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn2");
                                                                        }
                                                                        elseif($slots->get("slot3".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn3");
                                                                        }
                                                                        elseif($slots->get("slot4".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn4");
                                                                        }
                                                                        elseif($slots->get("slot5".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn5");
                                                                        }
                                                                        elseif($slots->get("slot6".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn6");
                                                                        }
                                                                        elseif($slots->get("slot7".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn7");
                                                                        }
                                                                        elseif($slots->get("slot8".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn8");
                                                                        }
                                                                        elseif($slots->get("slot9".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn9");
                                                                        }
                                                                        elseif($slots->get("slot10".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn10");
                                                                        }
                                                                        elseif($slots->get("slot11".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn11");
                                                                        }
                                                                        elseif($slots->get("slot12".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn12");
                                                                        }
                                                                        elseif($slots->get("slot13".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn13");
                                                                        }
                                                                        elseif($slots->get("slot14".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn14");
                                                                        }
                                                                        elseif($slots->get("slot15".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn15");
                                                                        }
                                                                        elseif($slots->get("slot16".$arena)==$pla->getName())
                                                                        {
                                                                                $thespawn = $config->get($arena . "Spawn16");
                                                                        }
                                                                        $spawn = new Position($thespawn[0]+0.5,$thespawn[1],$thespawn[2]+0.5,$levelArena);
                                                                        $pla->teleport($spawn,0,0);
                                                                    }
								}
								$config->set($arena . "StartTime", $timeToStart);
							}
							else
							{
                                                                $tema = $config->get($arena . "tema");
                                                                $second = ($time - 170) % 60;
                                                                $timer = ($time - 170 - $second) / 60;
                                                                $minutes = $timer % 60;
                                                                $seconds = str_pad($second, 2, "0", STR_PAD_LEFT);
                                                                if($time>170)
                                                                {
                                                                    foreach($playersArena as $pla)
                                                                    {
                                                                        $pla->sendTip(TE::BOLD.TE::AQUA."Theme: " .TE::GREEN. $tema .TE::AQUA. "  Time Left: ".TE::YELLOW.$minutes.TE::DARK_PURPLE.":".TE::YELLOW.$seconds. TE::RESET);
                                                                    }
                                                                }
								$time--;
								if($time == 469)
								{
                                                                    $laimit = new Config($this->plugin->getDataFolder() . "/limit.yml", Config::YAML);
									foreach($playersArena as $pl)
									{
                                                                            $pl->sendMessage(TE::BOLD.TE::GREEN.">--------------------------------------------------------------------------------");
                                                                            $pl->sendMessage(TE::BOLD.TE::YELLOW."> ".TE::YELLOW."Build something relevant to the theme: ".TE::BOLD.TE::AQUA. $tema);
                                                                            $pl->sendMessage(TE::BOLD.TE::YELLOW."> ".TE::YELLOW."When the timer ends you can");
                                                                            $pl->sendMessage(TE::BOLD.TE::YELLOW."> ".TE::BLUE."vote of the build you like the best!");
									    $pl->sendMessage(TE::YELLOW."> ".TE::BLUE."Time ".TE::AQUA."5".TE::GREEN." minutes");
                                                                            $pl->sendMessage(TE::BOLD.TE::GREEN.">--------------§bplaywcserver.ddns.net§a-----------------------------------------");
							                    $pl->setGamemode(1);
									    $api = $this->score;
                                                                                    $api->remove($pl);
									    $api = $this->plugin->score;
                                                                            $api->new($pla, $pla->getName(), TE::BOLD.TE::BLUE."[Run".TE::BOLD.TE::BLUE." Of The".TE::BOLD.TE::BLUE." Beast]");
                                                                            $i = 0;
                                                                            $lines = [
                                                                            TE::WHITE."   ",
                                                                            TE::BLUE."Beasts: ".TE::AQUA.$bestia,
                                                                            TE::WHITE."   ",
                                                                            TE::BLUE."Runner: ".TE::AQUA.$corredor,
                                                                            TE::WHITE."   ",
                                                                            TE::BLUE."Distance: ".TE::AQUA.$dist,
                                                                            TE::WHITE."    ",
                                                                            TE::BLUE."Time: ".TE::AQUA.$time,
                                                                            TE::WHITE."   ",
                                                                            TE::BLUE."playwcserver.ddns.net ",
                                                                            ];
                                                                            foreach($lines as $line){
                                                                        	    if($i < 15){
                                                                                  	$i++;
                                                                   	          $api->setLine($pla, $i, $line);
                                                                        	}
                                                                        }
                                                                            if($slots->get("slot1".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn1");
                                                                            }
                                                                            elseif($slots->get("slot2".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn2");
                                                                            }
                                                                            elseif($slots->get("slot3".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn3");
                                                                            }
                                                                            elseif($slots->get("slot4".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn4");
                                                                            }
                                                                            elseif($slots->get("slot5".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn5");
                                                                            }
                                                                            elseif($slots->get("slot6".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn6");
                                                                            }
                                                                            elseif($slots->get("slot7".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn7");
                                                                            }
                                                                            elseif($slots->get("slot8".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn8");
                                                                            }
                                                                            elseif($slots->get("slot9".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn9");
                                                                            }
                                                                            elseif($slots->get("slot10".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn10");
                                                                            }
                                                                            elseif($slots->get("slot11".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn11");
                                                                            }
                                                                            elseif($slots->get("slot12".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn12");
                                                                            }
                                                                            elseif($slots->get("slot13".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn13");
                                                                            }
                                                                            elseif($slots->get("slot14".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn14");
                                                                            }
                                                                            elseif($slots->get("slot15".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn15");
                                                                            }
                                                                            elseif($slots->get("slot16".$arena)==$pl->getName())
                                                                            {
                                                                                    $limite = $config->get($arena . "Spawn16");
                                                                            }
                                                                            $laimit->set($pl->getName(), $limite);
                                                                            $laimit->save();
									}
								}
								if($time>170)
                                                                {
                                                                    $time2 = $time-170;
									$minutes = $time2 / 60;
									if(is_int($minutes) && $minutes>0)
									{
										foreach($playersArena as $pl)
										{
											$pl->sendMessage($this->prefix .TE::YELLOW. $minutes . " " .TE::GREEN."minutes remaining");
										}
									}
									elseif($time2 == 30 || $time2 == 15 || $time2 == 10 || $time2 ==5 || $time2 ==4 || $time2 ==3 || $time2 ==2 || $time2 ==1)
									{
										foreach($playersArena as $pl)
										{
											$pl->sendMessage($this->prefix .TE::YELLOW. $time2 . " " .TE::GREEN. "seconds remaining");
                                                                                        $levelArena->addSound(new PopSound($pl));
										}
									}
								}
                                                                else
                                                                {
                                                                    if($time == 166 || $time == 156 || $time == 146 || $time ==136 || $time ==126 || $time ==116 || $time ==106 || $time ==96 || $time ==86 || $time ==76 || $time ==66 || $time ==56 || $time ==46 || $time ==36 || $time ==26)
                                                                    {
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                    $pl->sendMessage($this->prefix .TE::GREEN. "Next " .TE::YELLOW. "5" .TE::GREEN. " seconds");
                                                                                    $levelArena->addSound(new PopSound($pl));
                                                                            }
                                                                    }
                                                                    elseif($time == 165 || $time == 155 || $time == 145 || $time ==135 || $time ==125 || $time ==115 || $time ==105 || $time ==95 || $time ==85 || $time ==75 || $time ==65 || $time ==55 || $time ==45 || $time ==35 || $time ==25)
                                                                    {
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                    $pl->sendMessage($this->prefix .TE::GREEN. "Next " .TE::YELLOW. "4" .TE::GREEN. " seconds");
                                                                                    $levelArena->addSound(new PopSound($pl));
                                                                            }
                                                                    }
                                                                    elseif($time == 164 || $time == 154 || $time == 144 || $time ==134 || $time ==124 || $time ==114 || $time ==104 || $time ==94 || $time ==84 || $time ==74 || $time ==64 || $time ==54 || $time ==44 || $time ==34 || $time ==24)
                                                                    {
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                    $pl->sendMessage($this->prefix .TE::GREEN. "Next " .TE::YELLOW. "3" .TE::GREEN. " seconds");
                                                                                    $levelArena->addSound(new PopSound($pl));
                                                                            }
                                                                    }
                                                                    elseif($time == 163 || $time == 153 || $time == 143 || $time ==133 || $time ==123 || $time ==113 || $time ==103 || $time ==93 || $time ==83 || $time ==73 || $time ==63 || $time ==53 || $time ==43 || $time ==33 || $time ==23)
                                                                    {
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                    $pl->sendMessage($this->prefix .TE::GREEN. "Next " .TE::YELLOW. "2" .TE::GREEN. " seconds");
                                                                                    $levelArena->addSound(new PopSound($pl));
                                                                            }
                                                                    }
                                                                    elseif($time == 162 || $time == 152 || $time == 142 || $time ==132 || $time ==122 || $time ==112 || $time ==102 || $time ==92 || $time ==82 || $time ==72 || $time ==62 || $time ==52 || $time ==42 || $time ==32 || $time ==22)
                                                                    {
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                    $pl->sendMessage($this->prefix .TE::GREEN. "Next " .TE::YELLOW. "1" .TE::GREEN. " seconds");
                                                                                    $levelArena->addSound(new PopSound($pl));
                                                                            }
                                                                    }
                                                                    elseif($time == 161 || $time == 151 || $time == 141 || $time ==131 || $time ==121 || $time ==111 || $time ==101 || $time ==91 || $time ==81 || $time ==71 || $time ==61 || $time ==51 || $time ==41 || $time ==31 || $time ==21)
                                                                    {
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                    $pl->sendMessage($this->prefix .TE::GREEN. "Next " .TE::YELLOW. "0" .TE::GREEN. " seconds");
                                                                                    $levelArena->addSound(new PopSound($pl));
                                                                            }
                                                                    }
                                                                    if($time==11 || $time==21 || $time==31 || $time==41 || $time==51 || $time==61 || $time==71 || $time==81 || $time==91 || $time==101 || $time==111 || $time==121 || $time==131 || $time==141 || $time==151 || $time==161)
                                                                    {
                                                                        $points = new Config($this->plugin->getDataFolder() . "/puntos".$arena.".yml", Config::YAML);
                                                                        foreach ($playersArena as $pl)
                                                                        {
                                                                            $yo = $config->get("actual".$arena);
                                                                            if($yo!=$pl->getName())
                                                                            {
                                                                                $pts = $points->get($yo);
                                                                                $dam = $pl->getInventory()->getItemInHand()->getDamage();
                                                                                $puntos = $this->plugin->getPoints($dam);
                                                                                $voto = $this->plugin->getConfirm($dam);
                                                                                $tot = $pts + $puntos;
                                                                                $points->set($config->get("actual".$arena), $tot);
                                                                                $points->save();
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. " You Have Confirmed Vote: ".TE::BOLD.$voto);
                                                                            }
                                                                        }
                                                                    }
                                                                    if($time==170)
                                                                    {
                                                                        $points = new Config($this->plugin->getDataFolder() . "/puntos".$arena.".yml", Config::YAML);
                                                                        foreach($playersArena as $pl)
                                                                        {
                                                                            $pl->getInventory()->clearAll();
                                                                            $pl->getInventory()->setItem(0,Item::get(159,14,1));
                                                                            $pl->getInventory()->setItem(1,Item::get(159,6,1));
                                                                            $pl->getInventory()->setItem(2,Item::get(159,5,1));
                                                                            $pl->getInventory()->setItem(3,Item::get(159,13,1));
                                                                            $pl->getInventory()->setItem(4,Item::get(159,11,1));
                                                                            $pl->getInventory()->setItem(5,Item::get(159,4,1));
                                                                            $points->set($pl->getName(), 0);
                                                                            $points->save();
                                                                        }
                                                                        if($slots->get("slot1".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot1".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn1");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 160;
                                                                        }
                                                                    }
                                                                    if($time==160)
                                                                    {
                                                                        if($slots->get("slot2".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot2".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn2");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 150;
                                                                        }
                                                                    }
                                                                    if($time==150)
                                                                    {
                                                                        if($slots->get("slot3".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot3".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn3");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 140;
                                                                        }
                                                                    }
                                                                    if($time==140)
                                                                    {
                                                                        if($slots->get("slot4".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot4".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn4");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 130;
                                                                        }
                                                                    }
                                                                    if($time==130)
                                                                    {
                                                                        if($slots->get("slot5".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot5".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn5");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 120;
                                                                        }
                                                                    }
                                                                    if($time==120)
                                                                    {
                                                                        if($slots->get("slot6".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot6".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn6");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 110;
                                                                        }
                                                                    }
                                                                    if($time==110)
                                                                    {
                                                                        if($slots->get("slot7".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot7".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn7");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder!: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 100;
                                                                        }
                                                                    }
                                                                    if($time==100)
                                                                    {
                                                                        if($slots->get("slot8".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot8".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn8");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 90;
                                                                        }
                                                                    }
                                                                    if($time==90)
                                                                    {
                                                                        if($slots->get("slot9".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot9".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn9");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 80;
                                                                        }
                                                                    }
                                                                    if($time==80)
                                                                    {
                                                                        if($slots->get("slot10".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot10".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn10");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 70;
                                                                        }
                                                                    }
                                                                    if($time==70)
                                                                    {
                                                                        if($slots->get("slot11".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot11".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn11");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 60;
                                                                        }
                                                                    }
                                                                    if($time==60)
                                                                    {
                                                                        if($slots->get("slot12".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot12".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn12");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 50;
                                                                        }
                                                                    }
                                                                    if($time==50)
                                                                    {
                                                                        if($slots->get("slot13".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot13".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn13");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 40;
                                                                        }
                                                                    }
                                                                    if($time==40)
                                                                    {
                                                                        if($slots->get("slot14".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot14".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn14");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Bravo he builted this thanks: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 30;
                                                                        }
                                                                    }
                                                                    if($time==30)
                                                                    {
                                                                        if($slots->get("slot15".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot15".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn15");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        } 
                                                                        else
                                                                        {
                                                                            $time = 20;
                                                                        }
                                                                    }
                                                                    if($time==20)
                                                                    {
                                                                        if($slots->get("slot16".$arena)!=null)
                                                                        {
                                                                            $actual = $slots->get("slot16".$arena);
                                                                            $config->set("actual".$arena, $actual);
                                                                            $thespawn = $config->get($arena . "Spawn16");
                                                                            foreach($playersArena as $pl)
                                                                            {
                                                                                $rand = mt_rand(1, 2);
                                                                                $ra = mt_rand(1, 6);
                                                                                if($rand==1)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]+$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                elseif($rand==2)
                                                                                {
                                                                                $spawn = new Position($thespawn[0]-$ra,$thespawn[1]+21,$thespawn[2]+0.5,$levelArena);
                                                                                }
                                                                                $pl->teleport($spawn,0,0);
                                                                                $pl->sendMessage($this->prefix .TE::AQUA.$tema);
                                                                                $pl->sendMessage($this->prefix .TE::YELLOW. "Builder: " .TE::WHITE. TE::AQUA. $actual);
                                                                            }
                                                                        }
                                                                        else
                                                                        {
                                                                            $time = 10;
                                                                        }
                                                                    }
                                                                    if($time==10)
                                                                    {
                                                                        $points = new Config($this->plugin->getDataFolder() . "/puntos".$arena.".yml", Config::YAML);
                                                                        $limit = new Config($this->plugin->getDataFolder() . "/limit.yml", Config::YAML);
                                                                        $paints = $points->getAll();
                                                                        $values = array();
                                                                        foreach($paints as $key => $w){
                                                                            array_push($values, $w);
                                                                        }
                                                                        natsort($values);
                                                                        $val = array_reverse($values);
                                                                        $max = max($values);
                                                                        $quien = array_search($max, $paints);
                                                                        $thesp = $limit->get($quien);
									$this->plugin->getServer()->broadcastMessage($this->prefix .TE::YELLOW. "-----------------------------------------------");
                                                                        $this->plugin->getServer()->broadcastMessage($this->prefix .TE::YELLOW. ">> ".TE::AQUA."BUILD BATTLE WINNERS!!! $arena ".TE::GREEN."($tema)");
                                                                        $this->plugin->getServer()->broadcastMessage($this->prefix .TE::YELLOW. "1ST°: ".TE::AQUA.$quien." ".TE::GREEN.$max);
			                                                $this->plugin->getServer()->broadcastMessage($this->prefix .TE::YELLOW. "Reward for the winner°: ".TE::AQUA.$quien." ".TE::GREEN.$max);
									$this->plugin->getServer()->broadcastMessage($this->prefix .TE::YELLOW. "-----------------------------------------------");
									if(!empty($this->plugin->api))
                                                                             {
                                                                             $this->plugin->api->addMoney($quien,150);
                                                                             }
                                                                        foreach($playersArena as $pl) {
                                                                            $puntaje = $points->get($pl->getName());
                                                                            $tupos = 1 + array_search($puntaje, $val);
                                                                            $rand = mt_rand(1, 2);
                                                                            $ra = mt_rand(1, 6);
                                                                            if($rand==1)
                                                                            {
                                                                            $spawn = new Position($thesp[0]+$ra,$thesp[1]+21,$thesp[2]+0.5,$levelArena);
                                                                            }
                                                                            elseif($rand==2)
                                                                            {
                                                                            $spawn = new Position($thesp[0]-$ra,$thesp[1]+21,$thesp[2]+0.5,$levelArena);
                                                                            }
                                                                            $pl->sendMessage($this->prefix .TE::GREEN. "Percentage of how players rated you: ".TE::YELLOW.$tupos."°: ".TE::GREEN.$puntaje);
                                                                            $pl->teleport($spawn,0,0);
                                                                        }
                                                                    }
                                                                    if($time<=0)
                                                                    {
                                                                        $limit = new Config($this->plugin->getDataFolder() . "/limit.yml", Config::YAML);
                                                                        foreach($playersArena as $pl)
                                                                        {
                                                                            $limit->set($pl->getName(), 0);
                                                                            $limit->save();
                                                                            $pl->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(),0,0);
                                                                            $pl->setGamemode(0);
                                                                            $pl->getInventory()->clearAll();
                                                                            $pl->removeAllEffects();
                                                                            $pl->setFood(20);
                                                                            $pl->setHealth(20);
                                                                        }
                                                                        $this->getResetmap()->reload($levelArena);
                                                                        $config->set($arena . "Join", 0);
                                                                        $config->save();
                                                                        $points = new Config($this->plugin->getDataFolder() . "/puntos".$arena.".yml", Config::YAML);
                                                                        foreach($points->getAll() as $key => $w){
                                                                            $points->set($key, 0);
                                                                        }
                                                                        $points->save();
                                                                        $time = 470;
                                                                    }
                                                                }
								$config->set($arena . "PlayTime", $time);
							}
						}
						else
						{
                                                    foreach($playersArena as $pl)
                                                    {
                                                            $pl->sendTip(TE::YELLOW . "Need more players to begin. Please be patient!!" .TE::RESET);
                                                    }
                                                    $config->set($arena . "PlayTime", 470);
                                                    $config->set($arena . "StartTime", 30);
						}
					}
				}
			}
		}
		$config->save();
	}
}
