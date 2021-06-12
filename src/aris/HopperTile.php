<?php
declare(strict_types=1);
namespace aris;

use pocketmine\Server;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\InventoryHolder;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\tile\Container;
use pocketmine\tile\ContainerTrait;
use pocketmine\tile\Furnace;
use pocketmine\tile\Nameable;
use pocketmine\tile\NameableTrait;
use pocketmine\tile\Spawnable;

use aris\utils\HopperConfig;
use aris\utils\Facing;
class HopperTile extends Spawnable implements InventoryHolder, Container, Nameable{
	use NameableTrait{
		addAdditionalSpawnData as addNameSpawnData;
	}
	use ContainerTrait;

	protected $inventory;

	protected $area;
	protected $transferCooldown = 0; 
	
	private $is_vip = false;
	
	//To-do: Smart Fuel and Smelting input
	
	protected function readSaveData(CompoundTag $nbt) : void{
		if($nbt->hasTag("isVip")){
			$this->is_vip = boolval($nbt->getInt("isVip"));
		}
		if($nbt->hasTag("transferCooldown")){
			$this->transferCooldown = $nbt->getInt("transferCooldown");
		}
		$this->inventory = new HopperInventory($this);
		$this->loadName($nbt);
		$this->loadItems($nbt);
		$this->scheduleUpdate();

		$this->area = new AxisAlignedBB($this->x, $this->y+1, $this->z, $this->x+1, $this->y + 2, $this->z+1);
	}
	
	protected function writeSaveData(CompoundTag $nbt): void{
		$nbt->setInt("transferCooldown", $this->transferCooldown);
		$nbt->setInt("isVip", intval($this->is_vip));
		$this->saveName($nbt);
		$this->saveItems($nbt);
	}
	
	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->addNameSpawnData($nbt);
	}
	
	public function getData() : HopperConfig{
		$main = Main::getInstance();
		return $this->is_vip ?  $main->getVipData() : $main->getData();
	}

	public function getDefaultName() : string{
		return $this->is_vip() ? "VIP Hopper" : "Normal Hopper";
	}
	
	public function getInventory(){
		return $this->inventory;
	}
	
	public function getRealInventory(){
		return $this->inventory;
	}
	
	public function onUpdate() : bool{
		if($this->closed) return false;
		
		$block = $this->getBlock();
		if(!$block instanceof Hopper) return false;
		$inventory = $this->getInventory();
		$item_sucking_tick_rate = $this->getData()->getItemSuckingTickRate();
		$transfer_tick_rate = $this->getData()->getTransferTickRate();
		$transfer_per_tick = $this->getData()->getTransferPerTick();
		$time = Server::getInstance()->getTick();
		
		//Item Sucking
		if($time % $item_sucking_tick_rate == 0){
			$entities = $this->getLevel()->getNearbyEntities($this->area);
			foreach($entities as $entity){
				if(!$entity instanceof ItemEntity) continue;
				$itemEntity = $this->level->getEntity($entity->getId());
				if(is_null($itemEntity)) continue;
				$item = $entity->getItem();
				$tile = $this->level->getTileAt(intval($entity->x), intval($entity->y - 1), intval($entity->z));
				if(is_null($tile)) $tile = $this->level->getTileAt(intval($entity->x), intval($entity->y), intval($entity->z));
				$tile_inventory = $tile->getInventory();
				if(!$tile_inventory->canAddItem($item)) continue;
				$tile_inventory->addItem($item);
				$entity->flagForDespawn();
				$this->level->removeEntity($entity);
				break;
			}
		}
		
		if($this->transferCooldown == $time) return true;
		$this->transferCooldown = $time; 
		
		//Facing Container
		if($time % $transfer_tick_rate != 0) return true;
		$facing_tile = $block->getContainerFacing();
		if($facing_tile instanceof HopperTile) $facing_tile->onUpdate();
		if($facing_tile instanceof Container){
			$facing_inventory = $facing_tile->getInventory();
			foreach($inventory->getContents() as $slot => $item){
				if(is_null($item)) continue;
				if($facing_tile instanceof Furnace){
					if($block->getFace() == Facing::DOWN){
						$smelting = $facing_inventory->getSmelting();
						if($smelting->getCount() >= $facing_inventory->getMaxStackSize()) break;
						if($smelting->getId() != 0 && !$item->equals($smelting)) continue;
						$clone_item = clone $item;
						$clone_item->setCount(min($item->getCount(), $transfer_per_tick) + $smelting->getCount());
						$residue_count = $clone_item->getCount() - $facing_inventory->getMaxStackSize();
						if($residue_count > 0) $item->setCount($item->getCount() - $clone_item->getCount() + $smelting->getCount() + $residue_count);
						else $item->setCount($item->getCount() - $clone_item->getCount() + $smelting->getCount());
						$facing_inventory->setSmelting($clone_item);
					}else{
						$fuel = $facing_inventory->getFuel();
						if($fuel->getCount() >= $facing_inventory->getMaxStackSize()) break;
						if($fuel->getId() != 0 && !$item->equals($fuel)) continue;
						$clone_item = clone $item;
						$clone_item->setCount(min($item->getCount(), $transfer_per_tick) + $fuel->getCount());
						$residue_count = $clone_item->getCount() - $facing_inventory->getMaxStackSize();
						if($residue_count > 0) $item->setCount($item->getCount() - $clone_item->getCount() + $fuel->getCount() + $residue_count);
						else $item->setCount($item->getCount() - $clone_item->getCount() + $fuel->getCount());
						$facing_inventory->setFuel($clone_item);
					}
					$inventory->setItem($slot, $item);
					break;
				}
				$clone_item = clone $item;
				$clone_item->setCount(min($item->getCount(), $transfer_per_tick));
				if(!$facing_inventory->canAddItem($clone_item)) continue;
				$residue_count = 0;
				foreach($facing_inventory->addItem($clone_item) as $residue) $residue_count += $residue->getCount();
				if($residue_count == 0) $item->setCount($item->getCount() - $transfer_per_tick);
				else $item->setCount($item->getCount() - $transfer_per_tick + $residue_count);
				$inventory->setItem($slot, $item);
				break;
			}
		}
		
		//Above Container
		$above_tile = $block->getContainerAbove();
		if($above_tile instanceof Container){
			$above_inventory = $above_tile->getInventory();
			if($above_tile instanceof Furnace){
				$item = $above_inventory->getResult();
				if($item->getId() == 0) return true;
				$clone_item = clone $item;
				$clone_item->setCount(min($item->getCount(), $transfer_per_tick));
				if(!$inventory->canAddItem($clone_item)) return true;
				$residue_count = 0;
				foreach($inventory->addItem($clone_item) as $residue) $residue_count += $residue->getCount();
				if($residue_count == 0) $item->setCount($item->getCount() - $transfer_per_tick);
				else $item->setCount($item->getCount() - $transfer_per_tick + $residue_count);
				$above_inventory->setResult($item);
				return true;
			}
			foreach($above_inventory->getContents() as $slot => $item){
				if(is_null($item)) continue;
				$clone_item = clone $item;
				$clone_item->setCount(min($item->getCount(), $transfer_per_tick));
				if(!$inventory->canAddItem($clone_item)) continue;
				$residue_count = 0;
				foreach($inventory->addItem($clone_item) as $residue) $residue_count += $residue->getCount();
				if($residue_count == 0) $item->setCount($item->getCount() - $transfer_per_tick);
				else $item->setCount($item->getCount() - $transfer_per_tick + $residue_count);
				$above_inventory->setItem($slot, $item);
				break;
			}
		}
		return true;
	}
}
