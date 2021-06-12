<?php
declare(strict_types=1);
namespace aris;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ItemDespawnEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\entity\object\ItemEntity;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemFactory;
use pocketmine\tile\Tile;

use aris\utils\HopperConfig;

use formapi\CustomForm;
use formapi\SimpleForm;

class Main extends PluginBase implements Listener{
		/** @var HopperConfig */
		private $data;
		private $vip_data;
		
		/** @var Main */
		private static $instance;
		
		public static function hasInstance() : bool{
			return self::$instance !== null;
		}
	
		public static function getInstance() : self{
			return self::$instance;
		}

		public static function setInstance(Main $instance) : void{
			self::$instance = $instance;
		}
		public function onEnable(){
			$this->getServer()->getPluginManager()->registerEvents($this, $this);
			$this->setInstance($this);
			$this->init();
			$this->getLogger()->info("§aHopperify §fenabled!");
		}
		
		public function init(){
			BlockFactory::registerBlock(new Hopper(), true);
			Tile::registerTile(HopperTile::class, ["Hopper", "minecraft:hopper"]);
			Item::initCreativeItems();
			
			$config = $this->getConfig();
			$this->data = new HopperConfig(
				$config->getNested("normal.transfer.tick-rate"),
				$config->getNested("normal.transfer.per-tick"),
				$config->getNested("normal.item-sucking.tick-rate"),
				$config->getNested("normal.item-sucking.per-tick"),
				$config->getNested("normal.limit")
			);
			
			$this->vip_data = new HopperConfig(
				$config->getNested("vip.transfer.tick-rate"),
				$config->getNested("vip.transfer.per-tick"),
				$config->getNested("vip.item-sucking.tick-rate"),
				$config->getNested("vip.item-sucking.per-tick"),
				$config->getNested("vip.limit")
			);
		}
		
		public function getData() : HopperConfig{
			return $this->data;
		}
		
		public function getVipData() : HopperConfig{
			return $this->vip_data;
		}
		
		public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
			if($sender instanceof Player){	
				if($sender->hasPermission("hopper.cmd")){
					switch($cmd->getName()){
						case "hopper":
							if(!isset($args[0])){
								$sender->sendMessage("       §l§7HOPPERIFY §0- §bHELP     " .
									"\n    §r§8/§ahopper §6<set | debug | help>" . 
									"\n        §5set §0- §fOpen Setting Ui" . 
									"\n        §5debug §0- §fOpen Debugging Ui" .
									"\n        §5help §0- §fGet help message"
								);
							}else{
								switch($args[0]){
									case "set":
									case "set":
										if(!isset($args[1])){
											$sender->sendMessage("/hopper set <normal | vip>");
										}else{
											switch($args[1]){
												case "normal":
													$this->sendSettingUi($sender, false);
												break;
												case "vip":
													$this->sendSettingUi($sender, true);
												break;
											}
										}
									break;
									case "debug":
									case "d":
										$this->sendDebuggingUi($sender);
									break;
									case "help":
									case "h":
									case "?":
										$sender->sendMessage("       §l§7HOPPERIFY §0- §bHELP     " .
											"\n    §r§8/§ahopper §6<set | debug | help>" . 
											"\n        §5set §0- §fOpen Setting Ui" . 
											"\n        §5debug §0- §fOpen Debugging Ui" .
											"\n        §5help §0- §fGet help message"
										);
									break;
								}
							}
						break;
					}
				}else{
					$sender->sendMessage("§cYou don't have permission to use this command!");
					return false;
				}
			}else{
				$sender->sendMessage(" §cThis command is available in-game only!");
				return false;
			}
			return true;
		}
		
		public function onPlace(BlockPlaceEvent $event){
			$block = $event->getBlock();
			if($block instanceof Hopper){
				$block->check4Vip();
				$limit = $block->isVip() ? $this->vip_data->getLimit() : $this->data->getLimit();
				foreach($block->getLevel()->getTiles() as $tile){
					if($tile instanceof HopperTile) $limit--;
				}
				if($limit  <= 0){
					$event->getPlayer()->sendMessage("You've enough amount of hoppers");
					$event->setCancelled(true);
				}
			}
		}
		
		public function onItemSpawn(ItemSpawnEvent $event){
			$entity = $event->getEntity();
			if(is_null($entity->getLevel()->getEntity($entity->getId()))){
				$entity->getLevel()->addEntity($entity);
			}
		}
		
		public function onItemDespawn(ItemDespawnEvent $event){
			$entity = $event->getEntity();
			if(!is_null($entity->getLevel()->getEntity($entity->getId()))){
				$entity->getLevel()->removeEntity($entity);
			}
		}
		
		public function onInvPickUp(InventoryPickupItemEvent $event){
			$entity = $event->getItem();
			if(!is_null($entity->getLevel()->getEntity($entity->getId()))){
				$entity->getLevel()->removeEntity($entity);
			}
		}
		
		public function onDamage(EntityDamageEvent $event){
			$entity = $event->getEntity();
			if($entity instanceof ItemEntity){
				if(!is_null($entity->getLevel()->getEntity($entity->getId()))){
					//$this->getLogger()->info("Removing " . $entity->getId());
					$entity->getLevel()->removeEntity($entity);
				}
			}
		}
		public function sendSettingUi(Player $player, bool $is_vip = false) : void{
			if($is_vip){
				$title = "§lVIP HOPPER SETTING";
				$hopper_data = $this->vip_data;
			}else{
				$title = "§lNORMAL HOPPER SETTING";
				$hopper_data = $this->data;
			}
			$form = new CustomForm(function(Player $player, $data) use ($hopper_data){
				$result = $data;
			    if($result === null) return;
				$data = array_map(function($str){return empty($str) ? null : intval($str);}, $data);
				foreach($data as $d){
					//$this->getLogger()->info($d);
					if(!is_null($d) && $d <= 0){
						$player->sendMessage("Value cannot be lower than 1 and it musts be a number!");
						return;
					}
				}
				$hopper_data->setTransferTickRate(empty($data[1]) ? $hopper_data->getTransferTickRate() : $data[1]);
				$hopper_data->setTransferPerTick(empty($data[2]) ? $hopper_data->getTransferPerTick() : $data[2]);
				$hopper_data->setItemSuckingTickRate(empty($data[3]) ? $hopper_data->getItemSuckingTickRate() : $data[3]);
				$hopper_data->setItemSuckingPerTick(empty($data[4]) ? $hopper_data->getItemSuckingPerTick() : $data[4]);
				$hopper_data->setLimit(empty($data[5]) ? $hopper_data->getLimit() : $data[5]);
			});
			$form->setTitle($title);
			$form->addLabel("Please write the input on the input box below");
			$form->addInput("Transfer Tick Rate:", (string) $hopper_data->getTransferTickRate());
			$form->addInput("Transfer Per Tick:", (string) $hopper_data->getTransferPerTick());
			$form->addInput("Item Sucking Tick Rate:", (string) $hopper_data->getItemSuckingTickRate());
			$form->addInput("Item Sucking Per Tick:", (string) $hopper_data->getItemSuckingPerTick());
			$form->addInput("Hopper limit:", (string) $hopper_data->getLimit());
			$player->sendForm($form);
		}
		
		public function sendDebuggingUi(Player $player) : void{
			$form = new SimpleForm(function(Player $player, $data){
				$result = $data;
			    if($result === null) return;
			});
			$content = "§aNormal: \n" .
				"    §f-§b Transfer Tick Rate§f: §g" . $this->data->getTransferTickRate() . "\n" .
				"    §f-§b Transfer Per Tick§f: §g" . $this->data->getTransferPerTick() . "\n" .
				"    §f-§b Item Sucking Tick Rate§f: §g" . $this->data->getItemSuckingTickRate() . "\n" .
				"    §f-§b Item Sucking Per Tick§f: §g" . $this->data->getItemSuckingPerTick() . "\n" .
				"    §f-§b Hopper Limit§f: §g" . $this->data->getLimit() . "\n".
				"§aVip: \n" .
				"    §f-§b Transfer Tick Rate§f: §g" . $this->vip_data->getTransferTickRate() . "\n" .
				"    §f-§b Transfer Per Tick§f: §g" . $this->vip_data->getTransferPerTick() . "\n" .
				"    §f-§b Item Sucking Tick Rate§f: §g" . $this->vip_data->getItemSuckingTickRate() . "\n" .
				"    §f-§b Item Sucking Per Tick§f: §g" . $this->vip_data->getItemSuckingPerTick() . "\n" .
				"    §f-§b Hopper Limit§f: §g" . $this->vip_data->getLimit();
			$form->setTitle("§lDEBUG UI");
			$form->setContent($content);
			$form->addButton("§cExit", 0, "textures/blocks/barrier", "exit");
			$player->sendForm($form);
		}
		
		public function onDisable(){
			$config = $this->getConfig();
			$config->setNested("normal", [
				"transfer" => [
					"tick-rate" => $this->data->getTransferTickRate(),
					"per-tick" => $this->data->getTransferPerTick()
				],
				"item-sucking" => [
					"tick-rate" => $this->data->getItemSuckingTickRate(),
					"per-tick" => $this->data->getItemSuckingPerTick()
				],
				"limit" => $this->data->getLimit()
			]);
			$config->setNested("vip", [
				"transfer" => [
					"tick-rate" => $this->vip_data->getTransferTickRate(),
					"per-tick" => $this->vip_data->getTransferPerTick()
				],
				"item-sucking" => [
					"tick-rate" => $this->vip_data->getItemSuckingTickRate(),
					"per-tick" => $this->vip_data->getItemSuckingPerTick()
				],
				"limit" => $this->vip_data->getLimit()
			]);
			$config->save();
			$this->getLogger()->info("§cHopper §fdisabled!");
		}
}